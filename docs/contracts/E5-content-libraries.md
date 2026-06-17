# عقد الدفعة E5 — مكتبات المحتوى (بنك أسئلة معاد استخدامه عبر المقررات)

> **الحالة:** نهائي للتنفيذ — يسلّمه `backend-dev` و`frontend-dev` حرفياً. **آخر دفعة في خطّة التنفيذ.**
> **المرجع:** `docs/implementation-plan.md §6 E5` · `docs/feature-matrix.csv` (السطر: «مكتبات المحتوى v1 — بنك أسئلة قابل لإعادة الاستخدام عبر المقررات» P2) · `docs/PRD-MOOC-Platform-v2.md §5.هـ (بنك الأسئلة المُعاد استخدامه)` · `docs/architecture/CONTEXTS.md` (Assessment يملك بنك الأسئلة).
> **الهدف:** تمكين المؤلّف من **إعادة استخدام أسئلته عبر مقرراته**: استيراد (نسخ عميق) سؤال/أسئلة من بنوك مقرراته الأخرى (التي يملكها أو هو طاقم فيها) إلى بنك المقرر الحالي، فتصبح **نسخاً مستقلّة مملوكة للمقرر الهدف**.
> **المبدأ الحاكم لهذه الدفعة (حسّاسان):** **(1) النسخ العميق — عزل المصدر عن الهدف:** السؤال المستورَد نسخة جديدة مستقلّة تماماً بكل خصائص E4؛ **تعديل/حذف المصدر لاحقاً لا يؤثّر على النسخة، والعكس**. **لا مرجع مشترك، لا FK للمصدر.** **(2) عزل ملكية المؤلّف:** المؤلّف يستورد **فقط** من مقرراته/مقررات هو طاقم فيها — **لا أسئلة مؤلّفين آخرين**، يُتحقَّق من ملكية المصدر **والهدف** على الخادم لكل سؤال.

---

## 0. قرارات معمارية مثبتة (بعد قراءة الكود)

### 0.1 النهج المختار: **(أ) إعادة استخدام عبر النسخ بين مقررات المؤلّف — بلا كيان مكتبة جديد**

**القرار:** «المكتبة» في v1 = **مجموع أسئلة مقررات المؤلّف الأخرى**. لا جدول `question_libraries`، لا أسئلة منفصلة عن المقرر، لا CRUD مكتبة. نقطتا API فقط: **بحث/قائمة** الأسئلة القابلة للاستيراد، و**استيراد (نسخ عميق)** إلى بنك المقرر الحالي.

| الخيار | الكلفة | القرار |
|------|--------|--------|
| **(أ) نسخ بين مقررات المؤلّف** | لا هجرات، لا كيان جديد، يعيد استخدام `question_bank` و`CourseAccess::isStaffFor` و`CoursePolicy` و`AssessmentsPanel` بالكامل. سطح أصغر، خطر أقل. | **✅ مختار لـ v1** |
| (ب) كيان مكتبة مستقل (`question_libraries`) | هجرات جديدة + CRUD مكتبة + Policy جديدة + مزامنة ازدواجية «سؤال مكتبة ↔ سؤال مقرر». أنظف مفهومياً لكن أثقل بكثير. | ❌ مؤجّل (مكتبات هيكلية v2) |

**لماذا (أ):**
1. **يحقّق المعيار حرفياً** — «إعادة الاستخدام عبر المقررات» = أُنشئ سؤالاً في مقرر، أعِد استخدامه في اختبار أي مقرر آخر لك. النسخ يوفّر ذلك دون مفهوم جديد على المؤلّف.
2. **يعيد استخدام كلّ القائم** — `question_bank.course_id` يبقى المُلكية الوحيدة؛ `CourseCloner::cloneQuestionBank` يثبت أنّ النسخ العميق للأسئلة نمط قائم؛ `isStaffFor` يحدّد «مقررات المؤلّف» جاهزاً.
3. **لا ازدواجية حالة** — لا «نسخة مكتبة» و«نسخة مقرر» تتباعدان؛ مصدر حقيقة واحد لكل سؤال (`question_bank`).
4. **آخر دفعة — انضباط النطاق** — أبسط عقد كافٍ، لا هندسة زائدة (قاعدة `architect`).

> **المكتبات الهيكلية v2 (أقسام كاملة كوحدات معاد استخدامها) خارج النطاق صراحةً** — لا تُذكر في الواجهة ولا الـAPI. أي طلب لها قرار صريح لاحق.

### 0.2 بقيّة القرارات

| البند | القرار | المصدر المقروء |
|------|--------|----------------|
| السياق المالك | **Assessment** حصراً. الاستيراد منطق تطبيقي (`Application`) ينسخ صفوف `question_bank`. لا يمسّ Catalog/Enrollment سوى قراءة «مقررات الطاقم» عبر `CourseAccess` (خدمة Enrollment قائمة) و`Course` (Catalog). | `QuestionController.php` · `CourseAccess.php` |
| ملكية السؤال | `question_bank.course_id` فقط (FK → `courses`، `cascadeOnDelete`). **لا عمود ملكية جديد، لا عمود مصدر.** | `create_question_bank_table` |
| «أسئلة المؤلّف القابلة لإعادة الاستخدام» | أسئلة كل مقرر `c` حيث `isStaffFor(user, c)` (مالك أو co_author أو reviewer)، **باستثناء المقرر الهدف**. نفس منطق `CourseController::mine` (مالك OR `whereHas members`). | `CourseAccess::isStaffFor` · `CourseController::mine:39-41` |
| النسخ العميق | نسخة جديدة في `question_bank` بـ `course_id = الهدف` + **كل** أعمدة المحتوى (`type, body, choices, correct, config, explanation, points`). **بلا `id` المصدر، بلا أي مرجع للمصدر.** | `CourseCloner::cloneQuestionBank` (نمط — **مع تصحيح ثغرة §2.3**) |
| ربط بالاختبار | بلا تغيير — السؤال المستورَد سؤال بنك عادي يُختار في `quiz_questions` كأي سؤال (`QuizController::syncQuestions`). | `create_quiz_questions_table` |
| النقود | لا تنطبق (لا حقول نقدية). | — |
| soft delete | لا — يتبع `question_bank` القائم (حذف فعلي). حذف المصدر **لا** يحذف النسخة (لا FK بينهما — هذا جوهر العزل). | `create_question_bank_table` |
| التجارة | لا علاقة — التأليف خلف Sanctum، لا تمسّ `payments.enabled`. | — |
| PDPL | لا بيانات شخصية — أسئلة محتوى تعليمي. لا تسجيل بيانات أفراد. | — |

---

## 1. مخطط البيانات

**لا هجرات جديدة. لا أعمدة جديدة. لا جداول جديدة.**

E5 لا يضيف بنية — يعيد استخدام `question_bank` كما هو (بعد E4). الأعمدة التي تُنسَخ في الاستيراد:

| العمود | يُنسَخ؟ | ملاحظة |
|------|--------|--------|
| `id` | ❌ | جديد تلقائياً للنسخة |
| `course_id` | ❌ | = **المقرر الهدف** (لا المصدر) |
| `type` | ✅ | كما المصدر |
| `body` | ✅ | كما المصدر |
| `choices` | ✅ | نسخة JSON كاملة (معرّفات الخيارات تبقى كما هي) |
| `correct` | ✅ | مفتاح الإجابة كاملاً |
| `config` | ✅ | **E4 — tolerance/flags. لا يجوز إسقاطه** |
| `explanation` | ✅ | **E4 — تغذية راجعة. لا يجوز إسقاطه** |
| `points` | ✅ | كما المصدر |
| `created_at`/`updated_at` | ❌ | جديدة (لحظة الاستيراد) |

> **عزل تام:** لا عمود `source_question_id` ولا FK للمصدر. النسخة بعد الإنشاء كيان مستقلّ لا يعرف أصله — هذا ما يضمن أن تعديل/حذف المصدر لا يلمسها.

---

## 2. منطق الاستيراد — خدمة تطبيقية جديدة (Application)

`app/Contexts/Assessment/Application/QuestionImporter.php` (جديد) — تستقبل المقرر الهدف وقائمة معرّفات أسئلة المصدر، تتحقّق من ملكية كل مصدر، وتنسخ نسخاً عميقة في معاملة واحدة.

### 2.1 التوقيع والسلوك (منطق فقط — لا كود إنتاج هنا)
```
import(actor: User, targetCourse: Course, sourceQuestionIds: int[]): Question[]   // النسخ الجديدة
  داخل DB::transaction:
    - sources = Question::whereIn('id', sourceQuestionIds)->get()
    - لكل source:
        • abort 403 إن لم يكن isStaffFor(actor, source.course)        // عزل الملكية — لكل سؤال
        • abort 422 إن كان source.course_id === targetCourse.id        // لا استيراد من البنك نفسه
    - نسخ عميق لكل source إلى صفّ جديد course_id=targetCourse.id بكل أعمدة §1 (✅)
    - return الصفوف الجديدة
```
- **الترتيب يُحفظ** بترتيب `sourceQuestionIds` المُرسَل (إدراج متسلسل).
- **التحقّق قبل أي إدراج** — إن فشل أي سؤال (ملكية/هدف خاطئ) تُلغى المعاملة كاملةً (ذرّية: إمّا الكل أو لا شيء). يمنع استيراداً جزئياً غامضاً.
- **معرّف مفقود** (`id` غير موجود في `question_bank` أصلاً): `whereIn` يتجاهله؛ القرار: إن نقص أيّ معرّف من النتيجة ⇒ `422` (`some_questions_not_found`) قبل الإدراج — لا نسخ صامت ناقص.

### 2.2 لماذا `Application` لا `Domain`
النسخ يلمس Eloquent (`question_bank`) و`CourseAccess` (تخويل عبر سياق Enrollment) — هذه `Infrastructure`/تنسيق، فموضعها `Application` (مثل `CourseCloner` في Catalog). `Domain` (`QuestionType`/`AnswerGrader`) **لا يُلمَس** في هذه الدفعة.

### 2.3 ⚠️ ثغرة قائمة يجب تصحيحها (مرجع — تتبع E5 النمط الصحيح)
`CourseCloner::cloneQuestionBank` (السطور 108-119) ينسخ `type, body, choices, correct, points` **فقط** — **يُسقط `config` و`explanation` (حقول E4).** أي نسخة عميقة في E5 **يجب أن تنسخ السبعة كلها** (§1). 
- **إلزامي:** `QuestionImporter` ينسخ الحقول السبعة كاملةً.
- **مستحسن (إصلاح اتساق):** تصحيح `CourseCloner::cloneQuestionBank` ليضيف `'config' => $question->config` و`'explanation' => $question->explanation` — وإلّا فإنّ استنساخ مقرر يفقد إعدادات E4. (يُسجَّل كبند تنظيف ضمن هذه الدفعة؛ لا تُترك ثغرة E4 صامتة.)

---

## 3. عقد الـ API

سياق `assessment`، خلف `auth:sanctum`، بادئة `/api/v1/assessment`. مساران جديدان فقط.

### 3.1 `GET /api/v1/assessment/courses/{course}/questions/importable` — مصادر الاستيراد

قائمة أسئلة المؤلّف القابلة للاستيراد إلى بنك `{course}` (المقرر الهدف).

| البند | القيمة |
|------|--------|
| **الطريقة/المسار** | `GET assessment/courses/{course}/questions/importable` |
| **الاسم** | `api.assessment.questions.importable` |
| **التخويل** | `abort_unless($user->can('update', $course), 403)` — لا بدّ أن يملك المؤلّف صلاحية **التأليف على المقرر الهدف** أولاً (سيستورد إليه). |
| **بارامترات الاستعلام** | `q` (نصّ بحث في `body`، اختياري، `≤100` محرف)؛ `source_course_id` (تصفية بمقرر مصدر بعينه، اختياري، `exists:courses,id`)؛ `type` (تصفية بنوع، اختياري، ضمن `QuestionType`)؛ `page` (ترقيم). |
| **منطق الاستعلام** | أسئلة كل مقرر `c` حيث `isStaffFor(user, c)` **و** `c.id ≠ {course}.id` (استثناء المقرر الهدف). أي: `course_id IN (مقررات الطاقم ما عدا الهدف)`. تطبيق `q`/`type`/`source_course_id` فوقها. |
| **الترتيب** | `latest()` (الأحدث أولاً) داخل التجميع، مع اسم المقرر المصدر لكل سؤال. |
| **الترقيم** | `paginate(20)`. |
| **الاستجابة `200`** | `ImportableQuestionResource::collection` (§3.3). |
| **الأخطاء** | `403 forbidden` (لا صلاحية تأليف على الهدف) · `422` (بارامتر استعلام غير صالح) · `401` (غير مصادَق). |
| **حدّ المعدّل** | افتراضي مجموعة `assessment` (يُورَث؛ لا حدّ خاص). |

> **عزل صريح:** الاستعلام لا يطال **مطلقاً** مقرراً لا `isStaffFor` فيه المستخدم. حتّى لو زوّر العميل `source_course_id` لمقرر غريب، فلتر `course_id IN (مقررات الطاقم)` يُفرَغ النتيجة منه (لا تسريب أسئلة الآخرين). تأكيد اختباري §6.

### 3.2 `POST /api/v1/assessment/courses/{course}/questions/import` — استيراد (نسخ عميق)

ينسخ أسئلة مصدر مختارة إلى بنك `{course}` كنسخ جديدة مستقلّة.

| البند | القيمة |
|------|--------|
| **الطريقة/المسار** | `POST assessment/courses/{course}/questions/import` |
| **الاسم** | `api.assessment.questions.import` |
| **التخويل** | `abort_unless($user->can('update', $course), 403)` على **المقرر الهدف** (نموذج: `QuestionController::index`). **زائداً** تحقّق `isStaffFor` على مقرر **كل** سؤال مصدر داخل `QuestionImporter` (§2.1) ⇒ `403` إن انتُهك. |
| **جسم الطلب** | `{ "source_question_ids": int[] }` |
| **التحقّق (FormRequest)** | `source_question_ids` → `required, array, min:1, max:100`؛ `source_question_ids.*` → `integer, distinct, exists:question_bank,id`. |
| **الاستجابة `201`** | `QuestionAdminResource::collection` للنُّسخ **الجديدة** (المؤلّف يرى المفتاح؛ مطابق `store`). مغلَّفة `{ "data": [...] }`. |
| **الأخطاء** | `403 forbidden` (لا تأليف على الهدف، أو مصدر ليس المستخدم طاقماً فيه) · `422 unprocessable` (`source_question_ids` فارغ/غير صالح، أو معرّف مفقود `some_questions_not_found`، أو مصدر = الهدف نفسه `cannot_import_into_self`) · `401`. |
| **حدّ المعدّل** | افتراضي مجموعة `assessment`. |
| **الذرّية** | معاملة واحدة — فشل أي سؤال يُلغي الكل (§2.1). |

> **عزل النسخ:** بعد `201`، النُّسخ صفوف جديدة في `question_bank` بـ `course_id = الهدف` ولا تحمل أثراً للمصدر. ترتيبها = ترتيب `source_question_ids`.

### 3.3 `ImportableQuestionResource` (جديد — للقائمة فقط)

`app/Http/Resources/ImportableQuestionResource.php` — عرض **منتقي** للسؤال المصدر في شاشة الاستيراد. **يكشف ما يكفي للاختيار، لا أكثر** (لا داعي لكشف مفتاح الإجابة في قائمة تصفّح).

```
{
  "id": int,                       // معرّف السؤال المصدر (يُرسَل لاحقاً في source_question_ids)
  "type": string,                  // نوع السؤال (تسمية/أيقونة في الواجهة)
  "body": string,                  // نصّ السؤال (للمعاينة/البحث)
  "points": int,
  "choices_count": int|null,       // عدد الخيارات (لا الخيارات نفسها) — تلميح كثافة
  "source_course": { "id": int, "title": string, "slug": string }   // من أين سيُستورَد
}
```
- **لا `correct`، لا `config`، لا `choices` الكاملة** في القائمة — تقليل البيانات، والمفتاح غير لازم للاختيار. (يُنسَخ كاملاً عند الاستيراد على الخادم؛ لا يمرّ عبر القائمة.)
- `source_course` يُعرَّف عبر `whenLoaded('course')` (تحميل مُسبَق `with('course:id,title,slug')` لتفادي N+1).

> **عقد قائم بلا تغيير:** `QuestionAdminResource` و`QuestionResource` و`StoreQuestionRequest` و`Question` و`AnswerGrader` و`QuizController` تبقى **حرفياً كما هي**. E5 إضافة بحتة.

---

## 4. التخويل والعزل (حسّاس)

| الحالة | السلوك | الموضع |
|------|--------|--------|
| استيراد إلى مقرر لا يملك المستخدم التأليف فيه | `403` على **الهدف** | `import` (FormRequest/controller `can('update', target)`) |
| استيراد سؤال من مقرر ليس المستخدم طاقماً فيه (مؤلّف آخر) | `403` على **المصدر** — يُتحقَّق `isStaffFor` لكل سؤال داخل الخدمة | `QuestionImporter` §2.1 |
| قائمة المصادر تكشف أسئلة مؤلّف آخر | **مستحيل** — فلتر `course_id IN (مقررات الطاقم)`؛ لا تطال غيرها حتّى بتزوير `source_course_id` | `importable` §3.1 |
| المؤلّف المشارك (co_author على المصدر) يستورد منه | **مسموح** — `isStaffFor` يشمل co_author (E3) | `CourseAccess::isStaffFor:46` |
| استيراد من البنك نفسه (مصدر = الهدف) | `422 cannot_import_into_self` | `QuestionImporter` §2.1 |
| تعديل/حذف السؤال المصدر بعد الاستيراد | **لا أثر على النسخة** (لا FK، لا مرجع) | المخطط §1 |
| تعديل/حذف النسخة بعد الاستيراد | **لا أثر على المصدر** (كيانان مستقلّان) | المخطط §1 |
| Super Admin / Reviewer | يتجاوز عبر `Gate::before` / `isStaffFor` يشمل `ReviewCourses` — متّسق مع القائم | `CoursePolicy` · `isStaffFor:47` |

---

## 5. الواجهة الأمامية

### 5.1 الأنواع (`frontend/src/lib/types.ts`)
```ts
export interface ImportableQuestion {
  id: number;
  type: QuestionKind;
  body: string;
  points: number;
  choices_count: number | null;
  source_course: { id: number; title: string; slug: string };
}
```
> `BankQuestion` و`QuestionKind` و`QuestionChoice` بلا تغيير.

### 5.2 `AssessmentsPanel.tsx` — بطاقة «بنك الأسئلة»
- **زرّ جديد «استيراد من مكتبتي»** بجوار عنوان «بنك الأسئلة» (أعلى `QuestionForm`). يفتح مكوّناً جديداً `<QuestionImportPicker courseSlug={courseSlug} onImported={load} />` (منبثق/لوح جانبي inline، يُحمَّل كسلًا عند الفتح فقط — مثل نمط `SubmissionReview`).
- بعد استيراد ناجح: استدعاء `load()` القائم (يُعيد جلب `questions`) فتظهر النُّسخ فوراً في القائمة، ويُغلق المنتقي.

### 5.3 مكوّن جديد `QuestionImportPicker`
`frontend/src/components/studio/QuestionImportPicker.tsx`:
- **التحميل:** `GET /assessment/courses/${courseSlug}/questions/importable` (مع `q`/`type`/`source_course_id` اختيارياً عبر بارامترات).
- **العرض:** قائمة أسئلة قابلة للاختيار (checkbox)، لكل عنصر: نصّ السؤال (مبتور)، تسمية النوع (`KIND_LABELS`)، النقاط، واسم **المقرر المصدر** (`source_course.title`) كشارة.
- **البحث/التصفية:** حقل بحث `q` (debounce)، ومُنتقي «المقرر المصدر» اختياري، ومُنتقي النوع اختياري.
- **الاستيراد:** زرّ «استيراد المحدّد (n)» ⇒ `POST .../questions/import { source_question_ids }` بالمعرّفات المختارة. معطّل عند `n=0`.
- **الحالات (إلزامية — Vitest):**
  - **تحميل:** هيكل/مؤشّر تحميل أثناء الجلب.
  - **فراغ:** «لا أسئلة متاحة للاستيراد من مقرراتك الأخرى.» (لا مصادر، أو بحث بلا نتائج).
  - **خطأ:** `ErrorMsg` (role="alert") عند فشل الجلب/الاستيراد.
  - **نجاح:** رسالة «استُورد n سؤالاً ✓» (role="status")، ثم `onImported()` وإغلاق.
- **a11y/RTL:** عربي افتراضاً RTL؛ `aria-expanded`/`aria-controls` على زرّ الفتح؛ تسميات للمدخلات؛ نصّ السؤال مبتور بـ`truncate` مع `title`.

> **عزل واجهي:** المنتقي يعرض **فقط** ما يعيده `importable` (مقررات المؤلّف) — لا واجهة لتصفّح أسئلة مؤلّفين آخرين أصلاً.

### 5.4 i18n
مفاتيح عربية جديدة في `dictionary`: `import.open` («استيراد من مكتبتي»)، `import.title`، `import.empty`، `import.searchPlaceholder`، `import.sourceFilter`، `import.submit` (مع عدّاد)، `import.success`.

---

## 6. معايير القبول (قابلة للاختبار)

### 6.1 الخادم — `QuestionImporter` + الاستيراد (Pest Feature)
1. **نسخ مستقلّ كامل:** استيراد سؤال `mcq` من مقرر A إلى مقرر B ⇒ صفّ جديد في `question_bank` بـ `course_id=B`، بـ `body/type/choices/correct/points` مطابقة، و**`id` مختلف**. عدد أسئلة B زاد 1، وأسئلة A بلا تغيير.
2. **كل خصائص E4 تُنسَخ:** استيراد سؤال `numerical` (`config.tolerance`) وسؤال `regex` (`config.flags`) وسؤال بـ`explanation` ⇒ النُّسخ تحمل `config` و`explanation` **مطابقَين** للمصدر (تأكيد عدم إسقاط حقول E4 — §2.3).
3. **عزل المصدر عن الهدف:** بعد الاستيراد، **تعديل** `body`/`correct`/`config` للسؤال المصدر ⇒ النسخة **بلا تغيير**. **حذف** السؤال المصدر ⇒ النسخة **باقية** (لا تتالي).
4. **عزل الهدف عن المصدر:** تعديل/حذف النسخة ⇒ المصدر **بلا تغيير**.
5. **منع استيراد أسئلة مؤلّف آخر:** مؤلّف يحاول استيراد سؤال من مقرر لا يملكه/ليس co_author فيه ⇒ `403`، ولا صفّ جديد.
6. **عزل القائمة:** `importable` لمؤلّف لا يحوي **أيّ** سؤال من مقرر خارج طاقمه — حتّى مع `source_course_id` مزوّر لمقرر غريب ⇒ نتيجة فارغة منه (لا تسريب).
7. **استثناء المقرر الهدف من البحث:** أسئلة المقرر الهدف نفسه **لا تظهر** في `importable` لذلك الهدف (تجنّب الاستيراد الدائري). محاولة استيراد من البنك نفسه ⇒ `422 cannot_import_into_self`.
8. **تخويل الهدف:** مستخدم بلا `update` على المقرر الهدف ⇒ `403` على `importable` و`import`.
9. **co_author يستورد:** مؤلّف مشارك (E3) على المقرر المصدر **والهدف** ⇒ استيراد ناجح `201`.
10. **ذرّية:** قائمة فيها سؤال صالح وآخر من مقرر غريب ⇒ `403` و**لا** نسخ جزئي (صفر صفوف جديدة).
11. **معرّف مفقود:** `source_question_ids` فيه معرّف غير موجود ⇒ `422 some_questions_not_found`، لا نسخ.
12. **ترتيب وحدّ:** ≤100 معرّف؛ الترتيب محفوظ؛ `min:1` (فارغ ⇒ `422`).

### 6.2 السلامة (تأكيد عدم كسر القائم)
13. السؤال المستورَد يُختار في اختبار (`quiz_questions`) ويُصحَّح كأي سؤال بنك — `QuizAttemptService` بلا تغيير.
14. `QuestionResource` للطالب يبقى يحجب `correct`/`config` للنُّسخ (وراثة سلوك E4 — النسخة سؤال عادي).
15. مسارات/موارد E4 القائمة تمرّ دون تعديل.

### 6.3 الواجهة (Vitest)
16. `QuestionImportPicker`: حالات تحميل/فراغ/خطأ/نجاح كلها مُصيَّرة بأدوار a11y صحيحة.
17. زرّ «استيراد المحدّد» معطّل عند صفر اختيار؛ يستدعي `POST import` بالمعرّفات المختارة؛ بعد النجاح يستدعي `onImported`.
18. عند `importable` فارغ ⇒ رسالة الفراغ، لا قائمة.

---

## 7. تقسيم المسؤوليات

| المكوّن | المسؤول | الملفات |
|--------|---------|--------|
| منطق الاستيراد (Application) | backend-dev | **جديد** `app/Contexts/Assessment/Application/QuestionImporter.php` (نسخ عميق + تحقّق ملكية لكل مصدر، معاملة ذرّية) |
| إصلاح ثغرة E4 (تنظيف) | backend-dev | `app/Contexts/Catalog/Application/CourseCloner.php` (`cloneQuestionBank` يضيف `config`+`explanation`) §2.3 |
| المتحكّم + الموارد + التحقّق (Http) | backend-dev | **جديد** `QuestionImportController` (أو إجراءان على `QuestionController`) · **جديد** `ImportQuestionsRequest` · **جديد** `ImportableQuestionResource` · إعادة استخدام `QuestionAdminResource` للنُّسخ |
| المسارات | backend-dev | `routes/api.php` (سطران داخل مجموعة `assessment`: `questions.importable` GET، `questions.import` POST) |
| الأنواع + المنتقي + الزرّ (Frontend) | frontend-dev | `frontend/src/lib/types.ts` (`ImportableQuestion`) · **جديد** `components/studio/QuestionImportPicker.tsx` · `components/studio/AssessmentsPanel.tsx` (زرّ + دمج) · `i18n/dictionary` (مفاتيح `import.*`) |
| الاختبارات | backend-dev (Pest Feature §6.1-6.2) · frontend-dev (Vitest §6.3) | حسب §6 |

> **حدود الموديول:** المنطق كلّه داخل سياق **Assessment** (الاستيراد ينسخ صفوف `question_bank`). يقرأ «مقررات الطاقم» عبر `CourseAccess::isStaffFor` (خدمة Enrollment قائمة) و`Course` (Catalog) — **قراءة تخويل فقط، لا تعديل عبر الحدود.** `Domain` (`AnswerGrader`/`QuestionType`) **لا يُلمَس.** لا كيان/جدول/راية/نقود جديدة. أبسط عقد كافٍ يحقّق «إعادة الاستخدام عبر المقررات».
