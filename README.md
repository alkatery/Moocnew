# منصة MOOC — MOOC Platform

منصة تعليم جماهيري مفتوح (MOOC) تعمل بالكامل في **الوضع المجاني**، مع وحدة
تجارة (Commerce) **معزولة خلف راية `payments.enabled`** تُفعَّل لاحقاً دون
إعادة بناء. المرجع الكامل والملزِم: [`docs/PRD-MOOC-Platform-v2.md`](docs/PRD-MOOC-Platform-v2.md).

## المكدس التقني

- **Backend:** PHP 8.3 / Laravel 11
- **قاعدة البيانات:** PostgreSQL 16
- **الكاش/الطوابير:** Redis 7
- **المصادقة:** Laravel Sanctum
- **الصلاحيات:** `spatie/laravel-permission` + Policies/Gates
- **الاختبارات:** Pest (وحدة + Feature)
- **البنية:** Docker (لا Kubernetes) + خدمات مُدارة

## فلسفة البناء

Modular Monolith بسياقات محدودة (Bounded Contexts)، كل سياق مفصول إلى طبقات
`Domain` / `Application` / `Infrastructure`. الالتحاق (Enrollment) مفصول تماماً
عن الدفع. النقود تُخزَّن بوحدات صحيحة (`*_minor`) — لا `float` إطلاقاً.

بنية السياقات والقرارات المعمارية موثّقة في:

- [`docs/architecture/CONTEXTS.md`](docs/architecture/CONTEXTS.md)
- [`docs/adr/`](docs/adr/) — سجل القرارات المعمارية (ADR)

## التشغيل محلياً

```bash
cp .env.example .env
composer install
php artisan key:generate

# مع Docker (PostgreSQL 16 + Redis 7 + Nginx)
docker compose up -d
docker compose exec app php artisan migrate --seed

# أو مقابل خدمات محلية
php artisan migrate --seed
```

## الاختبارات

تعمل الاختبارات مقابل PostgreSQL (قاعدة `mooc_test`) — راجع `phpunit.xml`.

```bash
./vendor/bin/pest        # تشغيل المجموعة
./vendor/bin/pint --test # فحص أسلوب الكود
```

## حالة التنفيذ

التسليم على مراحل قابلة للاختبار (PRD §10):

- ✅ **المعلم 1 — التأسيس:** بنية السياقات · Docker · CI · جدول `settings` وراية `payments.enabled`.
- ✅ **المعلم 2 — الهوية والصلاحيات:** الأدوار الخمسة (spatie) + Gates · تسجيل/دخول/خروج Sanctum · موافقات PDPL · سجل تدقيق · لوحة إعدادات Super Admin لتبديل الراية.
- ✅ **المعلم 3 — الكتالوج:** تصنيفات/دورات/أقسام/دروس · سير النشر (مسودة→مراجعة→منشور) عبر آلة حالة · بحث وفلترة عبر Scout (+Meilisearch) · صلاحيات عبر CoursePolicy.
- ✅ **المعلم 4 — الالتحاق والوصول:** `EnrollmentService` (مجاني مباشر / مدفوع معلّق خلف الراية) · آلة حالة الالتحاق · تتبّع التقدم وموضع الفيديو · تشغيل موقّع بثلاثة مزوّدين (Bunny/تخزين/يوتيوب) · webhook حالة الفيديو (توقيع + idempotency).
- ✅ **المعلم 5 — الاختبارات والواجبات:** بنك أسئلة (اختيار/صح‑خطأ/قصير) · تصحيح آلي · محاولات بخلط وتوقيت وحد محاولات · واجبات برفع ملف وتصحيح يدوي.
- ⏳ المعالم 6–8 — الشهادات، الإشعارات، الإحصاء.
