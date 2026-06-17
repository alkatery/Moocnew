# عقد الدفعة C2 — مراجعة/تصحيح تسليمات الواجبات من واجهة المعلّم

> **الحالة:** نهائي للتنفيذ — يسلّمه `frontend-dev` (رئيسي) و`backend-dev` (بند صغير محدّد) حرفياً.
> **المرجع:** `docs/implementation-plan.md §4 C2` · `docs/PRD-MOOC-Platform-v2.md §5.هـ` · `docs/architecture/CONTEXTS.md` (Assessment + Enrollment).
> **الهدف:** واجهة معلّم لعرض/تصحيح تسليمات الواجبات تستهلك API القائم (`index`/`grade`) + إعادة درجة (تجاوز درجة سابقة). القبول: مسار تصحيح كامل من الواجهة مع تدقيق (`activity_logs`).

---

## 0. حقيقة مرصودة بعد قراءة الكود — الخلفية شبه جاهزة

| البند | الحالة المرصودة | المصدر المقروء |
|------|------------------|----------------|
| قائمة واجبات المقرر | جاهزة `GET /assessment/courses/{course}/assignments` (slug)، `AssignmentResource` تُصدّر `id/course_id/section_id/title/description/due_at/points/rubric/weight` | `AssignmentController` + `AssignmentResource.php` |
| قائمة التسليمات | جاهزة `GET /assessment/assignments/{assignment}/submissions`، محميّة `can('update', $assignment->course)`، مُرقّمة 20، مرتّبة `latest('submitted_at')` | `AssignmentSubmissionController::index` |
| التصحيح | جاهز `POST /assessment/submissions/{submission}/grade`: `rubric_scores` (تُجمَع وتُقصّ عند `points`) **أو** `grade` مباشر + `feedback`؛ يكتب `graded_by/graded_at`، يُدقّق `assignment.graded`، إعادة التصحيح = نفس النقطة (تستبدل) | `AssignmentSubmissionController::grade` + `GradeSubmissionRequest` |
| التخويل (عرض + تصحيح) | `index` و`grade` كلاهما `can('update', $course)` = طاقم المقرر (مالك / `courses.review` / super_admin عبر `Gate::before`) | المتحكّم + `GradeSubmissionRequest::authorize` |
| **اسم الطالب** | **غير مُصدَّر** — `AssignmentSubmissionResource` تُصدّر `user_id` فقط، لا `student.name` | `AssignmentSubmissionResource.php` |
| **تنزيل ملف التسليم** | **لا آلية** — تُصدَّر `has_file` (منطقية) فقط؛ `file_path` على قرص **`media`** المحمي بلا مسار تسليم | `AssignmentSubmissionResource.php` + `config/filesystems.php` |

> **الخلاصة:** التصحيح نفسه لا يحتاج أي تغيير خلفي. لكنّ **واجهة مراجعة قابلة للاستخدام** تتطلّب بندَين صغيرين على الخلفية (اسم الطالب + رابط تنزيل موقّع) — مفصّلان في **§2 (backend-dev)** مع تبريرهما. لا هجرات، لا تغيير على منطق `grade`.

---

## 1. قرارات معمارية مثبتة

| البند | القرار | المصدر/التبرير |
|------|--------|----------------|
| ربط `{course}` | بالـ **slug** (binding ضمني) | `Course::getRouteKeyName() === 'slug'` |
| ربط `{assignment}`/`{submission}` | بالـ **id** (binding ضمني) | المسارات الحالية في `routes/api.php` |
| التخويل | بلا تغيير: `can('update', $course)` على `index`+`grade` يكفي (PDPL: المعلّم يرى طلاب مقرره فقط) | المتحكّم الحالي |
| الترقيم | `index` يُرقّم 20 (ثابت من الخادم)؛ الواجهة تستهلك `meta` للتنقّل | `index()->paginate(20)` |
| دلالة `rubric_scores` | مفتاح المصفوفة = **`criterion.id`** (سلسلة)، القيمة = نقاط ممنوحة (عدد صحيح ≥ 0)، تُقصّ كلٌّ عند `max_points` وتُجمَع وتُقصّ عند `points` | حلقة `grade()` السطور 79–90 |
| النقود | لا تنطبق (نقاط/درجات صحيحة 0..`points`) | — |
| الهجرات | **لا هجرة جديدة** — إعادة استخدام `assignments`/`assignment_submissions` كما هي | — |

---

## 2. بند خلفي صغير ومحدّد (backend-dev) — مبرَّر، لا منطق جديد للتصحيح

الواجهة لا تكتمل (عرض «من سلّم؟» + فتح الملف المُسلَّم) دون التاليَين. كلاهما إضافة عرض فقط، لا تمسّ منطق `grade`/`store`:

### 2.أ — كشف اسم الطالب في `AssignmentSubmissionResource`
- **التبرير:** المعلّم يصحّح تسليمات مجهولة الهوية حالياً (يرى `user_id` فقط). C1 (Gradebook) سبق وكشف `name` لطاقم المقرر بنفس مبرّر PDPL.
- **التغيير:** إضافة مفتاح `student` إلى `AssignmentSubmissionResource::toArray`:
  ```php
  'student' => [
      'id'   => $this->user_id,
      'name' => $this->whenLoaded('user', fn () => $this->user->name),
  ],
  ```
  مع eager-load في `index`: `$assignment->submissions()->with('user:id,name')->latest('submitted_at')->paginate(20)`.
- **PDPL:** يُكشف **الاسم فقط** (لا بريد/هاتف). الكشف مشروط بأن المستهلك طاقم هذا المقرر (التخويل قائم) وأن التسليم لهذا المقرر. لا تسرّب طلاب مقرر آخر.
- **توافق رجوعي:** `user_id` يبقى كما هو؛ `student` إضافة لا تكسر مستهلكاً.

### 2.ب — رابط تنزيل موقّع لملف التسليم
- **التبرير:** `file_path` على قرص **`media`** المحمي (غير عمومي)، بلا أي مسار تسليم اليوم — المعلّم لا يستطيع فتح ملف الطالب إطلاقاً. هذا حاجز للقبول («مسار تصحيح كامل»).
- **النمط المُعاد استخدامه (حرفياً):** نفس `MediaStreamController` للفيديو — قرص `media` + مسار `signed` قصير العمر، التوقيع هو الصلاحية (راجع `routes/api.php` السطر ~299 و`MediaStreamController.php`).
- **المتحكّم الجديد:** `App\Http\Controllers\Api\V1\Assessment\SubmissionFileController` (`__invoke(Request, AssignmentSubmission): StreamedResponse`):
  - `abort_unless($request->user()->can('update', $submission->assignment->course), 403);` (طاقم المقرر فقط — لا يكتفي بالتوقيع لأنّ الرابط يُولَّد ضمن استجابة محميّة).
  - `abort_if($submission->file_path === null, 404);`
  - `$disk = Storage::disk('media'); abort_unless($disk->exists($submission->file_path), 404); return $disk->response($submission->file_path);` (أو `download(...)` لإجبار التنزيل).
- **المسار:** داخل مجموعة `auth:sanctum` + بادئة `assessment`:
  ```php
  Route::get('submissions/{submission}/file', SubmissionFileController::class)->name('submissions.file');
  ```
  (التخويل عبر `can('update', course)` داخل المتحكّم؛ لا `signed` لأنّ المصادقة محميّة بـ Sanctum + Policy — أبسط من توليد توقيع وأكثر اتّساقاً مع بقية نقاط Assessment.)
- **الكشف في الـ Resource:** يستبدل `has_file` بحقلين (أو يُبقي `has_file` ويضيف `file_url`):
  ```php
  'has_file' => $this->file_path !== null,
  'file_url' => $this->when(
      $this->file_path !== null,
      fn () => route('api.assessment.submissions.file', $submission), // رابط مطلق محمي
  ),
  ```
  > بديل أبسط إن فُضِّل عدم إضافة متحكّم: إبقاء `file_url = null` وعرض «ملف مُرفق (يُعرض في إصدار لاحق)» — لكن هذا يكسر معيار «مسار تصحيح كامل»، لذا **المسار الموقّع/المحمي هو الموصى به**.

> **حدود الموديول:** المتحكّم الجديد في طبقة Http (Assessment)، يقرأ عبر نموذج `AssignmentSubmission` (Infrastructure) ويفوّض التسليم لقرص `media`. Domain لا يعرف Infrastructure. لا خدمة جديدة.

### 2.ج — لا تغيير على `grade`/`store`/`GradeSubmissionRequest`
منطق التصحيح، التحقّق (`grade` 0..`points` أو `rubric_scores` مصفوفة أعداد ≥ 0)، التدقيق (`assignment.graded`)، وإعادة الاحتساب (`CourseCompletionService`) — **كما هي**.

---

## 3. عقود الاستهلاك (كما هي فعلاً في الكود — لا اختراع)

### 3.أ — قائمة واجبات المقرر
```
GET /api/v1/assessment/courses/{slug}/assignments        (auth, طاقم المقرر)
```
الاستجابة `{ "data": AssignmentItem[] }` حيث كل عنصر:
```jsonc
{ "id": 5, "course_id": 12, "section_id": null, "title": "المشروع",
  "description": "…|null", "due_at": "ISO8601|null", "points": 100,
  "rubric": [ { "id": "c1", "title": "وضوح الفكرة", "max_points": 40 } ] /* أو null */,
  "weight": 3 }
```
> **مهم:** `rubric` (المعايير + `max_points`) يأتي من **الواجب** لا من التسليم. الواجهة تقرأ `rubric` من هذا المسار لبناء نموذج التصحيح بالمعايير.

### 3.ب — قائمة تسليمات واجب (مُرقّمة 20)
```
GET /api/v1/assessment/assignments/{assignment}/submissions        (auth, طاقم المقرر، 403 لغيره)
```
الاستجابة (Resource collection مع `meta`/`links` الترقيم القياسي) — كل عنصر **بعد بند §2**:
```jsonc
{
  "id": 31,
  "assignment_id": 5,
  "user_id": 41,
  "student": { "id": 41, "name": "سارة المالكي" },   // ← يُضاف في §2.أ
  "content": "نص إجابة الطالب…|null",
  "has_file": true,
  "file_url": "https://…/api/v1/assessment/submissions/31/file",  // ← §2.ب، null إن لا ملف
  "grade": 85,                                        // null إن لم يُصحَّح
  "rubric_scores": { "c1": 35, "c2": 50 },            // null إن صُحِّح بدرجة مباشرة أو لم يُصحَّح
  "feedback": "عمل جيد…|null",
  "submitted_at": "ISO8601",
  "graded_at": "ISO8601|null"                         // null ⇒ «غير مُصحَّح»
}
```
- **حالة التصحيح:** `graded_at !== null` ⇒ «مُصحَّح»؛ وإلا «غير مُصحَّح» (مطابق `AssignmentSubmission::isGraded()`).
- **`meta`:** `{ current_page, last_page, total }` (ترقيم Laravel القياسي، per_page=20).

### 3.ج — تصحيح / إعادة تصحيح (نفس النقطة)
```
POST /api/v1/assessment/submissions/{submission}/grade        (auth, طاقم المقرر، 403 لغيره)
```
الطلب — **أحد** الشكلين (متبادلان حصرياً عبر `required_without`):
```jsonc
// (أ) درجة مباشرة:
{ "grade": 85, "feedback": "…|null" }              // grade: integer 0..points

// (ب) درجات معايير (يجمعها الخادم ويقصّها عند points):
{ "rubric_scores": { "c1": 35, "c2": 50 }, "feedback": "…|null" }
//   المفتاح = criterion.id من rubric الواجب؛ القيمة integer ≥ 0 (تُقصّ عند max_points آلياً).
```
- قواعد التحقّق الفعلية (`GradeSubmissionRequest`): `grade` ⇒ `required_without:rubric_scores|integer|min:0|max:{points}`؛ `rubric_scores` ⇒ `required_without:grade|array`، عناصرها `integer|min:0`؛ `feedback` ⇒ `nullable|string`.
- الاستجابة `200`: `{ "data": AssignmentSubmissionResource }` (بنفس شكل §3.ب، محدّثاً بـ `grade`/`rubric_scores`/`feedback`/`graded_at`).
- **إعادة التصحيح:** تُرسَل لنفس النقطة فتستبدل القيم السابقة (لا حذف/إنشاء). تُسجِّل `assignment.graded` في `activity_logs` في كل مرة.
- **عند rubric:** الخادم يُرجع `rubric_scores` **مقصوصة** (`min(awarded, max_points)`) و`grade` = `min(مجموع، points)` — الواجهة تعرض المُرجَع لا المُدخَل.

### رموز الأخطاء (موحّدة)
| الحالة | الرمز |
|-------|------|
| غير مصادَق | `401` |
| ليس طاقم المقرر (عرض/تصحيح/تنزيل) | `403` |
| الواجب/التسليم/الملف غير موجود | `404` |
| `grade` خارج `0..points` أو لا `grade` ولا `rubric_scores` أو نوع خاطئ | `422` |

### حدود المعدّل
- ترث rate limiter مجموعة `auth:sanctum` (لا حد خاص). التصحيح فعل آمن متكرّر (idempotent بحكم `update`).

---

## 4. حدود الموديول (Bounded Contexts)

- **Assessment (يملك المنطق):** التصحيح/المعايير/التسليمات كلها داخل `app/Contexts/Assessment` ونماذجها (`Assignment`/`AssignmentSubmission`). المتحكّم الجديد لتسليم الملف (§2.ب) في طبقة Http (Assessment). لا منطق درجات في الواجهة — الواجهة تعرض/ترسل فقط.
- **Enrollment (التخويل/الالتحاق):** `can('update', $course)` عبر `CoursePolicy` (طاقم المقرر). الالتحاق لا يعتمد على الدفع (راية `payments.enabled` لا تمسّ هذه الدفعة).
- **Domain لا يعرف Infrastructure:** التسليم يقرأ عبر نماذج Infrastructure الموجودة؛ لا تسرّب طبقات.

---

## 5. الواجهة (frontend-dev — رئيسي)

### المكان
**ضمن تبويب «التقييمات والدرجات»** (`tabpanel-assessments`) في `frontend/src/components/studio/AssessmentsPanel.tsx`:
- في بطاقة «الواجبات» الحالية (السطور ~91–109)، كل عنصر واجب يكتسب زرّاً **«مراجعة التسليمات»** يفتح مكوّناً جديداً `SubmissionReview` لذلك الواجب (inline توسّعاً أسفل العنصر، أو modal — قرار frontend-dev؛ inline يكفي ويبقي نمط اللوحة).
- **مكوّن جديد:** `frontend/src/components/studio/SubmissionReview.tsx` — `props: { assignment: AssignmentItem }`، يُحمَّل كسلًا عند الفتح فقط.

> لا تبويب جديد على مستوى الاستوديو (التصحيح جزء من «التقييمات»، والـ Gradebook C1 يبقى تبويباً مستقلاً للقراءة فقط).

### التدفّق (مطابق الهدف)
اختيار واجب ← فتح «مراجعة التسليمات» ← **قائمة تسليمات** (اسم الطالب + حالة «مُصحَّح/غير مُصحَّح» + تاريخ التسليم + الدرجة إن وُجدت) ← فتح تسليم ← عرض (`content` + رابط تنزيل الملف إن `has_file`) ← **تصحيح**: إمّا حقول معايير (إن `assignment.rubric` غير فارغ) أو حقل درجة مباشرة، + تغذية راجعة ← حفظ (`POST …/grade`) ← تحديث الحالة في القائمة دون إعادة تحميل كامل.

### الأنواع (frontend/src/lib/types.ts — إضافة، لا كسر)
- **توسعة `AssignmentItem`** بإضافة `rubric` (موجود في الـ API لكن غائب عن النوع الحالي):
  ```ts
  export interface RubricCriterion { id: string; title: string; max_points: number }
  // داخل AssignmentItem أضف:
  //   rubric: RubricCriterion[] | null;
  ```
- **نوع جديد للتسليم:**
  ```ts
  export interface AssignmentSubmission {
    id: number;
    assignment_id: number;
    user_id: number;
    student: { id: number; name: string };
    content: string | null;
    has_file: boolean;
    file_url: string | null;
    grade: number | null;
    rubric_scores: Record<string, number> | null;   // مفتاحه = criterion.id
    feedback: string | null;
    submitted_at: string;
    graded_at: string | null;                        // null ⇒ غير مُصحَّح
  }
  ```

### نموذج التصحيح
- **إن `assignment.rubric` غير فارغ:** حقل عددي لكل معيار (`min=0`, `max=criterion.max_points`, `dir="ltr"`) معنون بـ `criterion.title`، مع عرض مجموع مباشر تحته («المجموع: X من points»). يُرسَل `rubric_scores: { [id]: number }`. (يُهيّأ من `rubric_scores` الحالية عند إعادة التصحيح.)
- **وإلا:** حقل درجة واحد `grade` (`min=0`, `max=assignment.points`, `dir="ltr"`). يُرسَل `grade`.
- في الحالتين: حقل `feedback` (textarea، اختياري). أزرار «حفظ التصحيح» / «إلغاء».
- بعد الحفظ: تُحدَّث بطاقة التسليم بالقيم المُرجَعة من الـ API (الدرجة المقصوصة + `graded_at`)، وتنقلب الحالة إلى «مُصحَّح».

### الحالات
- **تحميل:** `common.loading`.
- **خطأ (403/شبكة):** `ErrorMsg` (`role="alert"`) — رسالة `review.error` («تعذّر تحميل التسليمات — تأكّد أنك من طاقم هذا المقرر»).
- **فراغ (لا تسليمات):** «لا تسليمات لهذا الواجب بعد» (`review.empty`).
- **إعادة تصحيح (تجاوز درجة سابقة):** عند فتح تسليم `graded_at !== null`، عرض شارة «مُصحَّح» + الدرجة الحالية، وعند الحفظ تأكيد `window.confirm` («سيُستبدَل التصحيح السابق (الدرجة X). متابعة؟») قبل الإرسال.
- **نجاح:** `SuccessMsg` (`role="status"`) «حُفظ التصحيح ✓».

### a11y / RTL (تنسيق compliance)
- كل حقل معيار/درجة/تغذية **معنون** (`<label htmlFor>` أو `aria-label`)؛ لا حقول بلا اسم.
- الأخطاء عبر `ErrorMsg`/`role="alert"`، النجاح عبر `role="status"` (نمط `AssessmentsPanel` الحالي G5).
- الحفاظ على نمط تبويبات WAI-ARIA القائم في صفحة الاستوديو دون كسره (المكوّن داخل `tabpanel-assessments`).
- روابط التنزيل: `<a href={file_url}>` يفتح رابطاً **محمياً** (Sanctum + Policy) — لا تسريب مسار التخزين المباشر؛ تباين AA على الأزرار/الشارات.

### مفاتيح i18n جديدة (frontend/src/i18n/dictionary.ts — ar + en)
`review.open` («مراجعة التسليمات»)، `review.empty` («لا تسليمات لهذا الواجب بعد»)، `review.error` («تعذّر تحميل التسليمات — تأكّد أنك من طاقم هذا المقرر»)، `review.graded` («مُصحَّح»)، `review.ungraded` («غير مُصحَّح»)، `review.grade` («الدرجة»)، `review.feedback` («تغذية راجعة»)، `review.save` («حفظ التصحيح»)، `review.saved` («حُفظ التصحيح ✓»)، `review.regradeConfirm` («سيُستبدَل التصحيح السابق. متابعة؟»)، `review.downloadFile` («تنزيل الملف المُرفق»)، `review.total` («المجموع»).

---

## 6. معايير القبول (قابلة للاختبار)

### خلفي (qa-tester — تأكيد التغطية القائمة + بنود §2)
> التغطية الحالية في `tests/Feature/Assessment/AssignmentTest.php` تغطّي: إنشاء واجب، تسليم متعلّم + تصحيح معلّم (`grade=85`، تدقيق `assignment.graded`)، منع غير الملتحق من التسليم، منع المتعلّم من التصحيح، اشتراط محتوى/ملف. **هذه كافية لـ `grade`/`store` ولا تتطلّب تعديلاً.**
1. **عرض التسليمات لطاقم المقرر:** مالك المقرر → `200`، قائمة مُرقّمة 20؛ مستخدم عشوائي/متعلّم → `403`. (موجود ضمنياً عبر `index`؛ أضِف اختباراً صريحاً لـ `index` إن غاب.)
2. **اسم الطالب مكشوف (§2.أ):** استجابة `index`/`grade` تتضمّن `data.*.student.name` ولا تتضمّن بريد/هاتف.
3. **تنزيل الملف (§2.ب):** طاقم المقرر يفتح `GET …/submissions/{id}/file` → `200` (محتوى الملف)؛ مستخدم آخر → `403`؛ تسليم بلا ملف → `404`. (`Storage::fake('media')`.)
4. **تصحيح بالمعايير:** إرسال `rubric_scores` بقيم تتجاوز `max_points` → الاستجابة تُرجع قيماً مقصوصة و`grade = min(مجموع, points)` (مطابق منطق `grade()`).
5. **إعادة التصحيح تستبدل:** تصحيح ثانٍ لنفس التسليم بدرجة مختلفة → `data.grade` الجديدة، و`activity_logs` فيه `assignment.graded` مرّتين.

### أمامي (frontend-dev، تأكيد qa-tester إن توفّر إطار)
- زر «مراجعة التسليمات» يفتح القائمة؛ تظهر أسماء الطلاب وحالات «مُصحَّح/غير مُصحَّح» صحيحة.
- نموذج التصحيح يتكيّف: حقول معايير عند وجود `rubric`، حقل درجة واحد عند غيابه؛ القيم الأولية من `rubric_scores`/`grade` عند إعادة التصحيح.
- الحفظ يستدعي `POST …/grade` بالجسم الصحيح، يعرض الدرجة المقصوصة المُرجَعة، ويقلب الحالة إلى «مُصحَّح».
- تأكيد إعادة التصحيح يظهر عند تجاوز درجة سابقة. حالات تحميل/خطأ/فراغ تظهر صحيحة. رابط التنزيل يعمل ومحمي.
- RTL سليم، الحقول معنونة، قابلية وصول لوحية محفوظة داخل التبويب.

---

## 7. تقسيم المسؤوليات

| الدور | المهام |
|------|--------|
| **frontend-dev (رئيسي)** | مكوّن `SubmissionReview.tsx` (قائمة تسليمات + عرض تسليم + نموذج تصحيح معايير/درجة + تغذية + حالات + تأكيد إعادة تصحيح + رابط تنزيل) + دمجه في `AssessmentsPanel.tsx` (زر «مراجعة التسليمات» على كل واجب) + الأنواع في `types.ts` (`RubricCriterion`، توسعة `AssignmentItem.rubric`، `AssignmentSubmission`) + مفاتيح i18n (ar/en). إعادة استخدام أنماط `AssessmentsPanel`/`InstructorGradebook` (StatusMessage، RTL، a11y). |
| **backend-dev (بند صغير)** | §2.أ كشف `student.name` في `AssignmentSubmissionResource` + eager-load `user:id,name` في `index`. §2.ب متحكّم `SubmissionFileController` + مسار `api.assessment.submissions.file` + كشف `file_url` (تنزيل محمي بـ `can('update', course)`). **لا** تغيير على `grade`/`store`/`GradeSubmissionRequest`، **لا** هجرات. |
| **qa-tester** | تأكيد كفاية تغطية `index`/`grade` القائمة في `tests/Feature/Assessment/AssignmentTest.php`؛ إضافة اختبارات §6 الخلفية (عرض 200/403، اسم الطالب مكشوف، تنزيل الملف 200/403/404، قصّ المعايير، إعادة التصحيح تستبدل + تدقيق مزدوج)؛ اختبار أمامي/تكامل خفيف للنموذج إن توفّر إطار. |
| **compliance** | PDPL: المعلّم يرى أسماء طلاب مقرره وتسليماتهم فقط (لا مقرر آخر، لا بريد/هاتف)؛ رابط التنزيل محمي لا يسرّب مسار التخزين؛ a11y (حقول معنونة، `role=alert/status`، تباين AA، تبويبات WAI-ARIA غير مكسورة)؛ RTL. |

---

## 8. ما هو خارج النطاق (لا هندسة زائدة)
- لا تعديل على منطق احتساب الدرجة الكلية/الإكمال (يبقى في `CourseGradeService`/`CourseCompletionService` المستدعى من `grade`).
- لا تصحيح جماعي/مجمّع (تسليم واحد في كل مرة).
- لا تعليقات سطرية (inline annotations) على الملف، ولا معاينة داخل المتصفّح (تنزيل/فتح الرابط يكفي القبول).
- لا إشعار للطالب كجزء من C2 (الإشعارات خارج هذه الدفعة).
- لا تغيير على راية `payments.enabled` (لا علاقة للتصحيح بالتجارة).
