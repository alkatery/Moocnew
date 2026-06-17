# عقد الدفعة E2 — جدولة ظهور الأقسام (Scheduled Section Visibility)

> **الحالة:** نهائي للتنفيذ — يسلّمه `backend-dev` و`frontend-dev` حرفياً.
> **المرجع:** `docs/implementation-plan.md §6 E2` · `docs/feature-matrix.csv` (السطر «تواريخ الإصدار والظهور» P2) · `docs/PRD-MOOC-Platform-v2.md §5.ب/§6 (الأقسام/الدروس) §5.ج (الوصول)` · `docs/architecture/CONTEXTS.md` (Catalog يملك الأقسام/الدروس؛ Enrollment يملك التسليم/الوصول/التقدّم).
> **الهدف:** يضبط المؤلّف **تاريخ ظهور** (`visible_from`) لكل قسم. القسم المجدول لتاريخ مستقبلي يبقى **مخفيّاً تماماً عن الطالب** (لا عنوانه ولا دروسه ولا محتواه ولا تشغيله) في **كل** نقطة تسليم، بينما يراه **الطاقم** كاملاً (معاينة). يدعم «النشر التدريجي» بإصدار أقسام على دفعات.
> **المبدأ الحاكم لهذه الدفعة (حرج):** **عدم تسريب المحتوى المجدول للطالب في أي نقطة تسليم.** نقطة الحقيقة للإخفاء = **scope موحّد `visibleTo($user)` على علاقة `sections`** يُطبَّق في **كل** مسار يُرجع أقساماً/دروساً للطالب (العرض، المحتوى، التشغيل، التقدّم). الطاقم (مالك/مراجع/أدمن) يتجاوز الإخفاء دائماً. لا منطق نشر/إصدار جديد، ولا حالة أقسام جديدة — حقل واحد `nullable` يقرّر كل شيء. لا هندسة زائدة.

---

## 0. قرارات معمارية مثبتة (بعد قراءة الكود)

| البند | القرار | المصدر المقروء |
|------|--------|----------------|
| **وحدة الجدولة** | **القسم وحده في v1** (لا الدرس). القسم هو وحدة التجميع الطبيعية للنشر التدريجي؛ إخفاء قسم يخفي كل دروسه. جدولة الدرس المنفرد تُضاف لاحقاً بنفس النمط دون كسر العقد. | `Section.php` · `Lesson.php` (الدرس ينتمي لقسم واحد) |
| **الحقل** | `sections.visible_from` — **`nullable timestamp`**. `null` = ظاهر دائماً (السلوك الحالي لكل الأقسام القائمة). قيمة في الماضي/الآن = ظاهر. قيمة في المستقبل = مخفيّ عن الطالب، ظاهر للطاقم. | `create_sections_table` (لا حقل اليوم) |
| **دلالة الظهور للطالب** | `visible_from IS NULL OR visible_from <= now()`. | قرار العقد |
| **استثناء الطاقم** | **نعم** — المالك/المراجع/الأدمن يرون كل الأقسام (مجدولة أو لا) في كل نقطة. يُحسم عبر `CourseAccess::isStaffFor($user, $course)` المُعاد استخدامها. | `CourseAccess.php:43-47` |
| **نقطة الحقيقة للإخفاء (العرض)** | **`CourseController::show`** — المسار **الوحيد** الذي يبني شجرة الأقسام/الدروس للطالب وللطاقم وللاستوديو (`GET /catalog/courses/{course}`). تُفلتر علاقة `sections` المُحمَّلة بـ scope `visibleTo($user)`. **حرج:** الاستوديو يجلب هذا المسار بتوكن (auth)، **ومشغّل الطالب يجلبه مجهولاً** (`auth: false`) — فالفلترة على مستوى الـ scope حسب `$request->user()` تخدم الطرفين بنقطة واحدة. | `CourseController::show:72-89` · `learn/[slug]/page.tsx:80` (مجهول) · `studio/[slug]/page.tsx:41` (auth) |
| **نقطة الحقيقة للوصول (المحتوى/التشغيل)** | **`LessonAccess::canAccess`** — البوّابة الموحّدة لـ `LessonContentController` و`PlaybackController`. يُضاف فحص «هل قسم الدرس مخفيّ عن هذا المستخدم؟» **قبل** أي منح وصول، **بما في ذلك قبل short-circuit الـ `is_free_preview`** (وإلا يتسرّب درس معاينة في قسم مجدول). | `LessonAccess.php:20-46` · `LessonContentController.php:26` · `PlaybackController.php:32` |
| **معالجة `is_free_preview` داخل قسم مجدول** | **القسم المجدول يحجب حتى دروس المعاينة المجانية.** الجدولة قرار «متى يُتاح المحتوى» وله الأسبقية على «معاينة مجانية». درس معاينة في قسم مخفيّ → **لا يظهر في الشجرة ولا يُشغَّل** (للطالب/الزائر). | `LessonAccess.php:22` (short-circuit الحالي) |
| **رمز رفض الوصول** | **`403`** (لا `404`) — متسق مع `LessonContentController`/`PlaybackController` الحاليين (`abort_unless(...canAccess..., 403)`). لا نكشف وجود/عدد الأقسام المجدولة برمز مختلف. الدرس ضمن قسم مخفيّ **غير موجود في الشجرة أصلاً** للطالب، فلن تصله الواجهة عادةً؛ الـ 403 خط دفاع ثانٍ لطلب مباشر بالـ id. | `LessonContentController.php:26` · `PlaybackController.php:32` |
| **نقطة الحقيقة للتقدّم** | **`ProgressService::recalculate`** — مقام نسبة الإكمال يُحسب اليوم على **كل** دروس المقرر (`Lesson::whereHas('section'...)->count()`). يُعدَّل ليستثني دروس الأقسام **غير الظاهرة بعد** (`visible_from > now()`) من **المقام والبسط** معاً. القرار: التقدّم يُحسب على **الظاهر فعلاً** فقط. | `ProgressService.php:71-89` |
| **السياق المالك للحقل** | **Catalog** — `visible_from` خاصيّة تأليفية للقسم (مثل `title`/`position`). الـ scope يعيش على `Section` (Catalog). | `CONTEXTS.md` · `Section.php` |
| **السياق المالك للتسليم/الوصول/التقدّم** | **Enrollment** — `LessonAccess`/`ProgressService` يستهلكان الـ scope/الدلالة من Catalog لاتخاذ قرار الوصول والتقدّم. | `CONTEXTS.md` |
| **التأليف (ضبط التاريخ)** | عبر **`PATCH /catalog/sections/{section}`** القائم (`SectionController::update`) — يُضاف `visible_from` للحقول المقبولة. لا نقطة جديدة. التخويل القائم `can('update', $course)`. | `SectionController.php:27-32` · `SectionRequest.php` |
| **النقود** | لا تنطبق (لا حقول نقدية في هذه الدفعة). | — |
| **soft delete** | لا (تعديل عمود على جدول قائم؛ بلا حذف منطقي جديد). | قرار العقد |
| **الراية `payments.enabled`** | لا تمسّ هذا المنطق إطلاقاً — الجدولة سلوك تأليفي/وصولي مستقلّ عن التجارة. | `CONTEXTS.md` |

---

## 1. عقد الـ API

### 1.أ — عرض المقرر مع الأقسام المفلترة (تعديل سلوك قائم — **حرج**)

```
GET /api/v1/catalog/courses/{course}     (api.catalog.courses.show)   ← قائم، يتغيّر سلوكه
```

- **بلا تغيير في التوقيع.** يُعدَّل eager-load لعلاقة `sections` في `CourseController::show` ليطبّق scope `visibleTo($request->user())`:
  - **الطالب / الزائر (غير الطاقم):** يستقبل **فقط** الأقسام الظاهرة (`visible_from IS NULL OR visible_from <= now()`). الأقسام المجدولة مستقبلاً **غائبة كلياً** من المصفوفة — لا `id` ولا `title` ولا `lessons`.
  - **الطاقم (مالك/مراجع/أدمن للمقرر):** يستقبل **كل** الأقسام، وكلٌّ يحمل `visible_from` لعرض شارة «يظهر في …».
- **حقل جديد في `SectionResource`:** `visible_from` (ISO 8601 أو `null`). يُرسَل **دائماً** (للطالب يكون للأقسام الظاهرة فقط، وغالباً `null`؛ يفيد عرض «صدر حديثاً» اختيارياً، لكن لا يُستخدم لإخفاء أي شيء عند الطالب لأن المخفيّ غائب أصلاً).
- مثال (منظور الطاقم):
  ```jsonc
  {
    "data": {
      "id": 12,
      "title": "هياكل البيانات المتقدّمة",
      "sections": [
        { "id": 30, "title": "مقدّمة", "position": 1, "visible_from": null, "lessons": [ /* … */ ] },
        { "id": 31, "title": "الأسبوع الثاني", "position": 2, "visible_from": "2026-07-01T09:00:00+00:00", "lessons": [ /* … */ ] }
      ]
    }
  }
  ```
- منظور الطالب لنفس المقرر اليوم (`2026-06-15`): المصفوفة تحوي القسم `30` فقط (القسم `31` غائب تماماً).
- **منع N+1:** الـ scope يُطبَّق داخل eager-load واحد (`'sections' => fn ($q) => $q->visibleTo($user)->with('lessons')`) — لا استعلام لكل قسم.

### 1.ب — ضبط `visible_from` للقسم (تأليف — تعديل سلوك قائم)

```
PATCH /api/v1/catalog/sections/{section}     (api.catalog.sections.update)   ← قائم، يتوسّع
```

- **التخويل (قائم):** `SectionRequest::authorize` = `can('update', $section->course)` (المالك أو المراجع `courses.review`).
- **التحقّق (إضافة على `SectionRequest::rules`):**
  ```php
  'visible_from' => ['nullable', 'date'],
  ```
  - **`null` صريح يُقبل** ويعني «ظاهر دائماً» (إلغاء الجدولة). backend-dev يضمن أنّ إرسال `visible_from: null` يصفّر الحقل (لا يُتجاهَل) — أي يُدرَج ضمن `$section->update([... 'visible_from' => ...])` بشرطية تميّز «مُرسَل = null» عن «غير مُرسَل».
  - تاريخ في الماضي **مقبول** (= القسم ظاهر فوراً؛ سيناريو شرعي لإلغاء جدولة أو نشر فوري).
  - **لا قيد «مستقبل فقط»** — التبسيط: أي تاريخ صالح يُقبل، والدلالة (`<= now()`) تقرّر الظهور.
- **الاستجابة — `200`** عبر `SectionResource` (يتضمّن `visible_from` المحدّث).
- **إنشاء القسم (`POST /catalog/courses/{course}/sections`):** يبقى `visible_from = null` افتراضياً (قسم جديد ظاهر فوراً ما لم يُجدوَل لاحقاً عبر `PATCH`). إضافة `visible_from` لطلب الإنشاء **اختيارية** لـ backend-dev (نفس قاعدة التحقّق) لكنها ليست شرط قبول.

### رموز الأخطاء (موحّدة مع المنصّة)
| الحالة | الرمز | الجسم |
|-------|------|------|
| طلب محتوى/تشغيل درس ضمن قسم مخفيّ عن المستخدم (غير طاقم) | `403` | `abort_unless($access->canAccess(...), 403)` القائم — لا تغيير في الشكل |
| ضبط `visible_from` بلا صلاحية تأليف | `403` | `SectionRequest::authorize` ⇒ 403 |
| `visible_from` بصيغة تاريخ غير صالحة | `422` | تحقّق قياسي (`date`) برسالة عربية |
| طلب درس برقم غير موجود | `404` | route-model-binding القائم |

### حدود المعدّل
- **العرض (§1.أ):** بلا حدّ خاص — يرث حدّ `catalog/courses/{course}` القائم.
- **التأليف (§1.ب):** يرث حدّ مسارات `catalog` التأليفية تحت `auth:sanctum` (مطابق `sections.update` اليوم).

---

## 2. حدود الموديول (Bounded Contexts) ومنطق الإخفاء

### Catalog (يملك `visible_from` كخاصيّة تأليفية + الـ scope الموحّد)

- **هجرة** تضيف `visible_from` (`nullable timestamp`) إلى `sections` (§3).
- **على `Section`** (`app/Contexts/Catalog/Infrastructure/Persistence/Section.php`):
  - إضافة `'visible_from'` إلى `$fillable` و`casts()` (`'visible_from' => 'datetime'`).
  - **scope موحّد للظهور** — مصدر الحقيقة الوحيد لدلالة الإخفاء:
    ```php
    // الأقسام الظاهرة الآن (للطالب/الزائر): غير مجدولة أو حان موعدها.
    public function scopeVisibleNow(Builder $q): Builder
    {
        return $q->where(fn ($w) => $w->whereNull('visible_from')->orWhere('visible_from', '<=', now()));
    }

    // ظهور حسب المُشاهِد: الطاقم يرى الكل؛ غيره يرى الظاهر فقط.
    public function scopeVisibleTo(Builder $q, ?User $user, Course $course): Builder
    {
        if ($user !== null && /* CourseAccess::isStaffFor($user, $course) */) {
            return $q; // الطاقم يتجاوز الإخفاء
        }
        return $q->visibleNow();
    }
    ```
    - backend-dev يحقن `CourseAccess::isStaffFor` (أو يمرّر `bool $isStaff` محسوباً في المتحكّم لإبقاء النموذج نقياً من Identity). **الفحص نفسه (دلالة `visible_from`) يبقى في Catalog**؛ قرار «من الطاقم؟» يُحسب في طبقة Application/HTTP.
  - **دالة مساعدة على `Section`** لإعادة الاستخدام في Enrollment دون نسخ دلالة:
    ```php
    public function isVisibleNow(): bool   // visible_from === null || visible_from <= now()
    ```
- **`CourseController::show`** يستبدل `'sections.lessons'` بـ:
  ```php
  'sections' => fn ($q) => $q->visibleTo($request->user(), $course)->with('lessons'),
  ```
  (الطاقم محسوب من `CourseAccess::isStaffFor`؛ الزائر `null` ⇒ الظاهر فقط.)
- **`SectionController::update`** يضمّ `visible_from` إلى الحقول المحدَّثة (مع تمييز null الصريح).
- **`SectionResource`** يضيف `'visible_from' => $this->visible_from?->toIso8601String()`.

### Enrollment (يملك قرار الوصول والتقدّم — يستهلك دلالة Catalog)

- **`LessonAccess::canAccess`** (`app/Contexts/Enrollment/Application/LessonAccess.php`) — **حرج، أعِد الترتيب:**
  - يُحقن فحص الظهور **في مقدّمة الدالة، قبل short-circuit الـ `is_free_preview`**:
    ```
    1. حمّل section.course (قائم).
    2. حدِّد إن كان المستخدم من الطاقم (CourseAccess::isStaffFor) — الطاقم يتجاوز كل شيء (يصل دائماً).
    3. إن لم يكن طاقماً و الدرس ضمن قسم غير ظاهر بعد ($lesson->section->isVisibleNow() === false) ⇒ return false (لا وصول — حتى لو is_free_preview).
    4. تابع المنطق القائم: is_free_preview ⇒ true؛ ثم الالتحاق النشط…
    ```
  - **التبرير:** اليوم `is_free_preview` يمنح وصولاً فورياً (السطر 22) قبل أي اعتبار آخر — هذا تسريب لو كان الدرس في قسم مجدول. الجدولة لها الأسبقية لغير الطاقم.
  - **`activeEnrollmentFor`** (مسار كتابة التقدّم) لا يحتاج تعديلاً منطقياً (يقرأ الالتحاق فقط)، لكن منع تسجيل تقدّم على درس مخفيّ يُضمَن في `LessonProgressController` (انظر أدناه).
- **`LessonContentController::show` و`PlaybackController::show`** — **بلا تغيير في الكود** (يكفي أنّ `LessonAccess::canAccess` صار يرفض الدرس المخفيّ بـ `403`). نقطة دفاع موحّدة واحدة.
- **`LessonProgressController::store`** (مسار كتابة التقدّم) — يستخدم `activeEnrollmentFor` ثم يسجّل. **يُضاف**: استدعاء `LessonAccess::canAccess($user, $lesson)` قبل التسجيل، أو فحص `$lesson->section->isVisibleNow()` للطالب غير الطاقم ⇒ `403` «هذا الدرس غير متاح بعد.» (منع تسجيل تقدّم/استئناف على درس مجدول مُسرَّب عبر طلب مباشر بالـ id).
- **`ProgressService::recalculate`** (`ProgressService.php:71-89`) — **حرج للتقدّم:**
  - المقام الحالي `Lesson::whereHas('section', fn ($q) => $q->where('course_id', ...))->count()` يشمل دروس الأقسام المجدولة ⇒ يثبّت التقدّم تحت 100% للطالب الذي أنهى كل الظاهر. **يُعدَّل** لاستثناء الأقسام غير الظاهرة بعد:
    ```php
    ->whereHas('section', fn ($q) => $q->where('course_id', $enrollment->course_id)->visibleNow())
    ```
  - **البسط** (`completed_at` المعدودة) يُفلتر بنفس المنطق ضمناً (درس مخفيّ لا يُسجَّل له تقدّم أصلاً بعد منع §LessonProgressController؛ ولأمان إضافي يجوز ضمّ نفس القيد للبسط).
  - **القرار المعتمد:** **التقدّم يُحسب على الأقسام الظاهرة فعلاً فقط** (مقاماً وبسطاً). الطالب يبلغ 100% بإنهاء ما هو متاح له اليوم؛ وعند ظهور قسم جديد لاحقاً تُعاد نسبته تلقائياً (المقام يكبر) — وهذا السلوك المرغوب للنشر التدريجي.
  - **الاتساق مع E1:** `CourseCompletionService::evaluate` يُكمل الالتحاق عند `progress_percent >= 100` **و** اجتياز الدرجة. بهذا التعريف، الطالب قد يُكمل المقرر بناءً على المتاح حالياً؛ هذا مقبول ومقصود (محتوى لاحق يُثري لكنه لا يُلغي إكمالاً سابقاً، إذ `evaluate` لا يُنزِل حالة `Completed`). **هذا قرار صريح لتفادي تناقض E1/التقدّم — انظر §7 للبدائل المرفوضة.**

### الطبقية (إلزامي)
- **Domain لا يعرف Infrastructure/HTTP:** دلالة `visible_from` تعبير زمني بحت؛ قرار «من الطاقم؟» يُحسب من Identity في طبقة Application/HTTP ويُمرَّر كـ `bool` للـ scope/الدالة. لا يستورد `Section` (Infrastructure/Eloquent) أي شيء من Identity أو HTTP.
- المتحكّمات تمرّر كيانات Infrastructure (`Course`, `Lesson`, `User`) إلى Application (`LessonAccess`, `CourseAccess`) — نفس طبقية المتحكّمات القائمة.

---

## 3. مخطط الهجرة `add_visible_from_to_sections`

هجرة جديدة `database/migrations/XXXX_add_visible_from_to_sections_table.php`:

```php
public function up(): void
{
    Schema::table('sections', function (Blueprint $table) {
        // تاريخ ظهور القسم للطالب. null = ظاهر دائماً (سلوك كل الأقسام القائمة).
        // قيمة مستقبلية = مخفيّ عن الطالب حتى الموعد، ظاهر للطاقم (معاينة).
        $table->timestamp('visible_from')->nullable()->after('position');
        $table->index(['course_id', 'visible_from']);   // يخدم scopeVisibleTo/visibleNow ضمن المقرر
    });
}

public function down(): void
{
    Schema::table('sections', function (Blueprint $table) {
        $table->dropIndex(['course_id', 'visible_from']);
        $table->dropColumn('visible_from');
    });
}
```

- **`nullable` بلا قيمة افتراضية:** كل الأقسام القائمة تصبح `null` ⇒ ظاهرة (لا تغيير سلوكي رجعي). **هذا شرط أساسي: الترقية لا تخفي أي محتوى قائم.**
- **`index(['course_id','visible_from'])`:** يخدم استعلام «أقسام المقرر الظاهرة» (البادئة `course_id` تطابق الفهرس القائم `['course_id','position']` لكن هذا يضمّ `visible_from` للفلترة الزمنية).
- **النوع:** `timestamp` (يتسق مع `timestamps()` القائمة و`datetime` cast).
- **soft delete:** لا. **النقود:** لا حقول نقدية.
- **التراجع نظيف:** إسقاط الفهرس ثم العمود.

---

## 4. الواجهة (frontend-dev)

### 4.أ — الطالب/الزائر: لا يرى المجدول إطلاقاً (لا عمل إخفاء في الواجهة)

- **المبدأ:** الإخفاء **خلفي بالكامل**. مشغّل الطالب (`learn/[slug]/page.tsx`) ومعاينة الكتالوج (`CourseDetailClient.tsx`) يستهلكان `course.sections` كما تصلهما — والأقسام المجدولة **غائبة من المصفوفة أصلاً**. **لا يجوز** للواجهة الاعتماد على `visible_from` لإخفاء قسم (الخادم خط الدفاع؛ والمخفيّ لا يصل أصلاً).
- لا تغيير سلوكي مطلوب في `learn/[slug]/page.tsx` لعرض الطالب: العدّاد «X أقسام · Y درساً» (السطر 403) يعكس **الظاهر فقط** تلقائياً.
- **اختياري (تحسين):** إن أراد المنتج إشعار الطالب بوجود محتوى قادم، يُعرض شريط ثابت «سيُضاف محتوى جديد قريباً» **دون** كشف العناوين/التواريخ — لكنه **خارج شرط القبول** (الأنظف في v1: إخفاء كامل بلا تلميح).

### 4.ب — المؤلّف: ضبط `visible_from` في الاستوديو (`studio/[slug]/page.tsx`)

- **المكان:** تبويب «المنهج» — بطاقة كل قسم (حيث `renameSection`/`deleteSection`/`moveSection`، السطر ~87-107). يُضاف بجانب أزرار القسم:
  - **شارة حالة الظهور:**
    - `visible_from === null` ⇒ بلا شارة (أو شارة هادئة «ظاهر»).
    - `visible_from <= now()` ⇒ شارة «ظاهر» (صدر).
    - `visible_from > now()` ⇒ شارة بارزة «يظهر في {تاريخ مُنسَّق}» (`scheduled.visibleOn`).
  - **زرّ «جدولة الظهور»** يفتح حقل **تاريخ/وقت** (`<input type="datetime-local">` أو منتقي RTL) + زرّ حفظ يستدعي `PATCH /catalog/sections/{s.id}` بـ `{ visible_from }`، وزرّ **«إلغاء الجدولة»** يرسل `{ visible_from: null }` ⇒ `load()`.
- **التنسيق الزمني:** يُرسَل ISO 8601 (المنطقة الزمنية للمتصفّح ⇒ UTC أو مع offset)؛ يُعرض بالعربية/التقويم الميلادي بمنطقة المستخدم. RTL موروث.
- **حالات:** خطأ التحقّق (`422` تاريخ غير صالح) عبر `note`/`ErrorMsg` القائم؛ نجاح عبر `SuccessMsg`.
- **a11y (تنسيق compliance):** `<label>` لحقل التاريخ؛ الشارة تحمل نصّاً (لا اعتماد على اللون)؛ أزرار بأسماء واضحة («جدولة ظهور القسم: {title}»، «إلغاء جدولة القسم: {title}»).

### 4.ج — الأنواع (`frontend/src/lib/types.ts` — إضافة، لا كسر)

```ts
export interface Section {
  // ... القائم ...
  visible_from?: string | null;   // ISO 8601 أو null؛ يصل دائماً (للطالب يكون عادةً null للأقسام الظاهرة)
}
```
لا نوع جديد. الطالب لا يعتمد على هذا الحقل للإخفاء (الإخفاء خلفي).

### 4.د — مفاتيح i18n (`frontend/src/i18n/dictionary.ts` — ar + en)

`scheduled.visible` («ظاهر»)، `scheduled.visibleOn` («يظهر في {date}»)، `scheduled.schedule` («جدولة الظهور»)، `scheduled.unschedule` («إلغاء الجدولة»)، `scheduled.fieldLabel` («تاريخ ووقت ظهور القسم»)، `scheduled.hint` («حدّد متى يظهر هذا القسم للطلاب. اترك الحقل فارغاً ليظهر فوراً.»)، `scheduled.savedVisible` («سيظهر القسم للطلاب في الموعد المحدّد.»).

---

## 5. معايير القبول (قابلة للاختبار)

### خلفي — Feature/Unit tests (qa-tester)
1. **إخفاء عن الطالب في العرض:** مقرر بقسم `visible_from` مستقبلي وقسم `null`. **زائر/طالب** يطلب `GET /catalog/courses/{slug}` ⇒ المصفوفة تحوي القسم الظاهر فقط؛ المجدول **غائب كلياً** (لا id/title/lessons).
2. **ظهور للطاقم:** **مالك المقرر** (ومراجع/أدمن) يطلب نفس المسار ⇒ المصفوفة تحوي **كل** الأقسام، والمجدول يحمل `visible_from`.
3. **القسم الماضي/null ظاهر:** قسم بـ `visible_from` في الماضي أو `null` يظهر للطالب.
4. **حجب المحتوى:** طالب **ملتحق** يطلب `GET /lessons/{id}/content` لدرس ضمن قسم مجدول ⇒ **`403`**؛ ولدرس ضمن قسم ظاهر ⇒ `200`.
5. **حجب التشغيل:** طالب ملتحق يطلب `GET /lessons/{id}/playback` لدرس فيديو ضمن قسم مجدول ⇒ **`403`**.
6. **حجب معاينة مجانية في قسم مجدول (حرج):** درس `is_free_preview = true` **ضمن قسم مجدول** ⇒ زائر/طالب يطلب `content`/`playback` ⇒ **`403`** (الجدولة تتقدّم على المعاينة)؛ والطاقم ⇒ `200`.
7. **حجب كتابة التقدّم:** طالب يطلب `POST /lessons/{id}/progress` لدرس ضمن قسم مجدول ⇒ **`403`** (لا صفّ `LessonProgress`).
8. **التقدّم يستثني المخفيّ:** مقرر بـ 4 دروس ظاهرة + 2 ضمن قسم مجدول. طالب أكمل الـ 4 الظاهرة ⇒ `progress_percent = 100` (لا 67%)؛ والمقام في `recalculate` = 4.
9. **ظهور قسم لاحق يعيد حساب التقدّم:** بعد جعل القسم المجدول ظاهراً (تاريخ ماضٍ)، إعادة `recalculate` (عبر تسجيل تقدّم جديد) ⇒ المقام = 6؛ والطالب الذي أكمل 4 يصبح 67%. (إكمال سابق لا يُلغى — `Completed` يبقى.)
10. **ضبط التاريخ — مالك فقط:** المالك `PATCH /catalog/sections/{s}` بـ `{ visible_from }` ⇒ `200` والقيمة محفوظة/ظاهرة في `SectionResource`؛ مستخدم آخر (غير مراجع) ⇒ `403`، بلا تغيير.
11. **إلغاء الجدولة:** `PATCH` بـ `{ visible_from: null }` على قسم مجدول ⇒ يصبح ظاهراً للطالب فوراً (null صريح يصفّر الحقل، لا يُتجاهَل).
12. **تاريخ غير صالح:** `visible_from` بصيغة غير صالحة ⇒ `422`.
13. **الترقية لا تخفي القائم:** بعد الهجرة، كل الأقسام القائمة `visible_from = null` وتظهر للطالب كما قبل الدفعة (لا انحدار).
14. **منع N+1:** `show` يحمّل `sections.lessons` المفلترة بـ eager-load واحد (لا استعلام لكل قسم).
15. **الطلب المباشر بالـ id لا يتجاوز:** طالب يعرف `id` درس في قسم مجدول (مثلاً من مقرر آخر/تخمين) ⇒ `content`/`playback`/`progress` كلها `403` (لا اعتماد على غياب الدرس من الشجرة فقط).

### أمامي — Vitest (qa-tester)
- منظور الطالب: شجرة الأقسام تعرض الظاهر فقط (المُمَرَّر من API) — لا اعتماد على `visible_from` للإخفاء.
- استوديو: قسم بـ `visible_from` مستقبلي يعرض شارة «يظهر في {تاريخ}»؛ زرّ «جدولة الظهور» يستدعي `PATCH` بالقيمة؛ زرّ «إلغاء الجدولة» يستدعي `PATCH` بـ `null`؛ حالة نجاح/خطأ.

---

## 6. تقسيم المسؤوليات

| الدور | المهام |
|------|--------|
| **backend-dev** | هجرة `add visible_from to sections` (`nullable timestamp` + فهرس `['course_id','visible_from']`، تراجع نظيف) · على `Section`: `fillable`+`cast datetime` + `scopeVisibleNow`/`scopeVisibleTo($user,$course)` + `isVisibleNow()` · `CourseController::show` يفلتر `sections` بـ `visibleTo($user,$course)` (eager-load واحد) · `SectionResource` يضيف `visible_from` · `SectionRequest` يقبل `visible_from` (nullable date) و`SectionController::update` يحفظه مع تمييز null الصريح · **`LessonAccess::canAccess` يفحص ظهور القسم قبل short-circuit `is_free_preview` (الطاقم يتجاوز)** · `LessonProgressController::store` يرفض الدرس المخفيّ بـ 403 · **`ProgressService::recalculate` يستثني الأقسام غير الظاهرة من المقام (والبسط)**. إعادة استخدام `CourseAccess::isStaffFor` — لا حالة/منطق نشر جديد. |
| **frontend-dev** | استوديو: شارة حالة الظهور + حقل تاريخ/وقت (`PATCH section`) + زرّ إلغاء الجدولة في `studio/[slug]/page.tsx` · تمديد نوع `Section` بـ `visible_from` في `types.ts` · مفاتيح i18n (ar/en). **لا منطق إخفاء في عرض الطالب** (خلفي بالكامل). إعادة استخدام `api`/`ErrorMsg`/`SuccessMsg`/`PageHeader`. |
| **integrator** | يثبّت طرفاً لطرف: غياب القسم المجدول من `show` للزائر مقابل ظهوره للطاقم؛ `403` لـ content/playback/progress على درس مخفيّ (بما فيه معاينة مجانية)؛ المقام في التقدّم يطابق الظاهر؛ `PATCH section` بـ `visible_from`/`null` يطابق ما يرسله الاستوديو. |
| **qa-tester** | Feature tests §5 (إخفاء العرض، ظهور الطاقم، حجب content/playback/progress، **معاينة مجانية في قسم مجدول**، التقدّم يستثني المخفيّ وإعادة الحساب عند الظهور، تأليف للمالك فقط، إلغاء الجدولة، الترقية لا تخفي، N+1، الطلب المباشر بالـ id) + اختبار أمامي للاستوديو. |
| **compliance** | a11y شارة الظهور (نصّ + لا اعتماد على اللون) وحقل التاريخ (`<label>`، RTL، تقويم) + رسائل عربية واضحة + PDPL (لا بيانات شخصية جديدة؛ `visible_from` بيانات محتوى بحتة) + التأكّد أن المخفيّ لا يتسرّب في أي عرض. |
| **reviewer** | البوّابة — لا اعتماد قبل توقيعه. **تدقيق خاص: لا تسريب محتوى مجدول في أي نقطة تسليم.** |

---

## 7. ما هو خارج النطاق (لا هندسة زائدة) — وقرارات صريحة

- **جدولة الدرس المنفرد** — خارج v1. القسم وحدة الجدولة الكافية للنشر التدريجي وللمصفوفة. إن لزم لاحقاً، يُضاف `lessons.visible_from` بنفس النمط (scope على `Lesson`، فحص في `LessonAccess`) دون كسر هذا العقد.
- **تاريخ «انتهاء ظهور» / `visible_until`** — خارج النطاق. المصفوفة تطلب «تاريخ الإصدار/الظهور» (متى يُتاح)، لا سحب المحتوى. يُضاف عند طلب صريح.
- **النشر المؤقّت بالتوقيت الدقيق عبر Scheduler** — **لا**. الإخفاء **محسوب وقت الطلب** (`visible_from <= now()`) — لا حاجة لمهمّة مجدولة تُبدّل حالة؛ القسم «يظهر» لحظة تجاوز `now()` للتاريخ، تلقائياً وبلا أثر جانبي. أبسط وأدقّ.
- **حالة قسم صريحة (`draft/scheduled/published`)** — **لا**. حقل `visible_from` الواحد يكفي ويُجنّب آلة حالة زائدة. «مجدول» = `visible_from > now()` محسوبة، لا حالة مخزّنة.
- **إشعار الطالب عند صدور قسم جديد** — خارج النطاق (يُبنى لاحقاً على سياق Notification إن طُلب).
- **البديل المرفوض للتقدّم (احتساب المخفيّ في المقام):** رُفض جعل المقام = **كل** الدروس (بما فيها المجدولة). السبب: يثبّت الطالب الذي أنهى كل المتاح تحت 100% بلا فعل ممكن، ويناقض E1 (لا يصل أبداً إلى `Completed` رغم إنهاء المتاح). **القرار المعتمد: التقدّم على الظاهر فقط**، وإكمال سابق لا يُلغى عند صدور محتوى جديد (`CourseCompletionService::evaluate` لا يُنزِل `Completed`). هذا يحافظ على اتساق E1 ويخدم تجربة النشر التدريجي.
- **الالتحاق المشروط بظهور قسم** — لا علاقة؛ الالتحاق مستقلّ عن الجدولة (الطالب يلتحق ويرى المتاح، ويتكشّف الباقي زمنياً).
