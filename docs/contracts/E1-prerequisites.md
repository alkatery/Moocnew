# عقد الدفعة E1 — المتطلّبات السابقة (Prerequisites)

> **الحالة:** نهائي للتنفيذ — يسلّمه `backend-dev` و`frontend-dev` حرفياً.
> **المرجع:** `docs/implementation-plan.md §6 E1` · `docs/feature-matrix.csv` (السطر «المتطلّبات السابقة» P2 = «منع الالتحاق حتى إكمال متطلّب») · `docs/PRD-MOOC-Platform-v2.md §5.ج (الالتحاق/الوصول)` · `docs/architecture/CONTEXTS.md` (Enrollment + Catalog).
> **الهدف:** قيد التحاق مشروط — لا يلتحق الطالب بمقرر حتى **يُكمل متطلّباً سابقاً** (مقرراً آخر). المؤلّف يضيف/يزيل متطلّبات لمقرره؛ المنصّة ترفض الالتحاق إن لم يُكمل المتعلّم كل المتطلّبات.
> **المبدأ الحاكم لهذه الدفعة:** **نقطة الحقيقة للرفض هي `EnrollmentService::enroll` وحدها** (المكان الوحيد الذي يُنشئ الالتحاق). علاقة many-to-many ذاتية على `courses`. **لا منطق إكمال جديد** — «الإكمال» = `enrollment.status === Completed` للمتطلّب (التعريف القائم الذي يطبّقه `CourseCompletionService` ويشمل اجتياز درجة النجاح). لا هندسة زائدة.

---

## 0. قرارات معمارية مثبتة (بعد قراءة الكود)

| البند | القرار | المصدر المقروء |
|------|--------|----------------|
| نقطة الرفض | **`EnrollmentService::enroll`** فقط — قبل `Enrollment::create`، داخل المعاملة. كل مسارات الالتحاق العادي تمرّ به (`EnrollmentController::store`). | `app/Contexts/Enrollment/Application/EnrollmentService.php:33` |
| تعريف «الإكمال» للمتطلّب | **`enrollment.status === Completed`** للمتعلّم في المقرر-المتطلّب. هذا التعريف **يشمل اجتياز درجة النجاح** أصلاً (لا نُكرّره): `CourseCompletionService` لا يضع `Completed` إلا بعد `progress=100` **و** `grade >= passing_grade`. فحصنا = `CourseAccess::hasCompletedEnrollment` المُعاد استخدامها حرفياً. | `CourseCompletionService.php:34-69` · `CourseAccess.php:34-41` |
| السياق المالك للعلاقة | **Catalog** — المتطلّب خاصيّة تأليفية للمقرر (مثل القسم/الدرس/درجة النجاح). الجدول `course_prerequisites` يربط `courses ↔ courses`. | `Course.php` · `CourseController::update` |
| السياق المالك للقيد | **Enrollment** — منطق «هل يجوز الالتحاق؟» يعيش في `EnrollmentService` (لا في Catalog). يقرأ متطلّبات المقرر (Catalog) ويستعلم إكمال المتعلّم (Enrollment). | `EnrollmentService.php` |
| نموذج العلاقة | many-to-many **ذاتية** على `Course` عبر `belongsToMany(Course::class, 'course_prerequisites', 'course_id', 'prerequisite_course_id')`. | قرار العقد |
| منع الدائرية (v1) | **منع التطابق الذاتي فقط**: مقرر لا يكون متطلّب نفسه (`course_id !== prerequisite_course_id`) — تحقّق بسيط عند الإضافة (422). **سلاسل التبعية العميقة (A→B→A) خارج v1** (انظر §7 للتبرير). | قرار العقد |
| استثناء الطاقم | **نعم** — المالك/المراجع/الأدمن (`CourseAccess::isStaffFor`) يلتحق دون فحص المتطلّبات (لمعاينة/اختبار مقرره). المتعلّم العادي يخضع للقيد. | `CourseAccess.php:43-47` |
| الوضع المجاني/المدفوع | القيد يُفحص **قبل** قرار `grantsImmediateAccess` — يسري على الوضعين بالتساوي (الالتحاق لا يعتمد على الدفع، لكن المتطلّب يحجب إنشاء الالتحاق أصلاً). راية `payments.enabled` لا تمسّ هذا المنطق. | `EnrollmentService.php:46-54,122-129` |
| الالتحاق القائم (idempotent) | إن كان للمتعلّم التحاق سابق (أي حالة) → يُعاد كما هو **دون فحص المتطلّبات** (السطر 42-44). الفحص فقط عند **إنشاء جديد**. | `EnrollmentService.php:36-44` |
| الالتحاق عبر مسار/كود/أمر | `activateForOrder` (Commerce) و`EnrollmentCodeService` و`PathEnrollmentController` — **خارج نطاق E1** (انظر §7). القيد على مسار الالتحاق الطوعي العام فقط. | `EnrollmentService.php:78` · `EnrollmentCodeService.php` |
| عرض المتطلّبات للواجهة | تُضاف **قائمة ثابتة** (id/title/slug — بلا حالة لكل مستخدم) إلى `CourseResource`. الجلب العام `GET /catalog/courses/{slug}` **مجهول** (لا توكن) فلا يمكنه حمل «مكتمل/لا» لكل متعلّم. | `CourseController::show` · `catalog/[slug]/page.tsx:43` (fetch بلا Authorization) |
| معرفة المتعلّم «هل أكملت المتطلّب؟» في الواجهة | من **قائمة التحاقات المتعلّم القائمة** `GET /enrollments` (تحوي `status` لكل مقرر) — تقاطُع محلي مع `course.prerequisites`. لا نقطة API جديدة لهذا. | `EnrollmentController::index` · `EnrollmentResource` |
| التأليف (إضافة/إزالة متطلّب) | نقطتان جديدتان تحت `catalog/courses/{course}/...` بتخويل `CoursePolicy::update` (المالك/المراجع) — نفس بوّابة بقية التأليف. | `routes/api.php:220` · `CoursePolicy::update` |
| النقود | لا تنطبق (لا حقول نقدية في هذه الدفعة). | — |
| soft delete | لا — الصفّ في `course_prerequisites` يُحذف فعلياً (لا قيمة تدقيقية؛ قابل لإعادة الإنشاء). تتالي الحذف عند حذف أحد المقررين. | قرار العقد |

---

## 1. عقد الـ API

### 1.أ — قراءة المتطلّبات (ضمن استجابة المقرر العامّة)

لا نقطة جديدة للقراءة. تُضاف **مصفوفة `prerequisites`** إلى `CourseResource` (مُحمَّلة عند `show` فقط — لا في القوائم لتفادي N+1):

`GET /api/v1/catalog/courses/{course}` → `200` (عبر `CourseResource`) — حقل جديد:

```jsonc
{
  "data": {
    "id": 12,
    "title": "هياكل البيانات المتقدّمة",
    "slug": "advanced-data-structures",
    // ... بقية حقول CourseResource بلا تغيير ...
    "prerequisites": [
      { "id": 7, "title": "أساسيات البرمجة", "slug": "programming-basics" },
      { "id": 9, "title": "مدخل إلى الخوارزميات", "slug": "intro-algorithms" }
    ]
  }
}
```

- **قائمة ثابتة** (id/title/slug فقط) — **بلا حالة «مكتمل» لكل مستخدم** (الجلب مجهول). الواجهة تحسب الإكمال محلياً من `GET /enrollments`.
- المتطلّبات الـ **محذوفة بـ soft-delete** أو غير المنشورة لا تُعرض (فلترة على `status = published` و`whereNull(deleted_at)`).
- مقرر بلا متطلّبات → `"prerequisites": []`.
- **منع N+1:** eager-load `prerequisites` ضمن `$course->load([...])` في `CourseController::show`.

### 1.ب — إضافة متطلّب (تأليف)

```
POST /api/v1/catalog/courses/{course}/prerequisites     (api.catalog.courses.prerequisites.store)
```

- **التخويل (إلزامي):** `abort_unless($request->user()->can('update', $course), 403)` — **نفس بوّابة `CourseController::update`** (المالك أو المراجع `courses.review`).
- **التحقّق (validation):**
  ```php
  'prerequisite_course_id' => ['required', 'integer', 'exists:courses,id'],
  ```
- **قواعد المجال (422 برسالة عربية واضحة):**
  - منع التطابق الذاتي: `prerequisite_course_id !== {course}.id` → وإلا `422` «لا يمكن أن يكون المقرر متطلّباً لنفسه.».
  - منع التكرار: زوج موجود مسبقاً → **idempotent** يعيد `200` بالقائمة الحالية (قيد `unique` طبقة ثانية).
  - المتطلّب يجب أن يكون **منشوراً** (`status = published`) → وإلا `422` «لا يمكن اشتراط مقرر غير منشور.» (متطلّب مسودّة لا معنى له للطالب).
- **السلوك:** `attach(prerequisite_course_id)` (أو `syncWithoutDetaching`) على علاقة `prerequisites`.
- **الاستجابة — `201 Created`** (أو `200` للتكرار): القائمة المحدّثة بنفس شكل عنصر §1.أ:
  ```jsonc
  { "data": [ { "id": 7, "title": "أساسيات البرمجة", "slug": "programming-basics" } ] }
  ```

### 1.ج — إزالة متطلّب (تأليف)

```
DELETE /api/v1/catalog/courses/{course}/prerequisites/{prerequisite}   (api.catalog.courses.prerequisites.destroy)
```

- **التخويل:** نفس §1.ب (`can('update', $course)`).
- `{prerequisite}` = id المقرر-المتطلّب (binding ضمني بالـ id؛ ليس slug — لتجنّب لبس مع `{course}` المرتبط بـ slug).
- **السلوك:** `detach($prerequisite)`. غير موجود في القائمة → `204` أيضاً (idempotent، لا `404`).
- **الاستجابة:** `204 No Content`.

### 1.د — الالتحاق مع قيد المتطلّب (تعديل سلوك قائم)

```
POST /api/v1/catalog/courses/{course}/enroll     (api.enrollment.enroll)   ← قائم، يتغيّر سلوكه
```

- **بلا تغيير في التوقيع.** يُضاف فحص داخل `EnrollmentService::enroll` (انظر §2) **قبل إنشاء الالتحاق**.
- **حالة الرفض — `422 Unprocessable Entity`** عندما المتعلّم (غير الطاقم) له متطلّب واحد فأكثر **لم يُكمله**:
  ```jsonc
  {
    "message": "يجب إكمال المتطلّبات السابقة قبل الالتحاق بهذا المقرر.",
    "errors": {
      "prerequisites": ["أكمل المقرر «أساسيات البرمجة» أولاً."]
    },
    "prerequisites": [
      { "id": 7, "title": "أساسيات البرمجة", "slug": "programming-basics" }
    ]
  }
  ```
  - الرمز **422** (لا 403): القيد بيانات-مدخل/حالة لا نقص صلاحية؛ يتسق مع نمط `422` للقواعد التجارية في `CourseController` ويسمح للواجهة بعرض رسالة حقل. حقل `prerequisites` الجذري يحمل **القائمة غير المكتملة فقط** (id/title/slug) لبناء روابط «أكمل المقرر X».
  - الرسالة تذكر **اسم أول متطلّب غير مكتمل** صراحةً؛ والمصفوفة تحوي كل غير المكتمل.
- **حالات القبول (لا تغيير عن اليوم):**
  - لا متطلّبات → التحاق طبيعي (`201`/`200`).
  - كل المتطلّبات مكتملة → التحاق طبيعي.
  - الفاعل من الطاقم (مالك/مراجع/أدمن للمقرر) → يتجاوز الفحص → التحاق طبيعي.
  - التحاق سابق قائم (أي حالة) → يُعاد كما هو دون فحص (idempotent القائم).
- **القبول الناجح يبقى كما هو:** `201` (جديد active/pending) أو `200` (قائم)، عبر `EnrollmentResource`.

### رموز الأخطاء (موحّدة مع المنصّة)
| الحالة | الرمز | الجسم |
|-------|------|------|
| غير مصادَق | `401` | معالج Sanctum القياسي |
| الالتحاق بمقرر غير منشور | `404` | `EnrollmentController::store:33` (قائم) |
| الالتحاق ومتطلّب غير مكتمل (متعلّم غير طاقم) | `422` | شكل §1.د مع مفتاح `errors.prerequisites` + جذر `prerequisites` |
| إضافة متطلّب بلا صلاحية تأليف | `403` | `abort(403)` |
| إضافة المقرر متطلّباً لنفسه | `422` | «لا يمكن أن يكون المقرر متطلّباً لنفسه.» |
| اشتراط مقرر غير منشور | `422` | «لا يمكن اشتراط مقرر غير منشور.» |
| `prerequisite_course_id` مفقود/غير موجود | `422` | تحقّق قياسي |

### حدود المعدّل
- **التأليف (§1.ب/§1.ج):** يرث rate limiter مجموعة `auth:sanctum` لمسارات `catalog` التأليفية (لا حدّ خاص) — مطابق `courses.update`.
- **الالتحاق (§1.د):** بلا تغيير عن الحدّ القائم لمسار `enroll`.

---

## 2. حدود الموديول (Bounded Contexts) ومنطق الرفض

### Catalog (يملك المتطلّب كخاصيّة تأليفية)
- **علاقة على `Course`** (`app/Contexts/Catalog/Infrastructure/Persistence/Course.php`):
  ```php
  // المتطلّبات السابقة لهذا المقرر (المقرر يشترط إكمالها).
  public function prerequisites(): BelongsToMany
  {
      return $this->belongsToMany(self::class, 'course_prerequisites', 'course_id', 'prerequisite_course_id');
  }
  ```
  - عند العرض العام تُفلتر بـ `wherePivot`/`where('status', Published)` (المتطلّبات غير المنشورة لا تظهر) — يتولاّها eager-load في `show`.
- **متحكّم تأليف جديد** `App\Http\Controllers\Api\V1\Catalog\CoursePrerequisiteController` (`store`/`destroy`) — التخويل عبر `can('update', $course)`، منطق `attach`/`detach` + قواعد §1.ب مباشرةً (لا خدمة مجال جديدة — بسيط كفاية كبقية التأليف). يجوز جمعه في `CourseController` إن طابق backend-dev النمط السائد.

### Enrollment (يملك قاعدة قبول الالتحاق — نقطة الحقيقة)
- **التعديل الوحيد لمنطق القبول داخل `EnrollmentService::enroll`** — يُحقن فحص بعد إرجاع الالتحاق القائم وقبل تحديد الوصول الفوري:
  - إن `$existing !== null` → يُعاد كما هو (لا فحص) — السطر 42-44 القائم.
  - يُمرَّر للخدمة سياق «هل الفاعل طاقم؟» لتجاوزه. **القرار:** يُضاف وسيط اختياري `bool $bypassPrerequisites = false` إلى `enroll(...)`، يحدّده **المتحكّم** عبر `CourseAccess::isStaffFor($user, $course)` (المتحكّم طبقة Application-facing تعرف Identity؛ الخدمة تبقى نقيّة). بديل مقبول: تمرير `CourseAccess` للخدمة. backend-dev يختار الأقل تطفّلاً مع إبقاء **الفحص نفسه في الخدمة**.
  - **خوارزمية الفحص (داخل الخدمة):**
    1. إن `$bypassPrerequisites` → تخطّى.
    2. اجلب `prerequisite_course_id`s المنشورة لـ `$course` (استعلام واحد على `course_prerequisites` ⋈ `courses`).
    3. إن لا متطلّبات → تابع.
    4. اجلب المقررات التي **أكملها** المتعلّم من تلك المجموعة: `Enrollment::where('user_id',...)->whereIn('course_id', $reqIds)->where('status', Completed)` (استعلام واحد). **«مكتمل» = `status === Completed` حصراً** (يشمل اجتياز الدرجة كما رُصد). إعادة استخدام دلالة `CourseAccess::hasCompletedEnrollment` (دفعة واحدة، لا حلقة).
    5. `$missing = $reqIds − $completedIds`. إن `$missing` غير فارغة → **ارمِ استثناء مجال** `PrerequisitesNotMet` يحمل قائمة المقررات الناقصة.
- **استثناء مجال جديد** `App\Contexts\Enrollment\Domain\PrerequisitesNotMet` (يمتدّ من استثناء النطاق القائم إن وُجد، وإلا `RuntimeException` بسيط يحمل `int[] $missingCourseIds`). يُحوَّل في طبقة HTTP إلى `422` بشكل §1.د (عبر `render()` على الاستثناء، أو التقاطه في المتحكّم وتحميل عناوين المقررات الناقصة للرسالة). **Domain لا يعرف HTTP** — التحويل في المتحكّم/الاستثناء، لا في الخدمة.
- **لا تغيير** على `activateForOrder`/`refundForOrder` (مسارات Commerce/أكواد خارج النطاق — §7).

### الطبقية (إلزامي)
- **Domain لا يعرف Infrastructure/HTTP:** الاستثناء يحمل أرقام مقررات فقط؛ تحميل العناوين للرسالة وتشكيل `422` في طبقة HTTP.
- المتحكّم يمرّر كيانات Infrastructure (`Course`, `User`) إلى Application (`EnrollmentService`, `CourseAccess`) — نفس طبقية `EnrollmentController` القائمة.

---

## 3. مخطط الهجرة `course_prerequisites`

هجرة جديدة `database/migrations/XXXX_create_course_prerequisites_table.php` — جدول ربط ذاتي على `courses`:

```php
Schema::create('course_prerequisites', function (Blueprint $table) {
    $table->id();
    $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();              // المقرر الذي يشترط
    $table->foreignId('prerequisite_course_id')->constrained('courses')->cascadeOnDelete(); // المقرر المطلوب إكماله
    $table->timestamps();
    $table->unique(['course_id', 'prerequisite_course_id']);   // لا تكرار للزوج
    $table->index('prerequisite_course_id');                   // للاستعلام العكسي (من يشترطني)
});
```

- **`unique(['course_id','prerequisite_course_id'])`:** يمنع تكرار الزوج على مستوى القاعدة (طبقة ثانية خلف `syncWithoutDetaching`)؛ ويفهرس استعلام «متطلّبات المقرر X» (البادئة `course_id`).
- **`index('prerequisite_course_id')`:** يخدم الاستعلام العكسي والتتالي.
- **`cascadeOnDelete` على المفتاحين:** حذف أيٍّ من المقررين (soft-delete على `courses` لا يُسقط الصفّ تلقائياً — لكن العرض يفلتر بـ `whereNull(deleted_at)`؛ الحذف الفعلي/الـ pruning يتولاّه التتالي). الصفّ لا يبقى معلّقاً لمقرر محذوف فعلياً.
- **منع الدائرية الذاتية:** قيد على مستوى التطبيق (§1.ب) — `course_id !== prerequisite_course_id`. (قيد `CHECK` على مستوى القاعدة اختياري لـ backend-dev إن دعمه المحرّك؛ غير إلزامي.)
- **`down()`:** `Schema::dropIfExists('course_prerequisites');` — تراجع نظيف.
- **soft delete:** لا (لا حقل `deleted_at` على جدول الربط).
- **النقود:** لا حقول نقدية.

---

## 4. الواجهة (frontend-dev)

### 4.أ — الطالب: زرّ الالتحاق يحترم المتطلّبات (`CourseDetailClient.tsx`)

- **المكان:** `frontend/src/app/catalog/[slug]/CourseDetailClient.tsx` — بطاقة الالتحاق اللاصقة (السطر ~135–168، زرّ `course.enroll`).
- **حساب الحالة (محلياً، بلا نقطة API جديدة):**
  - عند تحميل الجزيرة لمستخدم مصادَق: اجلب `GET /enrollments` مرّة → كوّن `Set<courseId>` للمقررات التي حالتها `completed`.
  - `missing = course.prerequisites.filter(p => !completedSet.has(p.id))`.
- **العرض:**
  - **`missing.length === 0`** (أو لا متطلّبات، أو زائر): الزرّ كما هو اليوم («التحق» → `POST .../enroll`).
  - **`missing.length > 0`** (متعلّم لم يُكمل): الزرّ يصبح **معطّلاً** بنص «أكمل المتطلّبات أولاً» (`prereq.blockedCta`)، مع قائمة روابط أسفله: «المتطلّبات السابقة:» ثم لكل متطلّب ناقص رابط إلى `/catalog/{p.slug}`.
  - حتى لو لم تُحسب الحالة (مثلاً تعذّر `GET /enrollments`) ويُترك الزرّ مفعّلاً، فإن **النقر يُرفض من الخادم بـ 422** ويُعرض الجسم عبر `ErrorMsg` القائم — الخادم هو خط الدفاع، والواجهة تحسين تجربة فقط.
- **معالجة `422` من الالتحاق (إلزامي — موجود جزئياً):** الكود الحالي يعرض `err.message` عبر `ErrorMsg` (السطر 42-45). يُوسَّع ليعرض، عند توفّر `err.body.prerequisites`، قائمة روابط «أكمل المقرر X» (إلى `/catalog/{slug}`) بدل/مع الرسالة النصّية. يستفيد من `ApiError` القائم في `@/lib/api`.
- **قسم عرض المتطلّبات (اختياري-مستحسن):** في عمود المنهج (يسار البطاقة)، إن `course.prerequisites.length > 0`، اعرض بطاقة «المتطلّبات السابقة» تسرد المقررات (روابط)، مع شارة «مكتمل ✓» للمكتمل و«مطلوب» لغيره (للمصادَق فقط).
- **a11y (تنسيق compliance):** الزرّ المعطّل `disabled` + `aria-disabled` ونصّ سبب مرئي (لا اعتماد على اللون)؛ قائمة المتطلّبات قائمة دلالية `<ul>`؛ التباين AA؛ الروابط أصيلة `<Link>`.

### 4.ب — المؤلّف: قسم «المتطلّبات السابقة» في الاستوديو (`studio/[slug]/page.tsx`)

- **المكان:** تبويب «المنهج» — العمود الجانبي (`lg:order-2`)، بطاقة جديدة بعد «إعدادات الدورة» (السطر ~248-257) بنفس نمط `<form className="card">`.
- **المحتوى:**
  - **قائمة المتطلّبات الحالية:** من `course.prerequisites` — لكل عنصر: عنوان المقرر + زرّ «إزالة» (`DELETE .../prerequisites/{p.id}` → `load()`).
  - **إضافة متطلّب:** حقل اختيار (select/بحث) يعرض **مقررات المؤلّف الأخرى المنشورة** عبر `GET /catalog/mine` (مُتاح للمالك/المراجع) — يُستثنى المقرر الحالي وما هو متطلّب مسبقاً. عند الاختيار → `POST .../prerequisites { prerequisite_course_id }` → `load()`.
  - **حالات:** لا متطلّبات → «لا متطلّبات سابقة — يمكن لأي طالب الالتحاق مباشرةً.»؛ خطأ الإضافة (`422` ذاتي/غير منشور) → عبر `note`/`SuccessMsg`/`ErrorMsg` القائم.
- **ملاحظة بيانات:** بعد `attach`/`detach` تُحدَّث الواجهة بـ `load()` القائم (يعيد جلب المقرر بقائمة المتطلّبات المحدّثة).
- **a11y:** `<label>` للـ select؛ زرّ الإزالة باسم واضح («إزالة المتطلّب: {title}»)؛ RTL موروث.

### 4.ج — الأنواع (`frontend/src/lib/types.ts` — إضافة، لا كسر)

```ts
// ---- E1: المتطلّبات السابقة ----
/** متطلّب سابق — عنصر مصفوفة prerequisites في CourseResource (ثابت، بلا حالة لكل مستخدم) */
export interface CoursePrerequisite {
  id: number;
  title: string;
  slug: string;
}
```
وإضافة الحقل إلى `Course`:
```ts
export interface Course {
  // ... القائم ...
  prerequisites?: CoursePrerequisite[];   // يأتي عند GET /catalog/courses/{slug} فقط
}
```

### 4.د — مفاتيح i18n (`frontend/src/i18n/dictionary.ts` — ar + en)

`prereq.title` («المتطلّبات السابقة»)، `prereq.required` («مطلوب»)، `prereq.completed` («مكتمل»)، `prereq.blockedCta` («أكمل المتطلّبات أولاً»)، `prereq.completeFirst` («أكمل المقرر «{title}» أولاً»)، `prereq.none` («لا متطلّبات سابقة — يمكن لأي طالب الالتحاق مباشرةً.»)، `prereq.add` («إضافة متطلّب»)، `prereq.remove` («إزالة المتطلّب»)، `prereq.studioHint` («اختر مقرراً منشوراً يجب على الطالب إكماله قبل الالتحاق بهذا المقرر.»).

---

## 5. معايير القبول (قابلة للاختبار)

### خلفي — Feature/Unit tests (qa-tester)
1. **رفض الالتحاق ومتطلّب غير مكتمل:** المقرر B يشترط A؛ متعلّم **غير مكتمل** لـ A → `POST .../B/enroll` → **`422`**، الجسم يحوي `errors.prerequisites` + جذر `prerequisites` فيه A، **ولا صفّ التحاق بـ B**.
2. **قبول عند إكمال المتطلّب:** المتعلّم له التحاق `Completed` في A → `POST .../B/enroll` → `201`، التحاق B `active` (أو `pending` للمدفوع خلف الراية).
3. **قبول عند غياب المتطلّبات:** B بلا متطلّبات → الالتحاق ينجح كما اليوم.
4. **«مكتمل» = Completed فقط:** متعلّم له التحاق `Active` (لا `Completed`) في A → الالتحاق بـ B **يُرفض 422** (التقدّم وحده لا يكفي؛ الإكمال يشمل الدرجة عبر `CourseCompletionService`).
5. **استثناء الطاقم:** مالك B (أو مراجع/أدمن) دون إكمال A → الالتحاق **ينجح** (`bypassPrerequisites`).
6. **idempotent للالتحاق القائم:** متعلّم له التحاق سابق بـ B (أي حالة) ثم أُضيف متطلّب A لاحقاً → إعادة `POST .../B/enroll` تعيد الالتحاق القائم **دون 422** (الفحص عند الإنشاء فقط).
7. **إضافة متطلّب — مالك فقط:** المالك `POST .../prerequisites` → `201` والقائمة تتضمّنه؛ مستخدم آخر (غير مراجع) → `403`، لا صفّ.
8. **منع الدائرية الذاتية:** `POST .../A/prerequisites { prerequisite_course_id: A.id }` → `422` «لا يمكن أن يكون المقرر متطلّباً لنفسه.».
9. **منع اشتراط غير منشور:** اشتراط مقرر `draft` → `422` «لا يمكن اشتراط مقرر غير منشور.».
10. **منع التكرار:** `attach` نفس الزوج مرّتين → الثاني `200`/`syncWithoutDetaching`، **صفّ واحد** (يثبت `unique`).
11. **الإزالة — مالك فقط:** المالك `DELETE .../prerequisites/{A}` → `204`، يختفي الصفّ؛ إزالة غير موجود → `204` (idempotent)؛ مستخدم آخر → `403`.
12. **العرض العام:** `GET /catalog/courses/{B}` يحوي `prerequisites` (A فقط، منشور)؛ متطلّب محذوف/غير منشور لا يظهر.
13. **الوضع المدفوع خلف الراية:** مع `payments.enabled=true` ومقرر B مدفوع، إكمال A → الالتحاق ينشئ `pending` (لا تغيير لمنطق الدفع)؛ عدم إكمال A → `422` **قبل** أي اعتبار دفع.
14. **منع N+1:** `show` يحمّل `prerequisites` بـ eager-load (لا استعلام لكل صفّ).

### أمامي — Vitest (qa-tester)
- متعلّم لم يُكمل متطلّباً: الزرّ معطّل بنص «أكمل المتطلّبات أولاً» + روابط للمتطلّبات الناقصة؛ متعلّم أكمل/لا متطلّبات: الزرّ فعّال؛ استجابة `422` تُعرض قائمة «أكمل المقرر X» عبر `ErrorMsg`؛ استوديو: إضافة/إزالة متطلّب تستدعي النقطتين وتحدّث القائمة؛ حالة «لا متطلّبات».

---

## 6. تقسيم المسؤوليات

| الدور | المهام |
|------|--------|
| **backend-dev** | هجرة `course_prerequisites` (FKs cascade + `unique(course,prerequisite)` + index) + علاقة `Course::prerequisites()` + متحكّم تأليف `CoursePrerequisiteController` (`store`/`destroy`، تخويل `can('update')`، قواعد §1.ب) + سطرا مسار `api.catalog.courses.prerequisites.*` + حقل `prerequisites` في `CourseResource` (مفلتر/eager-load في `show`) + **فحص المتطلّبات في `EnrollmentService::enroll`** (تجاوز للطاقم، «مكتمل»=`Completed`، استعلام دفعة واحدة) + استثناء `PrerequisitesNotMet` وتحويله إلى `422` بشكل §1.د في طبقة HTTP. إعادة استخدام `CourseAccess::isStaffFor`/`hasCompletedEnrollment` — **لا منطق إكمال جديد**. |
| **frontend-dev** | منطق الزرّ المشروط + روابط المتطلّبات الناقصة + معالجة `422` في `CourseDetailClient.tsx` (عبر `GET /enrollments`) + قسم تأليف المتطلّبات في `studio/[slug]/page.tsx` (اختيار من `GET /catalog/mine` المنشور، `attach`/`detach`، 3 حالات) + النوعان `CoursePrerequisite`/تمديد `Course` في `types.ts` + مفاتيح i18n (ar/en). إعادة استخدام `ApiError`/`ErrorMsg`/`SuccessMsg`/`PageHeader`. |
| **integrator** | يثبّت تطابق العقد طرفاً لطرف: شكل `prerequisites` في `show` = النوع؛ شكل `422` (مفتاح `errors.prerequisites` + جذر `prerequisites`) يطابق ما تقرأه الواجهة؛ مساري التأليف بأسمائهما. |
| **qa-tester** | Feature tests §5 (رفض/قبول، Completed-only، تجاوز الطاقم، idempotent الالتحاق، تأليف للمالك فقط، دائرية ذاتية، غير منشور، unique، إزالة، عرض عام، مدفوع خلف الراية، N+1) + اختبار أمامي للزرّ المشروط والاستوديو. |
| **compliance** | a11y الزرّ المعطّل (سبب مرئي + `aria-disabled`، لا اعتماد على اللون) وقائمة المتطلّبات الدلالية + RTL لقسم الاستوديو + رسائل عربية واضحة (لا تسرّب تقني) + PDPL (لا بيانات شخصية جديدة؛ جدول الربط مرجعي بحت). |
| **reviewer** | البوّابة — لا اعتماد قبل توقيعه. |

---

## 7. ما هو خارج النطاق (لا هندسة زائدة) — وقرار «امتحان الدخول»

- **امتحان الدخول (Entrance Exam) — مؤجَّل صراحةً.** التبرير: في `docs/feature-matrix.csv` هو بند **منفصل** (الأولوية **P3**، الوصف «تابع للمتطلّبات السابقة») بينما المتطلّبات السابقة **P2**. هدف E1 في الخطة والمصفوفة هو حرفياً «منع الالتحاق حتى إكمال متطلّب» — وهو متطلّب **مقرر**. امتحان الدخول يتطلّب منطق تقييم قبل الالتحاق (محاولة/تصحيح/عتبة بلا التحاق سابق) وتقاطعاً مع سياق Assessment لا يخدمه المخطط الحالي. v1 = **متطلّب مقرر فقط**؛ امتحان الدخول يُفتح كدفعة لاحقة عند طلب صريح، ويُبنى على عقد E1 هذا (نفس نقطة الرفض في `EnrollmentService`).
- **سلاسل التبعية العميقة (A→B→C) ومنع الدوائر متعدّدة المستويات** — خارج v1. المنع الوحيد: التطابق الذاتي (مقرر متطلّب نفسه). تبرير: الكشف عن الدوائر العميقة يتطلّب اجتياز رسم بياني عند كل إضافة؛ سيناريو نادر في التأليف اليدوي، وأثره (مقرران يحجب كلٌّ منهما الآخر) لا يفسد البيانات بل يمنع الالتحاق فحسب — يُعالَج تأليفياً. يُضاف فحص الدورة عند ظهور حاجة فعلية.
- **«AND منطقي فقط»:** كل المتطلّبات إلزامية (لا «أكمل أياً من X أو Y») — يكفي معيار القبول؛ منطق «أيٌّ من» يُضاف لاحقاً عند الحاجة.
- **مسارات الالتحاق غير الطوعية** (Commerce `activateForOrder`، أكواد التسجيل `EnrollmentCodeService`، التحاق المسار `PathEnrollmentController`) — **خارج E1**. القيد على مسار `POST .../enroll` العام فقط؛ هذه المسارات قرارات إدارية/تجارية مقصودة لا يحجبها متطلّب الطالب. تُراجَع لاحقاً إن لزم.
- **عرض «هل أكملت المتطلّب؟» لكل مستخدم في `GET /catalog/courses` (القوائم)** — لا. القائمة العامّة مجهولة وثقيلة؛ الحالة تُحسب في صفحة التفاصيل من `GET /enrollments` فقط.
- **إشعار الطالب عند إكمال متطلّب يفتح مقرراً جديداً** — خارج النطاق.
