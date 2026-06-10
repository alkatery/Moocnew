#!/usr/bin/env bash
#
# One-shot provisioner for a fresh Ubuntu 22.04/24.04 VPS (Hostinger KVM,
# Hetzner CX, …) to run the MOOC platform with Docker Compose: backend API,
# PostgreSQL, Redis, Meilisearch, Horizon worker and the scheduler — behind
# Caddy with automatic HTTPS. Idempotent: safe to re-run.
#
# Usage (as root):
#   APP_DOMAIN=api.example.com  WEB_DOMAIN=example.com  ./setup-vps.sh
#
set -euo pipefail

APP_DOMAIN="${APP_DOMAIN:?set APP_DOMAIN (e.g. api.example.com)}"
WEB_DOMAIN="${WEB_DOMAIN:?set WEB_DOMAIN (e.g. example.com)}"
APP_DIR="${APP_DIR:-/opt/mooc}"

echo "==> Installing base packages, Docker, firewall…"
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get install -y ca-certificates curl git ufw fail2ban
install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
chmod a+r /etc/apt/keyrings/docker.asc
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] \
https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo "$VERSION_CODENAME") stable" \
  > /etc/apt/sources.list.d/docker.list
apt-get update -y
apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

echo "==> Hardening: firewall + fail2ban…"
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable
systemctl enable --now fail2ban

echo "==> Fetching application into ${APP_DIR}…"
if [ ! -d "${APP_DIR}/.git" ]; then
  git clone "${REPO_URL:-https://github.com/alkatery/moocnew.git}" "${APP_DIR}"
fi
cd "${APP_DIR}"

if [ ! -f .env ]; then
  cp .env.example .env
  echo "!! Edit ${APP_DIR}/.env with production secrets, then re-run this script."
  echo "   Required: APP_KEY (php artisan key:generate), DB_*, MEILISEARCH_KEY,"
  echo "   MOYASAR_*/BUNNY_* (when going live), MAIL_*, SENTRY_LARAVEL_DSN."
fi

echo "==> Building & starting containers…"
docker compose build
docker compose up -d

echo "==> Migrating, seeding, linking storage, caching, search index…"
docker compose exec -T app php artisan key:generate --force || true
docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan db:seed --force
docker compose exec -T app php artisan storage:link || true
docker compose exec -T app php artisan config:cache route:cache
docker compose exec -T app php artisan scout:sync-index-settings || true

echo "==> Installing the scheduler cron (every minute)…"
CRON="* * * * * cd ${APP_DIR} && docker compose exec -T app php artisan schedule:run >> /var/log/mooc-schedule.log 2>&1"
( crontab -l 2>/dev/null | grep -v 'artisan schedule:run' ; echo "${CRON}" ) | crontab -

echo "==> Nightly database backup to local + (optional) S3/R2…"
cat > /usr/local/bin/mooc-backup <<'EOF'
#!/usr/bin/env bash
set -euo pipefail
cd /opt/mooc
TS=$(date +%F-%H%M)
docker compose exec -T pgsql pg_dump -U "${DB_USERNAME:-mooc}" "${DB_DATABASE:-mooc}" | gzip > "/var/backups/mooc-${TS}.sql.gz"
find /var/backups -name 'mooc-*.sql.gz' -mtime +14 -delete
# Optional offsite copy (configure rclone remote "r2"):
command -v rclone >/dev/null && rclone copy "/var/backups/mooc-${TS}.sql.gz" r2:mooc-backups/ || true
EOF
chmod +x /usr/local/bin/mooc-backup
mkdir -p /var/backups
( crontab -l 2>/dev/null | grep -v mooc-backup ; echo "30 2 * * * /usr/local/bin/mooc-backup" ) | crontab -

cat <<EOF

==> Done.
Next:
  1) Point DNS:  ${APP_DOMAIN} and ${WEB_DOMAIN}  ->  this server's IP.
  2) Caddy (in docker-compose) will issue HTTPS automatically once DNS resolves.
  3) Deploy the Next.js frontend (Vercel or 'npm run build && npm start' on
     this box) with NEXT_PUBLIC_API_BASE=https://${APP_DOMAIN}/api/v1
     and NEXT_PUBLIC_SITE_URL=https://${WEB_DOMAIN}.
  4) Create the first Super Admin:
       docker compose exec app php artisan tinker --execute "\\
         \\\$u=App\\Models\\User::factory()->create(['email'=>'you@${WEB_DOMAIN}','password'=>'CHANGE-ME']); \\
         \\\$u->assignRole('super_admin');"
EOF
