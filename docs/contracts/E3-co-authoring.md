# عقد الدفعة E3 — التأليف الجماعي (Co-authors)

> **الحالة:** نهائي للتنفيذ — يسلّمه `backend-dev` و`frontend-dev` حرفياً.
> **المرجع:** `docs/implementation-plan.md §6 E3` · `docs/feature-matrix.csv` (السطر «التأليف الجماعي Co-authors») · `docs/PRD-MOOC-Platform-v2.md §4 (الأدوار/التخويل) + §6 (المقرر)` · `docs/architecture/CONTEXTS.md` (Catalog يملك المقرر؛ Enrollment يملك «طاقم المقرر»).
> **الهدف:** مالك المقرر (`instructor_id`) يضيف **مؤلّفين مشاركين** (مدرّسين آخرين) لهم وصول تحرير كامل لهذا المقرر تحديداً (RBAC على مستوى المقرر). v1: دور واحد `co_author` يكافئ صلاحيات تحرير المالك على هذا المقرر — **دون** حذف المقرر و**دون** إدارة المؤلّفين (يبقيان للمالك حصراً).
> **المبدأ الحاكم لهذه الدفعة (حسّاس — يمسّ تخويل كل التحرير):** نوسّع **نقطتي حقيقة قائمتين فقط** — `CoursePolicy::update` و`CourseAccess::isStaffFor` — لتشملا المؤلّف المشارك، **دون** لمس `delete`/`submit`/`review`/`destroy`/الالتحاق. كل مسار تحرير يمرّ بإحدى هاتين البوّابتين يرث الصلاحية تلقائياً. لا منطق تخويل جديد لكل مسار. **منع تصعيد الصلاحيات هو المعيار الأول للقبول.**

---

## 0. قرارات معمارية مثبتة (بعد قراءة الكود)

| البند | القرار | المصدر المقروء |
|------|--------|----------------|
| السياق المالك للعلاقة | **Catalog** — العضوية خاصيّة تأليفية للمقرر (مثل المتطلّب السابق). جدول `course_members` يربط `courses ↔ users`. | `Course.php` · `CoursePrerequisiteController` |
| السياق المالك لقرار «من الطاقم؟» | **Enrollment** — `CourseAccess::isStaffFor` تبقى نقطة الحقيقة الوحيدة لـ «طاقم المقرر»؛ نوسّعها لتشمل المؤلّف المشارك. Catalog يكشف العلاقة فقط (`Course::hasCoAuthor`)؛ Enrollment يستهلكها. | `CourseAccess.php:43-47` |
| نموذج العلاقة | many-to-many بين `Course` و`User` عبر جدول `course_members` (عمود `role` مفتوح للنمو، قيمته الوحيدة في v1 = `co_author`). | قرار العقد |
| تعريف «المالك» (بلا تغيير) | `instructor_id === user->id`. المالك **ليس** صفّاً في `course_members` — هو خاصيّة على `courses`. لا ازدواج. | `CoursePolicy::owns()` · `CourseAccess.php:45` |
| من يكون co-author؟ | **مدرّس فقط** (`hasRole('instructor')` أو `can('courses.manage')`) — مطابق `transferCourse`/`resolveInstructorId`. المالك لا يُضاف لنفسه (422). لا تكرار (unique). | `UserController.php:225` · `CourseController.php:161` |
| إدارة الأعضاء | **المالك حصراً** عبر فعل سياسة **جديد** `manageMembers` (المالك أو super_admin فقط — **لا** `courses.review`، **لا** co-author). لا تصعيد: co-author لا يضيف/يزيل أعضاء. | قرار العقد |
| `delete`/`destroy` المقرر | **بلا تغيير** — تبقى `CoursePolicy::delete` (المالك + `courses.manage`). co-author يحاول الحذف ⇒ 403. | `CoursePolicy.php:46-49` · `CourseController::destroy` |
| `submit`/`review`/النشر | **بلا تغيير** — `submit` للمالك، `review`/`approve`/`reject` للمراجع. co-author **لا** يرسل للمراجعة ولا ينشر. | `CoursePolicy.php:54-65` · `CoursePublishingController` |
| `mine` (قائمة الاستوديو) | **نعم — يشمل المقررات التي المستخدم فيها co-author** (وإلّا لا يستطيع فتح الاستوديو لتحريرها). تُضاف `orWhereHas('members'...)`. المراجع يرى الكل (بلا تغيير). | `CourseController::mine` |
| النقود | لا تنطبق (لا حقول نقدية في هذه الدفعة). | — |
| soft delete | لا — صفّ `course_members` يُحذف فعلياً عند الإزالة (لا قيمة تدقيقية؛ قابل لإعادة الإنشاء). تتالي الحذف عند حذف المقرر أو المستخدم. | `course_prerequisites` كنموذج |
| التجارة | لا علاقة — العضوية تأليفية بحتة، خلف Sanctum، لا تمسّ `payments.enabled`. | — |
| PDPL | بحث المدرّسين يكشف **id/name فقط** (لا بريد/هاتف) — تقليل البيانات. الإضافة موافقة ضمنية للمالك (فعل إداري على مقرره)؛ co-author يرى البيانات التعليمية لمقرر صار طاقمه. | PRD §PDPL |

### لماذا فعل سياسة جديد `manageMembers` وليس إعادة استخدام `update`؟
لأنّ `update` ستصير **أوسع** بهذه الدفعة (تشمل co-author). لو أُسندت إدارة الأعضاء لـ `update` لأمكن co-author أن يضيف co-authors آخرين (**تصعيد صلاحيات**). الفصل صريح:
- `update` → المالك + المراجع + **co-author** (تحرير المحتوى).
- `manageMembers` → **المالك فقط** (+ super_admin عبر `Gate::before`). لا co-author، **ولا حتى المراجع** `courses.review` (إدارة فريق التأليف قرار ملكية لا قرار مراجعة).

---

## 1. مخطط البيانات

### جدول جديد `course_members`

هجرة جديدة `database/migrations/<ts>_create_course_members_table.php` (بعد طابع `course_prerequisites`):

| العمود | النوع | قيود |
|--------|------|------|
| `id` | bigint PK | — |
| `course_id` | FK → `courses.id` | `cascadeOnDelete` |
| `user_id` | FK → `users.id` | `cascadeOnDelete` |
| `role` | string | افتراضي `'co_author'` (مفتوح للنمو) |
| `created_at` / `updated_at` | timestamps | — |

قيود:
- `unique(['course_id', 'user_id'])` — لا يُضاف نفس المستخدم مرّتين لنفس المقرر (طبقة ثانية خلف فحص المتحكّم).
- `index('user_id')` — يخدم `mine` (و«أي مقررات أنا co-author فيها؟») وتتالي حذف المستخدم.
- **لا** `soft delete`.

ملاحظات:
- المالك (`instructor_id`) **لا** يُسجَّل هنا — يبقى خاصيّة على `courses`. منع إضافته صفّاً يُفرض في المتحكّم (422) لا في المخطط.
- `role` عمود سلسلة بسيط (لا enum DB) — v1 تكتب `'co_author'` فقط؛ التوسّع لأدوار لاحقة بلا هجرة.

---

## 2. التخويل (الأهمّ — صمّمه بدقّة)

### 2.أ — علاقة الموديل (Catalog)

تُضاف إلى `Course` (Infrastructure/Persistence):

```php
// علاقة المؤلّفين المشاركين — many-to-many عبر course_members (role='co_author').
public function members(): BelongsToMany
{
    return $this->belongsToMany(User::class, 'course_members')
        ->withPivot('role')->withTimestamps();
}

// هل هذا المستخدم مؤلّف مشارك على هذا المقرر؟ (تُستهلك في CoursePolicy و CourseAccess).
public function hasCoAuthor(User $user): bool
{
    return $this->members()
        ->where('users.id', $user->getKey())
        ->wherePivot('role', 'co_author')
        ->exists();
}
```

> ملاحظة DDD: `Course` في Catalog يعرف `User` (علاقة قائمة `instructor()` تفعل ذلك أصلاً) — لا كسر طبقات. قرار «من الطاقم؟» يبقى في Enrollment (يستدعي `hasCoAuthor`).

### 2.ب — `CoursePolicy::update` (تعديل نقطة حقيقة قائمة — حسّاس)

البوّابة الحالية:
```php
public function update(User $user, Course $course): bool
{
    if ($user->can(Permission::ReviewCourses->value)) return true;
    return $user->can(Permission::ManageCourses->value) && $this->owns($user, $course);
}
```
**يُضاف فرع ثالث للمؤلّف المشارك** (المنطق الجديد فقط):
```php
// المؤلّف المشارك يحرّر هذا المقرر كالمالك (محتوى فقط — لا حذف/لا إدارة أعضاء).
if ($course->hasCoAuthor($user)) return true;
```
> **أثر هذا التعديل (كل ما يرثه co-author تلقائياً عبر `can('update', $course)`):**
> كل المسارات التالية تمرّ بـ `can('update', $course)` (المباشر أو عبر `$lesson->section->course` / `$section->course` / `$question->course` / `$quiz->course` / `$assignment->course`) — جميعها تُفتح للمؤلّف المشارك دون أي تغيير في كودها:
>
> | المسار/الطلب | الموضع |
> |---|---|
> | تعديل المقرر | `CourseController::update` ← `UpdateCourseRequest::authorize` |
> | رفع الغلاف | `CourseController::uploadCover` |
> | إنشاء/تعديل/حذف/ترتيب الأقسام | `SectionController` ← `SectionRequest::authorize` |
> | إنشاء/تعديل/حذف/ترتيب الدروس + الأصول + التفريغ | `LessonController` · `LessonTranscriptController` ← `LessonRequest::authorize` |
> | بنك الأسئلة | `QuestionController` ← `StoreQuestionRequest::authorize` |
> | الاختبارات | `QuizController` ← `StoreQuizRequest::authorize` |
> | الواجبات | `StoreAssignmentRequest::authorize` |
> | جدولة الجلسات | `ScheduleSessionRequest::authorize` |
> | المتطلّبات السابقة (E1) | `CoursePrerequisiteController` |
> | ظهور الأقسام المجدول (E2) | `SectionController::update` |
> | تحليلات المقرر | `AnalyticsController::courseDropoff` (`|| can('update', $course)`) |
> | تنزيل/مراجعة التسليمات (C2) | `AssignmentSubmissionController::index` · `SubmissionFileController` · `GradeSubmissionRequest` |
>
> **هذا مقصود ومطلوب:** co-author يساوي المالك في تحرير هذا المقرر بالذات. backend-dev **لا يلمس** أيّاً من هذه المسارات — التعديل الوحيد سطر واحد في `CoursePolicy::update`.

**ما لا يتغيّر في `CoursePolicy`:** `view` (يبقى المالك/المراجع للمسودّات — يُضاف co-author؛ انظر §2.ج)، `delete`, `submit`, `review`, `create` — **بلا أي تعديل**. co-author لا يحذف/لا يرسل للمراجعة/لا ينشر.

### 2.ج — `CoursePolicy::view` (تعديل صغير ضروري)

co-author يجب أن يفتح **مسودّة** المقرر في الاستوديو. `view` الحالية تسمح للمالك/المراجع فقط بالمسودّات. يُضاف co-author:
```php
return $user !== null && (
    $this->owns($user, $course)
    || $user->can(Permission::ReviewCourses->value)
    || $course->hasCoAuthor($user)   // ← جديد
);
```
> بدونه يفشل `CourseController::show` (السطر 75 `can('view', $course)`) على المسودّة، فلا يُحمَّل الاستوديو لـ co-author.

### 2.د — `CourseAccess::isStaffFor` (تعديل نقطة حقيقة قائمة — الأحسّ)

البوّابة الحالية:
```php
public function isStaffFor(User $user, Course $course): bool
{
    return $course->instructor_id === $user->getKey()
        || $user->can(Permission::ReviewCourses->value);
}
```
**يُضاف فرع المؤلّف المشارك:**
```php
public function isStaffFor(User $user, Course $course): bool
{
    return $course->instructor_id === $user->getKey()
        || $course->hasCoAuthor($user)              // ← جديد
        || $user->can(Permission::ReviewCourses->value);
}
```
> **أثر هذا التعديل — كل نقطة يصير فيها co-author «طاقماً» (موثّقة بالكامل، حسّاس):**
>
> | الاستهلاك | الموضع | السلوك الجديد لـ co-author |
> |---|---|---|
> | **Gradebook** (C1) | `CourseGradebookController:29` | يرى درجات كل الطلاب |
> | **مراجعة محاولات الاختبار** | `QuizAttemptController:53` · `QuizController:34` | يرى محاولات الطلاب |
> | **الإعلانات + البريد الجماعي** (C3) | `StoreAnnouncementRequest:25` · `SendBulkEmailRequest:26` · `CourseAnnouncementController` | ينشر إعلانات ويرسل بريداً جماعياً |
> | **المنتدى** (D2) | `ForumController:67,101` | يميّز الإجابات ويحظر/يدير كطاقم |
> | **تجاوز المتطلّبات** (E1) | `EnrollmentController:42` (`$bypass`) | يلتحق بمقرره دون فحص المتطلّبات (معاينة) |
> | **تجاوز الظهور المجدول** (E2) | `CourseController::show:79` · `scopeVisibleTo` | يرى كل الأقسام (مجدولة أو لا) |
> | **`canParticipate`** | `CourseAccess:51` | يُعدّ مشاركاً (يصل لأنشطة المقرر دون التحاق) |
>
> **القرار الصريح:** **نعم، co-author يصبح طاقماً في كل ما سبق.** التبرير: المؤلّف المشارك يحرّر التقييمات فيجب أن يرى تسليماتها ودرجاتها، ويتواصل مع طلاب المقرر، ويعاين مقرره دون قيود — وإلّا كانت «المشاركة في التأليف» منقوصة. هذا اتّساق مع كون co-author نظيراً للمالك في حدود هذا المقرر.
>
> **ما لا يتأثّر بـ `isStaffFor`:** الحذف/الإرسال للمراجعة/النشر **لا** تمرّ بـ `isStaffFor` (تمرّ بـ `delete`/`submit`/`review` في `CoursePolicy` التي لم تتغيّر) — فلا تصعيد.

### 2.هـ — فعل سياسة جديد `CoursePolicy::manageMembers` (المالك حصراً)

```php
// إدارة فريق التأليف — المالك فقط (+ super_admin عبر Gate::before). لا co-author، لا مراجع.
public function manageMembers(User $user, Course $course): bool
{
    return $user->can(Permission::ManageCourses->value) && $this->owns($user, $course);
}
```
> ملاحظة: شرط `can('courses.manage')` يضمن أنّ المالك مدرّس فعلاً (المراجع وحده لا يملك هذه الصلاحية ⇒ لا يدير الأعضاء — مقصود). super_admin يمرّ عبر `Gate::before` (يملك كل شيء).

---

## 3. عقود الـ API (المالك حصراً — `manageMembers`)

كلّها تحت مجموعة `catalog` + `auth:sanctum` القائمة (`routes/api.php:216`)، باسم `api.catalog.courses.members.*`. متحكّم جديد `App\Http\Controllers\Api\V1\Catalog\CourseMemberController`.

### 3.أ — قائمة المؤلّفين المشاركين

```
GET /api/v1/catalog/courses/{course}/members        (api.catalog.courses.members.index)
```
- **التخويل:** `abort_unless($request->user()->can('manageMembers', $course), 403)`.
- **الاستجابة 200:**
```jsonc
{
  "data": [
    { "id": 42, "name": "سارة المطيري", "role": "co_author" },
    { "id": 17, "name": "خالد العتيبي", "role": "co_author" }
  ]
}
```
- **PDPL:** id/name + role فقط — **لا بريد/هاتف**. مقرر بلا أعضاء ⇒ `"data": []`.

### 3.ب — إضافة مؤلّف مشارك

```
POST /api/v1/catalog/courses/{course}/members        (api.catalog.courses.members.store)
```
- **التخويل:** `abort_unless($request->user()->can('manageMembers', $course), 403)`.
- **التحقّق:**
```php
'user_id' => ['required', 'integer', 'exists:users,id'],
```
- **قواعد العمل (422 لكل خرق):**
  1. **ليس مدرّساً** — `! $target->hasRole('instructor') && ! $target->can('courses.manage')` ⇒ 422 «المستخدم المحدّد ليس مدرّساً.» (مطابق `transferCourse`/`resolveInstructorId`).
  2. **المالك نفسه** — `user_id === $course->instructor_id` ⇒ 422 «المالك مؤلّف أصلاً ولا يُضاف كمؤلّف مشارك.».
  3. **مكرّر** — العضو موجود مسبقاً ⇒ 200 (idempotent) بالقائمة الحالية (لا 422؛ نمط E1).
- **النجاح 201:** يُسجَّل الصفّ بـ `role='co_author'`، تُعاد القائمة المحدّثة (نفس شكل §3.أ).
- **العزل:** المالك يضيف لمقرره فقط (تخويل على `$course`).

> منطق المتحكّم بسيط كفاية (مثل `CoursePrerequisiteController`) — لا خدمة مجال جديدة. الترتيب: تخويل → تحقّق → فحص المالك (422) → فحص الدور (422) → `attach`/idempotent.

### 3.ج — إزالة مؤلّف مشارك

```
DELETE /api/v1/catalog/courses/{course}/members/{user}   (api.catalog.courses.members.destroy)
```
- `{user}` = id المستخدم العضو.
- **التخويل:** `abort_unless($request->user()->can('manageMembers', $course), 403)`.
- **204** — تمّت الإزالة أو لم يكن عضواً (idempotent، `detach` بلا شرط — لا 404).
- بعد الإزالة يفقد المستخدم فوراً كل صلاحيات `update`/`isStaffFor` على هذا المقرر (الفحوص حيّة على الجدول).

### 3.د — بحث المدرّسين للاختيار (مساعد الواجهة — المالك حصراً)

الـ `users.index` الإداري محمي بـ `users.manage` (super_admin) فلا يصلح للمالك المدرّس. نقطة جديدة مقيّدة:
```
GET /api/v1/catalog/courses/{course}/instructors?q=...   (api.catalog.courses.instructors.search)
```
- **التخويل:** `abort_unless($request->user()->can('manageMembers', $course), 403)` (مرتبط بمقرر يملكه — لا بحث مفتوح عن المستخدمين).
- **التحقّق:** `q` نص مطلوب (≥ حرفين)؛ نتائج محدودة (مثلاً 10).
- **الاستعلام:** مستخدمون لهم دور `instructor`، مطابقة `name`/`email` (بحث فقط، لا كشف بريد في الخرج)، **استثناء** المالك والأعضاء الحاليين.
- **الاستجابة 200:**
```jsonc
{ "data": [ { "id": 42, "name": "سارة المطيري" } ] }   // id/name فقط — PDPL
```
> القرار: نقطة بحث مقيّدة بـ `manageMembers` على مقرر بعينه أبسط وأأمن من فتح `users.index` للمدرّسين. تكشف الحد الأدنى (id/name).

### 3.هـ — `mine` يشمل مقررات co-author (تعديل قائم)

`CourseController::mine` (السطر 35-42) يضيف مقررات العضوية:
```php
->unless(
    $request->user()->can(Permission::ReviewCourses->value),
    fn ($q) => $q->where(fn ($w) => $w
        ->where('instructor_id', $request->user()->getKey())
        ->orWhereHas('members', fn ($m) => $m->where('users.id', $request->user()->getKey()))),
)
```
> بدونه لا يرى co-author المقرر في `/studio` فلا يصل لتحريره. المراجع يبقى يرى الكل.

---

## 4. الواجهة (Next.js — RTL)

### 4.أ — قسم «فريق التأليف» في الاستوديو (للمالك فقط)
- مكوّن جديد `frontend/src/components/studio/AuthoringTeam.tsx`، يُركَّب في `studio/[slug]/page.tsx` ضمن العمود الجانبي للمنهج (بجوار `PrerequisitesManager`) **أو** تبويب خامس `team`.
- **شرط العرض:** يظهر **للمالك فقط** — يُحدَّد بمقارنة `course.instructor.id === me.id` (الواجهة تقرأ `me` من `/auth/me`؛ co-author لا يرى القسم). الخادم هو الحارس النهائي (403 على `manageMembers`)؛ إخفاء الواجهة تجميلي.
- **الوظائف:**
  - **بحث/اختيار مدرّس:** حقل بحث ⇐ `GET /catalog/courses/{slug}/instructors?q=` ⇐ اختيار ⇐ `POST .../members { user_id }`.
  - **عرض الأعضاء:** `GET .../members` — قائمة (الاسم + شارة «مؤلّف مشارك»).
  - **إزالة:** زر بجوار كل عضو ⇐ `DELETE .../members/{user}` + تأكيد.
- **حالات:** قائمة فارغة («لا مؤلّفين مشاركين بعد»)، خطأ 422 (ليس مدرّساً/المالك نفسه) يُعرض رسالة الخادم العربية.

### 4.ب — أثر على بقية الاستوديو
- co-author يفتح `/studio/{slug}` ويحرّر كل التبويبات القائمة (المنهج/التقييمات/الدرجات/التواصل) **دون أي تغيير** في تلك المكوّنات — الصلاحية تأتي من الخادم. التغيير الوحيد المرئي له: لا يرى قسم «فريق التأليف»، ولا أزرار «إرسال للمراجعة»/«حذف» (مخفيّة عبر شرط المالك؛ والخادم يردّ 403 احتياطاً).
- قائمة `/studio` (تستهلك `/catalog/mine`) تُظهر له مقررات هو co-author فيها تلقائياً (تعديل §3.هـ) — لا تغيير واجهة لازم.

---

## 5. الأمان ومنع تصعيد الصلاحيات (المعيار الأول)

| ناقل التصعيد | المنع |
|---|---|
| co-author يضيف co-author آخر | إدارة الأعضاء بـ `manageMembers` = المالك فقط — **لا** `update`. 403. |
| co-author يحذف المقرر | `delete` لم تتغيّر (المالك + `courses.manage`). 403 على `destroy`. |
| co-author ينشر/يرسل للمراجعة | `submit`/`review` لم تتغيّرا. 403. |
| المراجع `courses.review` يدير الأعضاء | `manageMembers` لا تمنح للمراجع (تشترط ملكية + `courses.manage`). 403. مقصود: إدارة الفريق قرار ملكية. |
| إضافة غير مدرّس عضواً | فحص الدور في `store` (422). |
| المالك يضيف نفسه | فحص `instructor_id` (422). |
| تكرار العضو | unique DB + فحص idempotent (200). |
| عزل المقررات | كل نقطة تمرّ بـ `can('manageMembers', $course)`/`can('update', $course)` على `$course` المحدّد — لا أثر عابر للمقررات. |
| كشف بيانات حسّاسة (PDPL) | قائمة الأعضاء وبحث المدرّسين: id/name فقط، لا بريد/هاتف. |
| بقاء صلاحية بعد الإزالة | الفحوص حيّة على `course_members` — الإزالة تُسقط `update`/`isStaffFor` فوراً (لا كاش). |

---

## 6. معايير القبول (قابلة للاختبار)

**التحرير (عبر `update`):**
1. co-author يُنشئ/يعدّل/يحذف/يرتّب الأقسام والدروس (200/201/204).
2. co-author يُنشئ أسئلة/اختبارات/واجبات ويضبط درجة النجاح ويرفع الغلاف ويضيف متطلّبات (E1) ويجدول الظهور (E2).
3. co-author يفتح **مسودّة** المقرر في الاستوديو (`show` على draft) — 200.

**الطاقم (عبر `isStaffFor`):**
4. co-author يرى gradebook المقرر ومحاولات/تسليمات الطلاب (200) — مستخدم بلا علاقة ⇒ 403.
5. co-author ينشر إعلاناً ويرسل بريداً جماعياً (201/202) ويميّز إجابة منتدى.
6. co-author يلتحق بمقرره متجاوزاً المتطلّبات (E1) ويرى الأقسام المجدولة (E2).

**عدم التصعيد (الأهمّ):**
7. co-author يحاول **حذف** المقرر ⇒ 403.
8. co-author يحاول **إرسال للمراجعة/نشر** ⇒ 403.
9. co-author يحاول `GET/POST/DELETE .../members` ⇒ 403 (لا يدير الأعضاء).
10. المراجع `courses.review` يحاول `POST .../members` ⇒ 403 (ليس مالكاً).

**إدارة المالك:**
11. المالك يضيف مدرّساً عضواً (201) ويزيله (204)؛ القائمة تعكس التغيير.
12. إضافة **غير مدرّس** ⇒ 422؛ إضافة **المالك نفسه** ⇒ 422؛ إضافة عضو **مكرّر** ⇒ 200 idempotent.

**التكامل:**
13. `/catalog/mine` لمستخدم co-author **يشمل** مقررات عضويته؛ ولا يشمل مقررات لا علاقة له بها.
14. بعد إزالة co-author: محاولاته على `update`/gradebook ⇒ 403 فوراً.
15. عزل المقررات: co-author في المقرر A لا يحرّر/لا يرى طاقم المقرر B ⇒ 403.

---

## 7. تقسيم المسؤوليات

| الجهة | المهام |
|---|---|
| **backend-dev** | هجرة `course_members` (§1) · `Course::members()` + `Course::hasCoAuthor()` (§2.أ) · **تعديل** `CoursePolicy::update` (سطر واحد §2.ب) + `CoursePolicy::view` (§2.ج) + **فعل جديد** `manageMembers` (§2.هـ) · **تعديل** `CourseAccess::isStaffFor` (سطر واحد §2.د) · متحكّم `CourseMemberController` (index/store/destroy §3.أ-ج) + بحث المدرّسين (§3.د) + أسطر المسارات `api.catalog.courses.members.*` · **تعديل** `CourseController::mine` (§3.هـ). **لا** يلمس أيّاً من مسارات التحرير المسرودة في §2.ب (ترث تلقائياً). |
| **frontend-dev** | `AuthoringTeam.tsx` (§4.أ) + تركيبه في `studio/[slug]/page.tsx` بشرط المالك · أنواع `CourseMember`/تعديل أنواع API · إخفاء أزرار الحذف/الإرسال عن غير المالك (الخادم هو الحارس). **لا** تغيير على مكوّنات المنهج/التقييمات/الدرجات/التواصل. |
| **architect (هذا العقد)** | تثبيت توسعة نقطتي `update`/`isStaffFor` بدقّة + فصل `manageMembers` لمنع التصعيد + توثيق كل نقطة تأثّر. |

> **حدّ الدفعة:** v1 دور واحد `co_author` = نظير المالك في التحرير والطاقم على هذا المقرر، دون حذف/إدارة أعضاء/نشر. أدوار جزئية (مثل «مساعد تصحيح فقط») أو سجلّ تدقيق للعضوية أو إشعار العضو عند الإضافة — **خارج v1** (عمود `role` يتيح التوسّع لاحقاً بلا هجرة).
