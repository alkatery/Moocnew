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
apt-get install -y ca-certificates curl git ufw fail2ban openssl
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
ufw allow 8080/tcp   # web container is published on :8080 (direct trial access)
ufw allow 3000/tcp   # Next.js frontend (FULL mode)
ufw --force enable
systemctl enable --now fail2ban

# FULL mode builds the Next.js bundle, which is memory-hungry. Add swap so a
# small (2–4GB) box does not OOM during the build. Idempotent.
if [ "${FULL:-0}" = "1" ] && [ ! -f /swapfile ]; then
  echo "==> Creating 4G swapfile for the frontend build…"
  fallocate -l 4G /swapfile || dd if=/dev/zero of=/swapfile bs=1M count=4096
  chmod 600 /swapfile
  mkswap /swapfile
  swapon /swapfile
  grep -q '/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi

echo "==> Fetching application into ${APP_DIR}…"
if [ ! -d "${APP_DIR}/.git" ]; then
  git clone --branch "${BRANCH:-main}" \
    "${REPO_URL:-https://github.com/alkatery/moocnew.git}" "${APP_DIR}"
fi
cd "${APP_DIR}"

# Pick the stack. Docker Compose honours COMPOSE_FILE, so every `docker
# compose` call below targets the right stack automatically.
#   FULL=1  → everything + Next.js UI + Horizon + Meilisearch (one box)
#   LIGHT=1 → no Meilisearch, in-memory search, inline queues (fits 2GB)
#   (default) → backend dev stack
if [ "${FULL:-0}" = "1" ]; then
  export COMPOSE_FILE="docker-compose.full.yml"
  # The browser talks to the API directly on :8080, so bake that public URL
  # into the frontend build. Override NEXT_PUBLIC_API_BASE to use HTTPS/domain.
  export NEXT_PUBLIC_API_BASE="${NEXT_PUBLIC_API_BASE:-http://${APP_DOMAIN}:8080/api/v1}"
  echo "==> FULL mode: UI + Horizon + Meilisearch. API for the UI: ${NEXT_PUBLIC_API_BASE}"
elif [ "${LIGHT:-0}" = "1" ]; then
  export COMPOSE_FILE="docker-compose.light.yml"
  echo "==> LIGHT trial mode: Meilisearch off, search in-memory, queues sync."
else
  export COMPOSE_FILE="docker-compose.yml"
fi

if [ ! -f .env ]; then
  cp .env.example .env
  sed -i "s#^APP_URL=.*#APP_URL=http://${APP_DOMAIN}:8080#" .env
  if [ "${LIGHT:-0}" = "1" ]; then
    # Make search work without Meilisearch on the trial box.
    sed -i 's/^SCOUT_DRIVER=.*/SCOUT_DRIVER=collection/' .env
    sed -i 's/^SCOUT_QUEUE=.*/SCOUT_QUEUE=false/' .env
    sed -i 's/^QUEUE_CONNECTION=.*/QUEUE_CONNECTION=sync/' .env
  fi
  echo "!! Edit ${APP_DIR}/.env with production secrets, then re-run this script."
  echo "   Required: APP_KEY (php artisan key:generate), DB_*,"
  echo "   MOYASAR_*/BUNNY_* (when going live), MAIL_*, SENTRY_LARAVEL_DSN."
fi

# Meilisearch runs in production mode in the full stack and refuses master
# keys shorter than 16 bytes — the .env.example default ("masterKey") is too
# short and the container crash-loops. Generate a strong key once. The same
# .env feeds both Compose (container env) and Laravel (Scout client).
if [ "${LIGHT:-0}" != "1" ]; then
  MEILI_KEY="$(grep -E '^MEILISEARCH_KEY=' .env | head -1 | cut -d= -f2- || true)"
  if [ "${#MEILI_KEY}" -lt 16 ]; then
    NEW_KEY="$(openssl rand -hex 16)"
    if grep -qE '^MEILISEARCH_KEY=' .env; then
      sed -i "s/^MEILISEARCH_KEY=.*/MEILISEARCH_KEY=${NEW_KEY}/" .env
    else
      echo "MEILISEARCH_KEY=${NEW_KEY}" >> .env
    fi
    echo "==> Generated a strong MEILISEARCH_KEY in .env."
  fi
fi

echo "==> Building & starting containers…"
docker compose build
docker compose up -d

# Both compose files bind-mount the host tree over /var/www/html, which
# shadows the vendor/ baked into the image. Install dependencies into the
# mounted tree so the running container can boot Laravel.
echo "==> Installing PHP dependencies inside the container…"
docker compose exec -T app composer install --no-dev --optimize-autoloader --no-interaction

echo "==> Migrating, seeding, linking storage, caching…"
docker compose exec -T app php artisan key:generate --force || true
docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan db:seed --force
docker compose exec -T app php artisan storage:link || true
docker compose exec -T app php artisan config:cache route:cache
# Meilisearch index settings only matter when Meilisearch is running.
if [ "${LIGHT:-0}" != "1" ]; then
  docker compose exec -T app php artisan scout:sync-index-settings || true
  # Index the seeded courses so search returns results immediately.
  docker compose exec -T app php artisan scout:import \
    "App\\Contexts\\Catalog\\Infrastructure\\Persistence\\Course" || true
fi

echo "==> Installing the scheduler cron (every minute)…"
CRON="* * * * * cd ${APP_DIR} && COMPOSE_FILE=${COMPOSE_FILE} docker compose exec -T app php artisan schedule:run >> /var/log/mooc-schedule.log 2>&1"
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
# Pin the backup to the same stack the box is running.
sed -i "2i export COMPOSE_FILE=${COMPOSE_FILE}" /usr/local/bin/mooc-backup
mkdir -p /var/backups
( crontab -l 2>/dev/null | grep -v mooc-backup ; echo "30 2 * * * /usr/local/bin/mooc-backup" ) | crontab -

cat <<EOF

==> Done. (stack: ${COMPOSE_FILE})
   API:  http://${APP_DOMAIN}:8080
$( [ "${FULL:-0}" = "1" ] && echo "   UI :  http://${APP_DOMAIN}:3000   <-- open this in your browser" )
Next:
  1) Create the first Super Admin (login for the UI/admin panel):
       COMPOSE_FILE=${COMPOSE_FILE} docker compose exec -T app php artisan tinker --execute "\\
         \\\$u=App\\Models\\User::factory()->create(['email'=>'admin@${WEB_DOMAIN}','password'=>'ChangeMe2026']); \\
         \\\$u->assignRole('super_admin'); echo 'OK';"
  2) For production: front the API with HTTPS (Caddy/Nginx + Let's Encrypt),
     point ${APP_DOMAIN} -> :8080, and rebuild the UI with
     NEXT_PUBLIC_API_BASE=https://${APP_DOMAIN}/api/v1.
EOF
