# النشر والتشغيل (Deployment)

> **إقلاع سريع على VPS:** بعد توجيه النطاق للخادم، نفّذ السكربت الجاهز:
> ```bash
> APP_DOMAIN=api.example.com WEB_DOMAIN=example.com bash docs/deploy/setup-vps.sh
> ```
> يثبّت Docker وجدار الحماية وfail2ban، يبني الحاويات، يشغّل الهجرات والبذور
> وربط التخزين والكاش وفهرس البحث، ويضيف cron للمجدول ونسخاً احتياطياً ليلياً
> لقاعدة البيانات (مع نسخ اختياري إلى R2/S3). أنشئ أول حساب إدارة عليا كما في
> نهاية السكربت.
>
> ### وضع التجربة الكاملة (`FULL=1`) — المنصة كاملة على خادم واحد
> يشغّل كل شيء: الواجهة الخلفية + **واجهة Next.js** + Horizon + Meilisearch.
> ```bash
> FULL=1 APP_DOMAIN=<server-ip> WEB_DOMAIN=<server-ip> bash docs/deploy/setup-vps.sh
> ```
> يبني السكربت واجهة Next.js (مع `swap` تلقائي لتفادي نفاد الذاكرة)، ويضمّن
> فيها عنوان الـ API العام (`http://<server-ip>:8080/api/v1`)، ويفتح المنفذين
> `8080` (الـ API) و`3000` (الواجهة). افتح المتصفح على `http://<server-ip>:3000`.
> لاستخدام نطاق/HTTPS، مرّر `NEXT_PUBLIC_API_BASE=https://api.example.com/api/v1`.
> يحتاج هذا الوضع خادماً بذاكرة **4GB** أو أكثر (أو 2GB مع الـ swap للبناء).
>
> ينشئ السكربت تلقائياً حساب **إدارة عليا** (`ADMIN_EMAIL`/`ADMIN_PASSWORD`،
> افتراضياً `admin@mooc.test` / `AdminMooc2026` — غيّرها بعد الدخول)، ويبذر
> **محتوى تجريبياً عربياً** (4 دورات، مسار تخصصي، أخبار، وحسابا
> `instructor@mooc.test` و`student@mooc.test`). عطّل المحتوى التجريبي بـ
> `DEMO=0`، أو ابذره يدوياً: `php artisan db:seed --class=DemoContentSeeder`.
>
> ### وضع التجربة الخفيف (يناسب VPS بسعة 2GB)
> أضف `LIGHT=1` فيعمل بلا Meilisearch (البحث يتحوّل تلقائياً لمحرّك Scout
> `collection` في الذاكرة) وبطوابير متزامنة، فلا تعمل سوى أربع خدمات:
> `app · web · pgsql · redis`.
> ```bash
> LIGHT=1 APP_DOMAIN=api.example.com WEB_DOMAIN=example.com bash docs/deploy/setup-vps.sh
> ```
> يضبط السكربت تلقائياً `SCOUT_DRIVER=collection` و`QUEUE_CONNECTION=sync`
> ويُبقي المساعد الذكي معطّلاً (`AI_DEFAULT_MODE=off`). للتشغيل اليدوي:
> ```bash
> docker compose -f docker-compose.light.yml up -d
> ```
> ملاحظة: الوضع الخفيف للتجربة فقط؛ للإنتاج عُد إلى الحزمة الكاملة (Meilisearch
> + عامل طوابير Horizon) بإزالة `LIGHT=1`.


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
  دفع/PR.
- `.github/workflows/deploy.yml` ينشر آلياً عبر **SSH من مشغّل GitHub** عند الدفع/الدمج
  إلى الفرع الإنتاجي، أو يدويّاً من تبويب Actions. لا يعتمد على بيئة محلية.

### تهيئة النشر الآلي (مرّة واحدة)
1. **على الخادم** — شغّل المُهيّئ مرّة (يثبّت Docker، يستنسخ المشروع إلى `/opt/mooc`، يُقلع الحزمة):
   ```bash
   APP_DOMAIN=api.example.com WEB_DOMAIN=example.com bash docs/deploy/setup-vps.sh
   ```
2. **في المستودع** (`Settings → Secrets and variables → Actions`) أضِف الأسرار:
   - `SSH_HOST` — IP/نطاق الخادم · `SSH_USER` — مستخدم SSH · `SSH_PRIVATE_KEY` — مفتاح نشر خاص.
   - (اختياري كـ Variables) `SSH_PORT` (افتراضي 22) · `APP_DIR` (افتراضي `/opt/mooc`) · `DEPLOY_BRANCH`.
   > يُفضَّل إنشاء مفتاح نشر **مخصّص** لهذا الغرض وإضافة عامّه إلى `~/.ssh/authorized_keys` على الخادم، وإبطاله عند الانتهاء. الأسرار لا تظهر في السجلات.
3. بعدها: كل دمج إلى الفرع الإنتاجي → يسحب الخادم أحدث الكود، يبني الحاويات، يشغّل الهجرات وأوامر الكاش. `.env` محميّ (لا يمسّه `git reset`). الفرع الإنتاجي الحالي مضبوط في `deploy.yml` على `claude/mooc-platform-setup-k5fma1` — حدّثه إن أعدت تسمية الفرع.

> ملاحظة أمنية: لتشديد التحقّق من مضيف SSH، استبدل `accept-new` بمفتاح مضيف مثبَّت في الوركفلو.
