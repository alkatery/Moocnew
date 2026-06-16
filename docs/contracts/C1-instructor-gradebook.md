# عقد الدفعة C1 — Gradebook للمعلّم

> **الحالة:** نهائي للتنفيذ — يسلّمه `backend-dev` و`frontend-dev` حرفياً.
> **المرجع:** `docs/implementation-plan.md §4 C1` · `docs/PRD-MOOC-Platform-v2.md §5.هـ/§5.ي` · `docs/architecture/CONTEXTS.md` (Assessment + Enrollment).
> **الهدف:** نقطة `GET /api/v1/assessment/courses/{course}/gradebook` (طاقم المقرر فقط) تعيد مصفوفة طلاب × عناصر تقييم؛ أمامية: جدول درجات قابل للفرز/التصدير CSV من تبويب الاستوديو.

---

## 0. قرارات معمارية مثبتة (بعد قراءة الكود)

| البند | القرار | المصدر المقروء |
|------|--------|----------------|
| ربط `{course}` | بالـ **slug** (لا id) | `Course::getRouteKeyName() === 'slug'` |
| حساب الدرجة الكلية | **إعادة استخدام منطق** `CourseGradeService::gradeFor()` (المتوسط الموزون: أفضل محاولة لكل اختبار + نسبة التصحيح لكل واجب، وزن افتراضي 1، صفر للغائب) | `app/Contexts/Assessment/Application/CourseGradeService.php` |
| لماذا خدمة جديدة لا الاكتفاء بـ `gradeFor` | `gradeFor()` يعيد `?int` فقط (الكلية)، **لا** يعيد تفصيل المكوّنات ولا يدعم الحساب الدُفعي لكل الطلاب → خدمة تجميع جديدة `GradebookService` تعيد المصفوفة كاملة وتتجنّب N+1 | المصدر أعلاه |
| قائمة الطلاب | جدول `Enrollment` بالحالات المانحة للوصول (`active` + `completed`) عبر منطق مطابق لـ `CourseAccess::hasActiveEnrollment` | `app/Contexts/Enrollment/Application/CourseAccess.php` |
| قاعدة التخويل | مالك المقرر `instructor_id == user` **أو** `courses.review` **أو** super_admin (عبر `Gate::before`) — مطابقة نمط `AnalyticsController::courseDropoff` و`CourseAccess::isStaffFor` | `AnalyticsController.php` + `CourseAccess.php` + `CoursePolicy.php` |
| هجرات | **لا هجرة جديدة** — إعادة استخدام `quizzes/quiz_attempts/assignments/assignment_submissions/enrollments` كما هي | — |
| النقود | لا تنطبق على هذه الدفعة (درجات نسبية 0–100) | — |

---

## 1. عقد الـ API

### المسار
```
GET /api/v1/assessment/courses/{course}/gradebook
```
- يُضاف داخل مجموعة `Route::middleware('auth:sanctum')->prefix('assessment')->name('api.assessment.')` في `routes/api.php` (بجوار `courses/{course}/grade`، السطر ~342).
- الاسم: `api.assessment.courses.gradebook`.
- `{course}` = **slug** (binding ضمني).
- المتحكّم: **جديد** `App\Http\Controllers\Api\V1\Assessment\CourseGradebookController` (`__invoke`).

### التخويل (إلزامي — معيار القبول الأساسي)
يُسمح بالوصول إذا تحقّق **أحد** التالي، وإلا **403**:
1. `course.instructor_id === $user->getKey()` (مالك المقرر)، **أو**
2. `$user->can(Permission::ReviewCourses->value)` (`courses.review` — للمشرف)، **أو**
3. super_admin (يمرّ تلقائياً عبر `Gate::before` في `IdentityServiceProvider`).

> **ملاحظة تنفيذ:** هذه نفس قاعدة `CourseAccess::isStaffFor($user, $course)` حرفياً. backend-dev **يعيد استخدامها** بدل تكرار الشرط:
> ```php
> abort_unless($access->isStaffFor($request->user(), $course), 403);
> ```
> **الطالب الملتحق ممنوع** هنا (`isStaffFor` لا يشمل `hasActiveEnrollment`) — وهذا مقصود: درجات الآخرين لطاقم المقرر فقط.

### الطلب
- لا جسم (GET). لا مُدخلات إجبارية.
- **ترقيم صفحات اختياري (للفصول الكبيرة):** `?page=N&per_page=M`.
  - `per_page`: عدد صحيح 1..100، الافتراضي **50**، السقف 100.
  - عند غياب `page` يُعاد أول صفحة. الترقيم يطبَّق **على الطلاب (الصفوف)** فقط؛ رؤوس الأعمدة (`components`) تبقى كاملة وثابتة في كل صفحة.

### الاستجابة — `200 OK`
```jsonc
{
  "data": {
    "course": { "id": 12, "title": "أساسيات البرمجة", "passing_grade": 60 },
    // رؤوس الأعمدة: عناصر التقييم المشتركة، مرتبة (الاختبارات ثم الواجبات، حسب id تصاعدياً).
    "columns": [
      { "key": "quiz:3",       "type": "quiz",       "id": 3, "title": "اختبار الوحدة 1", "pass_mark": 50, "weight": 2 },
      { "key": "quiz:7",       "type": "quiz",       "id": 7, "title": "اختبار الوحدة 2", "pass_mark": 50, "weight": 1 },
      { "key": "assignment:5", "type": "assignment", "id": 5, "title": "المشروع النهائي", "pass_mark": null, "weight": 3 }
    ],
    // الصفوف: طالب واحد لكل صف، مرتّبة بالاسم تصاعدياً (collation عربي ثابت)، ثم user_id كمفكّك تعادل.
    "rows": [
      {
        "user_id": 41,
        "name": "سارة المالكي",
        "enrollment_status": "active",          // active | completed
        "overall": 73,                            // الدرجة الكلية الموزونة (مطابقة gradeFor)، أو null إن لا تقييمات
        "passed": true,                           // overall >= passing_grade (أو true إن passing_grade <= 0 / overall null)
        // الخلايا مفتاحها key العمود؛ score نسبة 0–100 أو null (لم يُرصد/لم يُسلَّم/لم يُصحَّح)
        "cells": {
          "quiz:3":       { "score": 80,   "passed": true },
          "quiz:7":       { "score": 40,   "passed": false },
          "assignment:5": { "score": null, "passed": false }
        }
      }
    ]
  },
  "meta": {                                       // يظهر فقط عند تفعيل الترقيم
    "current_page": 1,
    "last_page": 3,
    "per_page": 50,
    "total": 124                                  // إجمالي الطلاب الملتحقين
  }
}
```

### قواعد دلالية ملزِمة
- **`score` لكل خلية:** للاختبار = أفضل محاولة مُسلَّمة (`max(score)` على `submitted_at != null`) أو `null` إن لا محاولة. للواجب = `round(grade/points*100)` مقصورة 0..100 إن `graded_at != null`، وإلا `null`. (مطابق منطق `CourseGradeService` و`CourseGradeController`.)
- **`passed` لكل خلية:** للاختبار = `score !== null && score >= pass_mark`؛ للواجب = `score !== null` (لا حد نجاح للواجب — مطابق المتحكّم الحالي).
- **`overall`/`passed` للصف:** يجب أن يطابق **بايت ببايت** ما يعيده `CourseGradeService::gradeFor($user_id, $course->id)` لو استُدعي منفرداً (الصفر للغائب يدخل في المتوسط الموزون). إن لم يكن للمقرر أي اختبار/واجب → `columns: []` و`overall: null` لكل صف و`passed: true`.
- **بدون طلاب:** `rows: []`، `columns` تُعاد كما هي.

### رموز الأخطاء (موحّدة مع المنصّة)
| الحالة | الرمز | الجسم |
|-------|------|------|
| غير مصادَق | `401` | معالج Sanctum القياسي |
| ليس طاقم المقرر | `403` | `{ "message": "..." }` عبر `abort(403)` |
| المقرر غير موجود | `404` | binding القياسي |
| `per_page` خارج المدى | `422` | تحقّق قياسي (يُقصر بدل الرفض إن لزم — راجع §التحقّق) |

### حدود المعدّل
- ترث rate limiter الافتراضي لمجموعة `auth:sanctum` (لا حد خاص). قراءة فقط، لا أثر جانبي.

---

## 2. حدود الموديول (Bounded Contexts)

- **Assessment (يملك الحساب):**
  - **خدمة تجميع جديدة:** `App\Contexts\Assessment\Application\GradebookService`.
    - التوقيع المقترح:
      ```php
      public function for(Course $course, array $userIds): array
      // أو: public function forCourse(int $courseId, list<int> $userIds): GradebookMatrix
      ```
    - **مسؤوليتها:** تحميل `Quiz/Assignment` للمقرر مرة واحدة → بناء `columns`؛ تحميل `QuizAttempt`/`AssignmentSubmission` **دُفعةً** لكل `$userIds` (راجع §الأداء) → بناء `cells` و`overall` بنفس صيغة الوزن المستخدمة في `CourseGradeService`.
    - **قاعدة DRY:** صيغة المتوسط الموزون مُعرّفة اليوم داخل `CourseGradeService::gradeFor`. لتفادي التكرار: إمّا (أ) استخراج دالة حساب خالصة `weightedOverall(array $components): ?int` مشتركة بين الخدمتين، أو (ب) إبقاء `gradeFor` كما هو واستدعاء الصيغة نفسها في `GradebookService` مع تعليق يربطهما. backend-dev يختار (أ) إن لم تتضخّم الرقعة، وإلا (ب).
  - Domain لا يعرف Infrastructure: المتحكّم يمرّر الكيانات؛ الخدمة في طبقة Application تقرأ عبر نماذج Infrastructure الموجودة (نمط `CourseGradeService` الحالي).
- **Enrollment (يملك قائمة الطلاب):**
  - المتحكّم/الخدمة يجلب `user_id`+`name`+`status` للملتحقين عبر `Enrollment` (الحالات `active`/`completed` + نافذة `access_expires_at` غير منتهية)، بـ join/eager على `user` لاسم العرض. **لا** يُسرّب أي PII غير الاسم وحالة الالتحاق.
- **التخويل:** عبر `CourseAccess::isStaffFor` (Enrollment/Application) المُعاد استخدامها — لا منطق تخويل جديد.
- **لا تغيير على:** `EnrollmentServiceProvider`/`AssessmentServiceProvider` bindings الحالية (`CourseGradeProvider` يبقى كما هو).

---

## 3. الأمان والأداء (إلزامي)

### تجنّب N+1 (شرط قبول هندسي)
الحساب الساذج = (عدد الطلاب) × (عدد الاختبارات + الواجبات) استعلامات. **ممنوع.** المطلوب:
1. تحميل اختبارات المقرر مرة واحدة: `Quiz::where('course_id',$id)->get(['id','title','pass_mark','weight'])`.
2. تحميل واجبات المقرر مرة واحدة: `Assignment::where('course_id',$id)->get(['id','title','points','weight'])`.
3. **أفضل محاولة لكل (quiz,user) دُفعةً** باستعلام مجمّع واحد:
   `QuizAttempt::whereIn('quiz_id', $quizIds)->whereIn('user_id',$userIds)->whereNotNull('submitted_at')->groupBy('quiz_id','user_id')->selectRaw('quiz_id,user_id,MAX(score) as best')->get()` → فهرسة في مصفوفة `[quizId][userId] => best`.
4. **آخر تسليم مُصحَّح لكل (assignment,user) دُفعةً:**
   `AssignmentSubmission::whereIn('assignment_id',$assignmentIds)->whereIn('user_id',$userIds)->whereNotNull('graded_at')->get(['assignment_id','user_id','grade'])` → فهرسة `[assignmentId][userId] => grade`.
   (تجاهل المكرّر إن وُجد بأخذ الأحدث/الأعلى — اتساقاً مع `->first()` الحالي يكفي أي واحد مُصحَّح.)
5. بناء الصفوف من المصفوفات في الذاكرة (صفر استعلام إضافي).
> الناتج: **عدد استعلامات ثابت (~4–5)** بغض النظر عن عدد الطلاب أو التقييمات.

### الترتيب الثابت (شرط قبول للاختبار)
- `columns`: الاختبارات قبل الواجبات، كلٌّ بترتيب `id` تصاعدي.
- `rows`: بالاسم تصاعدياً ثم `user_id` تصاعدياً (مفكّك تعادل حتمي).

### الخصوصية (PDPL — تنسيق مع compliance)
- يُكشف **الاسم + حالة الالتحاق + الدرجات** فقط. **ممنوع** كشف البريد/الهاتف/أي PII آخر.
- الكشف مشروط بكون الطالب **ملتحقاً بهذا المقرر تحديداً** والمستهلك **طاقم هذا المقرر**. لا تسرّب طلاب مقرر آخر.
- لا تخزين/تسجيل (logging) لمصفوفة الدرجات.

---

## 4. الواجهة (frontend-dev)

### المكان
**تبويب جديد «درجات الطلاب»** داخل صفحة الاستوديو `frontend/src/app/studio/[slug]/page.tsx`:
- توسعة `tab` من `'curriculum' | 'assessments'` إلى `'curriculum' | 'assessments' | 'gradebook'`.
- إضافة الزر الثالث في `role="tablist"` بعنوان **«درجات الطلاب»**، و`tabpanel` جديد `id="tabpanel-gradebook"`.
- مكوّن جديد: `frontend/src/components/studio/InstructorGradebook.tsx` (props: `{ courseSlug: string }`)، يُحمَّل كسلًا عند فتح التبويب (lazy fetch داخل `useEffect` على التبويب النشط).

> تنبيه: المكوّن الحالي `frontend/src/components/Gradebook.tsx` هو **عرض الطالب لدرجاته** ويبقى كما هو دون تعديل. هذا مكوّن مستقل لطاقم المقرر.

### الأنواع (frontend/src/lib/types.ts — إضافة، لا كسر)
أعد استخدام أسلوب `CourseGrade`/`GradeComponent` الحالي، وأضف:
```ts
export interface GradebookColumn {
  key: string;                         // "quiz:3" | "assignment:5"
  type: 'quiz' | 'assignment';
  id: number;
  title: string;
  pass_mark: number | null;
  weight: number;
}
export interface GradebookCell { score: number | null; passed: boolean }
export interface GradebookRow {
  user_id: number;
  name: string;
  enrollment_status: 'active' | 'completed';
  overall: number | null;
  passed: boolean;
  cells: Record<string, GradebookCell>;   // مفاتيحها = column.key
}
export interface InstructorGradebook {
  course: { id: number; title: string; passing_grade: number };
  columns: GradebookColumn[];
  rows: GradebookRow[];
}
```

### الجدول (طلاب × عناصر)
- العمود المثبّت الأول: **اسم الطالب** + شارة حالة الالتحاق. العمود الثاني: **الدرجة الكلية** (`overall%` بلون أخضر إن `passed` وإلا محايد، `—` إن `null`). ثم عمود لكل `column` (عنوان التقييم + شارة نوعه «اختبار»/«واجب»).
- الخلية: `score%` (أخضر إن `cell.passed`، محايد وإلا)، أو `«لم يُرصد»` (`grades.notGraded`) إن `null`.
- **الفرز:** قابل بالنقر على ترويسة أي عمود (الاسم نصياً، الدرجات عددياً مع وضع `null` في النهاية دائماً)، تبديل تصاعدي/تنازلي، فرز من جانب العميل على الصفوف المُحمّلة. ترتيب الخادم الافتراضي = بالاسم.
- RTL: الجدول `dir="rtl"`، العمود المثبّت على اليمين، `scope="col"`/`scope="row"`، `aria-sort` على الترويسة النشطة (a11y — تنسيق compliance).

### التصدير CSV (من العميل)
- زر **«تصدير CSV»** يولّد ملفاً من الصفوف المعروضة:
  - الترميز **UTF-8 مع BOM** (`﻿` في بداية المحتوى) لضمان ظهور العربية في Excel.
  - الأعمدة: `اسم الطالب, حالة الالتحاق, الدرجة الكلية, <عنوان كل تقييم...>`. القيم `null` → خلية فارغة. الفاصل `,` مع تهريب القيم التي تحتوي فاصلة/سطر/اقتباس (`"..."`).
  - اسم الملف: `gradebook-<slug>.csv`. التنزيل عبر `Blob` + `URL.createObjectURL` (لا طلب شبكة).
- **إن فُعّل ترقيم الخادم:** التصدير يصدّر **الصفحة المحمّلة فقط** في v1 (موثّق للمستخدم)، أو يجلب كل الصفحات تتابعياً قبل التصدير — قرار frontend-dev حسب البساطة؛ الافتراضي: per_page=100 يكفي لمعظم الفصول → صفحة واحدة.

### الحالات
- **تحميل:** نص `common.loading`.
- **خطأ (403/شبكة):** بطاقة «تعذّر تحميل درجات الطلاب — تأكّد أنك من طاقم هذا المقرر».
- **فراغ طلاب:** «لا طلاب ملتحقين بعد.»
- **فراغ تقييمات (`columns: []`):** «لا توجد تقييمات في هذه الدورة» (`grades.empty`) — لا جدول درجات.

### مفاتيح i18n جديدة (frontend/src/i18n/dictionary.ts — ar + en)
`gradebook.tab` («درجات الطلاب»)، `gradebook.export` («تصدير CSV»)، `gradebook.student` («الطالب»)، `gradebook.overall` (إعادة استخدام `grades.overall`)، `gradebook.empty.students` («لا طلاب ملتحقين بعد»)، `gradebook.error` («تعذّر تحميل درجات الطلاب»)، `gradebook.status.active`/`gradebook.status.completed`.

---

## 5. معايير القبول (قابلة للاختبار)

### خلفي — Feature tests (qa-tester)
1. **طاقم يرى المصفوفة:** مالك المقرر → `200`، `columns` تطابق اختبارات+واجبات المقرر، `rows` تشمل كل طالب `active`/`completed`، الترتيب بالاسم.
2. **`courses.review` يرى:** مستخدم بصلاحية `courses.review` (غير المالك) → `200`. super_admin → `200`.
3. **غير الطاقم 403:** طالب ملتحق نشط → `403`؛ مستخدم عشوائي → `403`؛ مالك مقرر **آخر** → `403`.
4. **صحّة الحساب لطالب له اختبار + واجب:** طالب له أفضل محاولة 80% (وزن 2، نجاح 50) وواجب مُصحَّح 60/100→60% (وزن 3): الخلايا = `{quiz:{80,passed:true}, assignment:{60,passed:true}}`، و`overall` = `round((80*2 + 60*3)/(2+3)) = round(340/5) = 68`، ويطابق `gradeFor` لنفس الطالب.
5. **الغائب:** طالب بلا أي محاولة/تسليم → خلاياه `score:null`، و`overall` يحتسب الأصفار موزونةً (مطابق `gradeFor`).
6. **بلا تقييمات:** مقرر بلا اختبارات/واجبات → `columns:[]`، كل `overall:null`، `passed:true`.
7. **عزل المقررات:** طلاب مقرر آخر **لا** يظهرون.
8. **N+1:** الاستعلامات ثابتة العدد مع 1 و20 طالباً (تأكيد عبر `DB::enableQueryLog`/أداة العدّ) — لا تتناسب طردياً مع عدد الطلاب.

### أمامي
- جدول طلاب×عناصر يُعرض من بيانات الـ API، الفرز بالنقر يعمل (تصاعدي/تنازلي، `null` آخراً)، تصدير CSV ينتج ملفاً بـ BOM والعربية سليمة، حالات تحميل/خطأ/فراغ تظهر صحيحة، الجدول RTL وقابل للوصول لوحياً.

---

## 6. تقسيم المسؤوليات

| الدور | المهام |
|------|--------|
| **backend-dev** | `CourseGradebookController` (`__invoke`) + سطر المسار `api.assessment.courses.gradebook` + `GradebookService` (تجميع + حساب دُفعي خالٍ من N+1) + استخراج/مشاركة صيغة الوزن مع `CourseGradeService` (DRY) + التخويل عبر `CourseAccess::isStaffFor` + الترقيم الاختياري. **لا** هجرات. |
| **frontend-dev** | تبويب «درجات الطلاب» في `studio/[slug]/page.tsx` + مكوّن `InstructorGradebook.tsx` (جدول/فرز/حالات) + تصدير CSV (UTF-8 BOM) + الأنواع في `types.ts` + مفاتيح i18n (ar/en). إعادة استخدام أنماط `Gradebook.tsx`/`AssessmentsPanel`. |
| **qa-tester** | Feature tests §5 الخلفية (تخويل 200/403، صحّة الحساب مطابقة `gradeFor`، عزل المقررات، ثبات عدد الاستعلامات) + اختبار أمامي خفيف للفرز/التصدير إن توفّر إطار. |
| **compliance** | تدقيق ألّا تُكشف بيانات أفراد خارج طاقم المقرر (الاسم + الحالة + الدرجات فقط، لا بريد/هاتف)، تأكيد عزل المقررات، مراجعة RTL/a11y للجدول (`scope`, `aria-sort`, تنقّل لوحي)، وأن التصدير لا يضيف PII. |

---

## 7. ما هو خارج النطاق (لا هندسة زائدة)
- لا تحرير درجات من هذه الشاشة (قراءة فقط؛ التصحيح يبقى في تدفّق `submissions/{submission}/grade`).
- لا تصدير من الخادم (CSV من العميل فقط).
- لا تصفية/بحث متقدّم داخل الجدول في v1 (الفرز يكفي معيار القبول).
- لا رسوم بيانية/تحليلات تجميعية (تخصّ سياق Analytics، خارج C1).
