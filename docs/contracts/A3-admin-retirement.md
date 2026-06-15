# عقد الدفعة A3 — إخفاء الهوية الإداري (Retirement / PDPL)

> المرحلة A (الامتثال) — الدفعة الأخيرة. العقد أولاً. مالكو التنفيذ: backend-dev + frontend-dev. البوّابة: reviewer مستقلّ.
> معيار القبول (feature-matrix → «الامتثال للخصوصية Retirement» P0):
> بعد التنفيذ لا تظهر بيانات شخصية في أي استعلام؛ تُحفظ المراجع المجهّلة؛ موثّق في سجل التدقيق.

## الفجوة المعالَجة
`DELETE /admin/users/{id}` الحالي يُجري soft-delete **لكنه يُبقي PII** (الاسم/البريد) في الصف المحذوف — قصور PDPL. A3 يضيف **retire** الذي يُعمّي الهوية فعلياً عبر خدمة `AnonymizeUser` المشتركة (المبنية في A2)، فيكون مساراً إدارياً مكافئاً لحذف المستخدم الذاتي.

## عقد الـ API
### `POST /api/v1/admin/users/{user}/retire` — الاسم `api.admin.users.retire`
- الوسطاء: `auth:sanctum` + صلاحية `users.manage` (Super Admin). 
- الحراسات (مطابقة لأعراف `UserController`): لا يمكن إخفاء هوية الذات → `422`؛ لا يمكن إخفاء هوية الإدارة العليا → `403`.
- التنفيذ: `AnonymizeUser->handle($user, 'user.retired', actor: $request->user())` — يبطل التوكنات، يُعمّي PII، يضع `disabled_at`، soft-delete، ويكتب تدقيقاً (causer = المشرف).
- الاستجابة: `204`.

## الموديول
- لا خدمة جديدة — يُعاد استخدام `App\Contexts\Identity\Application\AnonymizeUser`.
- طريقة جديدة `UserController::retire` (رفيعة) + مسار واحد. لا هجرة.

## أثر الكود القائم
- `DELETE /admin/users/{id}` (`destroy`) يبقى كما هو (حذف قابل للاستعادة دون تعمية) لتفادي كسر `AdminUserManagementTest`؛ `retire` هو المسار المتوافق مع PDPL. (يُسجَّل التمييز للمراجعة.)

## تقسيم المسؤوليات
- **backend-dev:** `UserController::retire` + المسار في `routes/api.php`.
- **frontend-dev:** زر «إخفاء الهوية» في صفحة `admin/users` (تأكيد ثم POST ثم إعادة تحميل) + نصوص i18n.
- **qa-tester:** اختبار: مشرف يُخفي هوية مستخدم → 204 + تعمية + trashed + إبطال توكنات + تدقيق `user.retired` بفاعل المشرف؛ منع الذات (422)؛ منع الإدارة العليا (403)؛ منع غير المخوّل (403).
