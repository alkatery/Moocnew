# النشر والتشغيل (Deployment)

> الهدف: تشغيل المنصة على **VPS من هوستنقر** (أو أي خادم KVM) بـ Docker، دون
> Kubernetes (قرار الـ PRD §0/§2). الواجهة الخلفية API فقط؛ واجهة Next.js
> تُنشر منفصلة.

## المتطلبات على الخادم
- Docker + Docker Compose.
- نطاق + شهادة TLS (Caddy/Nginx + Let's Encrypt) أمام حاوية `web`.

## المكوّنات (من `docker-compose.yml`)
- `app` (PHP-FPM 8.3) · `web` (Nginx) · `pgsql` (PostgreSQL 16) ·
  `redis` (7) · `meilisearch` (بحث).
- العمّال: شغّل `php artisan horizon` كحاوية/خدمة منفصلة لمعالجة الطوابير.
- المجدول: أضف cron واحد على الخادم:
  `* * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1`
  (يشغّل تذكيرات الحصص وغيرها).

## خطوات الإقلاع
```bash
cp .env.example .env            # ثم اضبط القيم الإنتاجية أدناه
docker compose build
docker compose up -d
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --force      # الأدوار والإعدادات ومحتوى الموقع
docker compose exec app php artisan storage:link         # روابط الصور المرفوعة (الشعار والصور)
docker compose exec app php artisan config:cache route:cache
docker compose exec app php artisan scout:sync-index-settings   # Meilisearch
```

## الإعدادات الإنتاجية الأساسية (`.env`)
- `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://...`
- قاعدة البيانات/Redis/Meilisearch إلى أسماء خدمات Docker.
- الوسائط: `MEDIA_DISK_DRIVER=s3` + مفاتيح Cloudflare R2 (نقل مجاني) أو S3.
- المراقبة: `SENTRY_LARAVEL_DSN=...`.
- التجارة (عند التفعيل): `MOYASAR_SECRET_KEY` و`MOYASAR_WEBHOOK_SECRET`.
- الفيديو المُدار (اختياري): مفاتيح `BUNNY_STREAM_*`.
- الفصول الحية (اختياري): `ZOOM_ACCOUNT_TOKEN` / `GOOGLE_MEET_ACCESS_TOKEN`
  (أو استخدم مزوّد «رابط مباشر» بلا مفاتيح).

## التفعيل التدريجي
- المنصة تعمل **مجاناً بالكامل** افتراضياً (`payments.enabled=false`).
- لتفعيل التجارة: `PATCH /api/v1/admin/settings/payments {"enabled": true}`
  بحساب Super Admin — تظهر مسارات `/api/v1/commerce/*` فوراً دون إعادة نشر.

## المراقبة والتعافي
- **الطوابير:** لوحة Horizon على `/horizon` (محميّة لمن يملك `analytics.view`).
- **توثيق API:** Scramble على `/docs/api` (محميّة خارج البيئة المحلية).
- **الأخطاء:** Sentry عبر الـ DSN.
- **النسخ الاحتياطي:** جدوِل `pg_dump` يومياً لقاعدة `mooc` + لقطات لتخزين
  الوسائط (R2/S3 يوفّران نسخاً)، واحتفظ بنسخ خارج الخادم. اختبر الاستعادة دورياً.

## CI/CD
- `.github/workflows/ci.yml` يشغّل Pint + Pest على PostgreSQL 16 + Redis لكل
  دفع/PR. اربط النشر (مثلاً عبر SSH/Compose) بعد نجاح CI على الفرع الرئيسي.
