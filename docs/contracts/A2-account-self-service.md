# عقد الدفعة A2 — الخدمة الذاتية للحساب (حقوق PDPL)

> المرحلة A (الامتثال). العقد أولاً. مالكو التنفيذ: backend-dev + frontend-dev. البوّابة: reviewer مستقلّ.
> معايير القبول (feature-matrix → «إعدادات الحساب» P0، و«GDPR تصدير/حذف» P0):
> تغيير كلمة المرور يبطل الجلسات الأخرى · تصدير يشمل بيانات المستخدم · حذف يخفي الهوية ويحافظ على سلامة السجلات.

## عقود الـ API — كلها تحت `auth:sanctum`، بادئة `/api/v1/account`

### 1) `PATCH /account` — تحديث الملف الذاتي
- الطلب (كلها اختيارية، تُحدَّث الموجودة فقط): `name`, `phone`, `country(size:2)`, `education_level`, `interests[]`, `locale(in:ar,en)`, `timezone(timezone)`.
- الاستجابة: `200` → `{ "user": UserResource }`. تدقيق: `user.profile_updated`.

### 2) `PUT /account/password` — تغيير كلمة المرور
- الطلب: `current_password` (must match)، `password` + `password_confirmation` (نفس قوة التسجيل: `Password::min(8)`، `confirmed`).
- نجاح: يحدّث كلمة المرور، **يبطل كل التوكنات الأخرى** ويُبقي الجلسة الحالية. تدقيق: `user.password_changed`.
- الاستجابة: `200` → `{ "message": "تم تحديث كلمة المرور." }`. كلمة حالية خاطئة → `422` على `current_password`.

### 3) `GET /account/export` — حق الوصول (PDPL)
- يعيد البيانات الشخصية للمستخدم: الملف، الموافقات (النوع/الإصدار/الطابع)، الالتحاقات (عنوان الدورة/الحالة/الإنجاز/التواريخ)، الشهادات (الموضوع/النوع/التقدير/التاريخ/UUID).
- الاستجابة: `200` JSON `{ "data": {...} }` مع ترويسة `Content-Disposition: attachment; filename="my-data.json"`. تدقيق: `user.data_exported`.

### 4) `DELETE /account` — حق الحذف/إخفاء الهوية (PDPL)
- الطلب: `current_password` (تأكيد). 
- يستدعي خدمة `AnonymizeUser` المشتركة: يبطل كل التوكنات، يُعمّي PII (`name`→«مستخدم محذوف»، `email`→`deleted-{uuid}@deleted.invalid`، تصفير phone/country/education_level/interests)، يضع `disabled_at`، ثم `soft delete`. تدقيق: `user.self_deleted`.
- يحافظ على سلامة السجلات المرتبطة (الالتحاقات/الشهادات/الدفتر) عبر الإبقاء على المفتاح مع تعمية الهوية — لا حذف فعلي (موازنة PDPL/سلامة مرجعية).
- الاستجابة: `204`. كلمة حالية خاطئة → `422`.

## الموديول والخدمة المشتركة
- خدمة جديدة `App\Contexts\Identity\Application\AnonymizeUser::handle(User $user, string $event, ?User $actor)` — تُستخدم في A2 (حذف ذاتي) و**يعاد استخدامها في A3** (retire إداري). معاملة واحدة (transaction).
- متحكّم جديد `Http/Controllers/Api/V1/Account/AccountController.php` (رفيع). FormRequests: `UpdateAccountRequest`, `ChangePasswordRequest`.

## أثر الاختبارات القائمة
- لا تغيير سلوكي على مسارات قائمة؛ إضافة فقط. اختبار جديد `AccountSelfServiceTest`.

## تقسيم المسؤوليات
- **backend-dev:** `AnonymizeUser`، `AccountController`، `UpdateAccountRequest`، `ChangePasswordRequest`، مسارات `account/*`.
- **frontend-dev:** صفحة `account` (RTL): قسم اللغة/المنطقة، قسم كلمة المرور، قسم الخصوصية (تصدير + حذف مؤكَّد). رابط في التنقّل. نصوص i18n. تحديث المستخدم في سياق الـ auth بعد التعديل.
- **qa-tester:** `AccountSelfServiceTest` (تحديث الملف، تغيير كلمة المرور يبطل التوكنات الأخرى، كلمة حالية خاطئة 422، تصدير يحوي البيانات، حذف يُعمّي ويُبطل التوكنات ويحجب الدخول).
