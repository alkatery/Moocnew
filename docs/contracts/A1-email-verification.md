# عقد الدفعة A1 — تفعيل البريد الإلكتروني

> المرحلة A (الامتثال). المبدأ: العقد أولاً. مالكو التنفيذ: backend-dev + frontend-dev. البوّابة: reviewer.
> معيار القبول (feature-matrix): «لا توكن فعّال قبل التحقّق؛ التحقّق ينجح عبر الرابط الموقّع؛ إعادة الإرسال محدودة المعدّل».

## القرار المعماري الحاكم
الحجب **عند نقاط إصدار التوكن** لا عبر وسيط `verified` على كل مسار — لأنّ التوكن لا يُصدَر إلا بعد التحقّق، فلا يمكن لغير المُفعّل بلوغ الموارد المحمية أصلاً. هذا يحقّق القبول بأدنى أثر (لا يكسر 280 اختبار `Sanctum::actingAs`).

## عقود الـ API (تحت `/api/v1`)

### 1) `POST /auth/register` (معدّل)
- الطلب: كما هو (name, email, password, password_confirmation, consents[], حقول ملف اختيارية).
- التغيير: ينشئ مستخدماً **غير مُفعّل**، ويرسل إشعار التحقّق، و**لا يصدر توكناً**.
- الاستجابة: `201` → `{ "message": "تم إنشاء حسابك. أرسلنا رابط تفعيل إلى بريدك.", "user": UserResource }`.

### 2) `POST /auth/login` (معدّل)
- بيانات صحيحة + بريد غير مُفعّل → `403` → `{ "message": "يلزم تفعيل بريدك الإلكتروني قبل الدخول.", "code": "email_unverified" }`.
- بيانات خاطئة → `422` (كما هو). حساب موقوف → `422` رسالة الإيقاف (كما هو).
- ترتيب الفحص: بيانات → موقوف → غير مُفعّل.

### 3) `GET /auth/email/verify/{id}/{hash}` (جديد) — الاسم `verification.verify`
- الوسطاء: `signed` + `throttle:6,1`. لا `auth` (التوقيع يصادق).
- التحقّق: `id` يطابق مفتاح المستخدم، و`hash_equals(sha1(email), hash)`.
- نجاح → يضع `email_verified_at`، يطلق `Verified`، ويعيد `200` (عند `expectsJson`) → `{ "message": "تم تفعيل بريدك بنجاح.", "user": UserResource, "token": "..." }` (توكن للدخول التلقائي). غير ذلك → تحويل إلى `FRONTEND_URL/verify-email?status=success`.
- مُفعّل مسبقاً → `200` `{ "message": "بريدك مفعّل مسبقاً.", "already": true }`.
- توقيع غير صالح/منتهٍ → `403` (من وسيط `signed`). هاش غير مطابق → `403` `{ "code": "invalid_verification" }`.

### 4) `POST /auth/email/resend` (جديد) — الاسم `verification.resend`
- الوسطاء: `throttle:6,1`. الطلب: `{ "email": "..." }`.
- مستخدم موجود وغير مُفعّل → يعيد الإرسال. دائماً `200` `{ "message": "إن كان البريد مسجّلاً وغير مفعّل، أرسلنا إليه رابط تفعيل." }` (لا كشف عن وجود الحساب).

## البيانات
- لا هجرة جديدة: `users.email_verified_at` موجود (nullable).
- `User implements MustVerifyEmail` + استعمال السمة `Illuminate\Auth\MustVerifyEmail`.

## الإشعار
- `Illuminate\Auth\Notifications\VerifyEmail` عبر قناة البريد (إشعار أمني ترانزاكشنال يتجاوز مركز التفضيلات عمداً).
- تخصيص `VerifyEmail::createUrlUsing()` في `IdentityServiceProvider::boot()` ليبني رابطاً إلى واجهة `FRONTEND_URL/verify-email?verify_url=<الرابط الموقّع للـ API>` — فيستهلكه SPA ويحافظ على صلاحية التوقيع لمسار الـ API.

## حدود الموديول
المنطق في سياق `Identity`. المتحكّمات رفيعة تحت `Http/Controllers/Api/V1/Auth`. لا أسرار في الكود (`FRONTEND_URL` عبر `config('app.frontend_url')`).

## تقسيم المسؤوليات
- **backend-dev:** `User` (العقد)، `RegisterController`، `LoginController`، `VerifyEmailController` (جديد)، `ResendVerificationController` (جديد)، `routes/api.php`، `IdentityServiceProvider` (تخصيص الرابط)، `config/app.php` (`frontend_url`).
- **frontend-dev:** صفحة `verify-email`، حالة «تحقّق من بريدك» بعد التسجيل، معالجة `email_unverified` في الدخول مع زر إعادة الإرسال، نصوص i18n.
- **qa-tester:** تحديث `RegistrationTest`؛ ملف جديد `EmailVerificationTest` (تسجيل بلا توكن + دخول محجوب لغير المُفعّل + تحقّق عبر رابط موقّع + إعادة إرسال محدودة + هاش غير صالح 403).

## أثر الاختبارات القائمة
- `RegistrationTest`: يُحدَّث (لم يعد توكن عند التسجيل).
- بقية الاختبارات (factory مُفعّل افتراضياً + `actingAs`) لا تتأثّر — بما فيها `FullLifecycleTest` و`AuthenticationTest` و`AdminAccountControlTest`.
