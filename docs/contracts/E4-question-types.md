# عقد الدفعة E4 — أنواع أسئلة إضافية (Question Types)

> **الحالة:** نهائي للتنفيذ — يسلّمه `backend-dev` و`frontend-dev` حرفياً.
> **المرجع:** `docs/implementation-plan.md §6 E4` · `docs/feature-matrix.csv` (الأسطر: «نوع سؤال: قائمة منسدلة» P2، «نوع سؤال: إدخال نصّي/Regex» P2، «نوع سؤال: صناديق اختيار» P2، «نوع سؤال: إدخال رقمي + تحمّل خطأ» P2) · `docs/PRD-MOOC-Platform-v2.md §5.هـ (الأسئلة موضوعية وتُصحَّح آلياً)` · `docs/architecture/CONTEXTS.md` (Assessment يملك بنك الأسئلة والتصحيح).
> **الهدف:** إضافة أربعة أنواع أسئلة جديدة **تُصحَّح آلياً بالكامل** إلى بنك أسئلة المقرر: **قائمة منسدلة (`dropdown`)**، **اختيار متعدّد الإجابات صريح (`multi_select`)**، **إدخال رقمي بهامش خطأ (`numerical`)**، و**إدخال نصّي بمطابقة Regex (`regex`)**.
> **المبدأ الحاكم لهذه الدفعة (حسّاس — يمسّ محرّك التصحيح):** **توسعة نظيفة** للتعداد `QuestionType` و`AnswerGrader` و`StoreQuestionRequest` بإضافة فروع `match` جديدة فقط — **دون لمس** فروع الأنواع القائمة (`mcq`/`true_false`/`short_answer`) ولا شكل تخزينها. كل نوع قائم يبقى مُصحَّحاً ومُؤلَّفاً ومُؤدَّى **حرفياً كما هو**. **عدم كسر الأنواع القائمة هو المعيار الأول للقبول.**

---

## 0. قرارات معمارية مثبتة (بعد قراءة الكود)

| البند | القرار | المصدر المقروء |
|------|--------|----------------|
| السياق المالك | **Assessment** حصراً. التعداد والتصحيح في `Domain`، التخزين في `Infrastructure`، التحقّق/التأليف عبر `Http`. لا يمسّ Catalog/Enrollment. | `QuestionType.php` · `AnswerGrader.php` |
| `dropdown` نوع مستقلّ أم إعادة استخدام MCQ؟ | **نوع مستقلّ في التعداد**، لكن **يعيد استخدام منطق تصحيح MCQ حرفياً** (مطابقة مجموعات معرّفات الخيارات). الفرق في التأليف (إجابة واحدة فقط) والعرض (`<select>` بدل صناديق). | `AnswerGrader::gradeMcq` · `QuestionResource` |
| `multi_select` نوع مستقلّ أم MCQ؟ | **نوع مستقلّ في التعداد**، **يعيد استخدام منطق تصحيح MCQ حرفياً**. السبب: المصفوفة تطلب «فصل نوع متعدّد الإجابات صريحاً». MCQ القائم يبقى كما هو (يقبل اختياراً واحداً أو أكثر دون تمييز في الواجهة)؛ `multi_select` يفصل النية صراحةً للطالب والمؤلّف. | `feature-matrix.csv:98` · `AnswerGrader::gradeMcq` |
| `numerical` و`regex` | **نوعان جديدان** بمنطق تصحيح جديد بالكامل في `AnswerGrader` (فرعا `match` جديدان). | `feature-matrix.csv:100-101` |
| الدرجات الجزئية (partial) | **لا — كل الأنواع «الكل أو لا شيء» (binary)** كما النظام القائم بالكامل. `is_correct` منطقية، والنقاط تُمنح كاملة أو صفراً (`QuizAttemptService:113-118`). `multi_select` = كل الصحيح مختار ولا خطأ، وإلّا صفر. **قرار صريح: لا درجات جزئية في v1** (لا يدعمها لا `quiz_attempt_answers.is_correct` ولا منطق `earnedPoints`). | `QuizAttemptService:111-124` · `quiz_attempt_answers` (عمود `is_correct boolean`) |
| تخزين إعدادات الأنواع الجديدة | **عمود `config jsonb nullable` جديد** على `question_bank` يحمل المعاملات التأليفية غير الإجابة (هامش `numerical`، أعلام `regex`). `correct` يبقى مفتاح الإجابة (cast `array`)، `choices` يبقى للخيارات. **هجرة واحدة تضيف عموداً واحداً** — لا تكفي حقول JSON القائمة وحدها لأنّ `numerical`/`regex` يحملان معاملات لا تنتمي للإجابة. | `Question.php:37-45` · `question_bank` migration |
| أمان Regex | **حرج.** النمط يُخزَّن كنصّ ويُنفَّذ بـ `preg_match` فقط (لا `e`/eval). تحقّق صحّة + حدّ طول عند **التأليف**؛ وقت تنفيذ محدود + معالجة فشل `preg_match` عند **التصحيح**. تفاصيل §6. | `AnswerGrader` (إضافة) |
| النقود | لا تنطبق (لا حقول نقدية في هذه الدفعة). | — |
| soft delete | لا تغيير — يتبع `question_bank` القائم (حذف فعلي مع تتالي الحذف). | `question_bank` migration |
| التجارة | لا علاقة — التأليف والاختبار خلف Sanctum، لا تمسّ `payments.enabled`. | — |
| Math / Drag&Drop / LaTeX / ORA | **خارج v1 صراحةً** (المصفوفة: P3/خارج النطاق). لا تُضاف للتعداد، لا تُذكر في الواجهة. أي طلب لها قرار صريح لاحق. | `feature-matrix.csv:102-106` |

### لماذا عمود `config` مستقلّ وليس حشو كل شيء في `correct`؟
`correct` = «مفتاح الإجابة» (يُرسَل في `QuestionAdminResource` ويُحجب في `QuestionResource`). دمج هامش الخطأ/أعلام Regex معه يخلط «الإجابة» بـ«إعدادات التقييم» ويعقّد حجب المفتاح عن الطالب. الفصل صريح:
- `correct` → الإجابة الصحيحة فقط (قيمة رقمية، نمط Regex، قائمة معرّفات). **محجوب عن الطالب** (`QuestionResource`).
- `config` → معاملات التقييم غير السرّية (`tolerance` للرقمي، `flags` للـRegex). يُعرَض أو يُحجب حسب الحاجة (§5).

---

## 1. توسعة التعداد `QuestionType`

`app/Contexts/Assessment/Domain/QuestionType.php` — **إضافة أربع حالات فقط** (لا حذف/تعديل للقائم):

```php
case Dropdown    = 'dropdown';
case MultiSelect = 'multi_select';
case Numerical   = 'numerical';
case Regex       = 'regex';
```

> التعداد مدعوم تلقائياً في: `Question::casts` (cast `type`)، قاعدة `Rule::enum(QuestionType::class)` في `StoreQuestionRequest:38`، و`fromValue` في `QuestionController`. لا تغيير في عمود `type string` بقاعدة البيانات.

---

## 2. مخطط البيانات

### هجرة جديدة — إضافة عمود `config`

`database/migrations/<ts>_add_config_to_question_bank_table.php` (طابع زمني بعد `2026_06_09_150000`):

```php
Schema::table('question_bank', function (Blueprint $table) {
    // معاملات تقييم غير-إجابة للأنواع الجديدة (tolerance/flags). فارغ للأنواع القائمة.
    $table->jsonb('config')->nullable()->after('correct');
});
// down(): dropColumn('config')
```

> **لا تغيير على `quiz_attempt_answers`** — `answer jsonb nullable` و`is_correct boolean` يستوعبان كل الأنواع الجديدة (نص/رقم/قائمة معرّفات). **لا تغيير على `question_bank.choices`/`correct`** سوى توسعة معناهما لكل نوع (الجدول أدناه).

### نموذج Eloquent `Question.php`

- إضافة `'config'` إلى `$fillable`.
- إضافة `'config' => 'array'` إلى `casts()`.
- لا تغيير على `'choices' => 'array'` و`'correct' => 'array'`.

### شكل `choices`/`correct`/`config` لكل نوع

| النوع | `choices` | `correct` | `config` |
|------|-----------|-----------|----------|
| `dropdown` | `[{id, text}, …]` (≥2) | `["<id واحد>"]` (معرّف خيار واحد فقط) | `null` |
| `multi_select` | `[{id, text}, …]` (≥2) | `["<id>", …]` (معرّف واحد على الأقل) | `null` |
| `numerical` | `null` | `[<عدد>]` (قيمة وحيدة، عدد صحيح أو عشري) | `{"tolerance": <عدد ≥0>}` |
| `regex` | `null` | `["<pattern>"]` (نمط وحيد، بلا فاصلات محدِّدة) | `{"flags": "i"|""}` (اختياري؛ غياب = حسّاس لحالة الأحرف) |

> **ملاحظة توافق:** `correct` يبقى **دائماً مصفوفة** (cast `array`) لكل الأنواع — حتى الرقمي والنصّي يخزَّنان كعنصر وحيد داخل مصفوفة. هذا يحافظ على ثبات الـ cast ويبسّط الـ`AnswerGrader` (لا حالات null/scalar متناثرة).

---

## 3. منطق التصحيح — توسعة `AnswerGrader`

`app/Contexts/Assessment/Domain/AnswerGrader.php` — **إضافة أربعة فروع إلى `match` فقط** + أربع دوال خاصّة. لا تغيير على `gradeMcq`/`gradeTrueFalse`/`gradeShortAnswer`.

```php
QuestionType::Dropdown    => $this->gradeMcq($correct, $answer),     // إعادة استخدام حرفية
QuestionType::MultiSelect => $this->gradeMcq($correct, $answer),     // إعادة استخدام حرفية
QuestionType::Numerical   => $this->gradeNumerical($question, $answer),
QuestionType::Regex       => $this->gradeRegex($question, $answer),
```

> **تغيير توقيع `isCorrect`:** الرقمي والنصّي يحتاجان `config` (الهامش/الأعلام) إضافةً إلى `correct`. **القرار:** تمرير `config` كوسيط رابع اختياري `array $config = []` إلى `isCorrect`، تستخدمه الفروع الجديدة فقط؛ الفروع القائمة تتجاهله (توقيعها الداخلي بلا تغيير). يحدّث `QuizAttemptService:113` ليمرّر `$question->config ?? []`.

### 3.1 `dropdown` و`multi_select` — مطابقة مجموعات (إعادة استخدام MCQ)
- منطق `gradeMcq` القائم حرفياً: مجموعة المعرّفات المُرسَلة = مجموعة المعرّفات الصحيحة (مرتّبة، فريدة، نصّية). order-independent.
- `dropdown`: الطالب يرسل معرّفاً واحداً داخل مصفوفة (`["b"]`)؛ المطابقة بمجموعة عنصر واحد.
- `multi_select`: الطالب يرسل كل ما اختاره داخل مصفوفة؛ صحّ فقط إن طابقت المجموعتان تماماً (كل الصحيح مختار **ولا** خطأ). **لا جزئي.**

### 3.2 `numerical` — رقمي بهامش خطأ
```
gradeNumerical(question, answer):
  - إن لم يكن answer رقماً قابلاً للتحويل (is_numeric) ⇒ false
  - target    = (float) correct[0]
  - tolerance = (float) config['tolerance'] ?? 0
  - given     = (float) answer
  - return abs(given - target) <= tolerance
```
- مقارنة `<=` (الحدّ شامل: قيمة على حافة الهامش تماماً = صحيحة).
- `tolerance = 0` ⇒ مطابقة تامّة. سالب غير مسموح (يُمنع عند التأليف، §4).
- يقبل أعداداً صحيحة وعشرية. الفاصلة العشرية نقطة لاتينية (`.`)؛ الواجهة تطبّع الفاصلة العربية «٫»/«,» إلى `.` قبل الإرسال.

### 3.3 `regex` — مطابقة نصّ بنمط (آمن)
```
gradeRegex(question, answer):
  - إن لم يكن answer نصّاً ⇒ false
  - إن تجاوز طول answer الحدّ (مثلاً 2000 محرف) ⇒ false  // حماية ReDoS من جهة الإدخال
  - pattern = correct[0]; flags = config['flags'] ?? ''
  - delimited = '/' . str_replace('/', '\/', pattern) . '/u' . (flags إن سُمح بها)
  - مع كاتم أخطاء: result = @preg_match(delimited, answer)
  - إن كانت result === false (فشل/تجاوز backtrack) ⇒ false  // لا استثناء، لا كسر للتصحيح
  - return result === 1
```
- **التحديد (delimiter) يُضاف هنا، لا يُخزَّن** — المؤلّف يكتب النمط فقط، فلا يحقن محدِّدات أو لاحقة `e`.
- **العلَم الوحيد المسموح: `i`** (تجاهل الحالة). `u` (Unicode) **مفروض دائماً** للعربية. أي علَم آخر يُسقَط (تحقّق التأليف §4 يقصره على `''` أو `i`).
- `pcre.backtrack_limit`/`pcre.recursion_limit` يُعتمد عليها كخطّ دفاع أخير؛ فشلها يعيد `false` بأمان (لا 500).

> **اختبار الوحدة (Domain):** كل الفروع الجديدة قابلة للاختبار معزولةً عن الإطار (مثل القائم) — مدخلات صحيحة/خاطئة/حدّية بلا قاعدة بيانات.

---

## 4. التحقّق — توسعة `StoreQuestionRequest`

`app/Http/Requests/Assessment/StoreQuestionRequest.php` — **إضافة فروع `match` في `withValidator` + قواعد `config`**. لا تغيير على فروع `validateMcq`/`validateTrueFalse`/`validateShortAnswer`.

### 4.1 قواعد `rules()` (إضافات)
```php
'config'            => ['nullable', 'array'],
'config.tolerance'  => ['nullable', 'numeric', 'min:0'],          // numerical
'config.flags'      => ['nullable', 'string', 'in:,i'],           // regex: فارغ أو i فقط
```

### 4.2 فروع `withValidator` الجديدة
```php
QuestionType::Dropdown    => $this->validateDropdown($v, $correct),
QuestionType::MultiSelect => $this->validateMultiSelect($v, $correct),
QuestionType::Numerical   => $this->validateNumerical($v, $correct),
QuestionType::Regex       => $this->validateRegex($v, $correct),
```

| النوع | قواعد التأليف (رسائل عربية) |
|------|----------------------------|
| `dropdown` | `choices` ≥2 (كـMCQ)؛ `correct` مصفوفة طولها **= 1** بالضبط، وعنصرها معرّف خيار موجود. رسالة عند ≠1: «القائمة المنسدلة تتطلّب إجابة صحيحة واحدة فقط.» |
| `multi_select` | `choices` ≥2؛ `correct` مصفوفة طولها **≥1**، وكل عناصرها معرّفات خيارات موجودة (نفس فحص MCQ). رسالة: «أسئلة متعدّدة الإجابات تتطلّب إجابة صحيحة واحدة على الأقل من الخيارات.» |
| `numerical` | `correct` مصفوفة طولها 1 وعنصرها `is_numeric`؛ `config.tolerance` رقم `≥0` (افتراض 0 عند الغياب). رسالة: «السؤال الرقمي يتطلّب قيمة عددية وهامش خطأ ≥ 0.» |
| `regex` | `correct` مصفوفة طولها 1 وعنصرها نصّ غير فارغ؛ **طول النمط ≤ 200 محرف**؛ **النمط صالح**: يُختبر بـ`@preg_match('/' . str_replace('/','\/',pattern) . '/u' . (flags), '')` ويجب ألّا يعيد `false`؛ لا لاحقة `e` (مرفوضة ضمنياً لأنّ التحديد مُولَّد محلياً ولا تُمرَّر أعلام غير `i`). رسالة عند بطلان النمط: «نمط Regex غير صالح أو طويل جداً.» |

> **منع ReDoS عند التأليف (دفاع أساسي):** بالإضافة إلى حدّ الطول، يُرفض النمط الذي يفشل `preg_match` التجريبي. الحدّ الأقصى للطول (200) والعلَم المقصور (`i`) يقلّصان مساحة الأنماط الكارثية. حدود PCRE (backtrack/recursion) خطّ الدفاع الثاني وقت التصحيح.

---

## 5. عقد الـ API

**لا مسارات جديدة.** المسارات القائمة تستوعب الأنواع الجديدة عبر حقل `type` والحقول الجديدة:

| المسار | تغيير |
|------|------|
| `POST /api/v1/assessment/courses/{course}/questions` | يقبل `type ∈ {dropdown, multi_select, numerical, regex}` + `config` (للرقمي/Regex). الصلاحية/الحدود/الأخطاء بلا تغيير. |
| `PATCH /api/v1/assessment/questions/{question}` | كأعلاه. |
| `POST /api/v1/assessment/quizzes/{quiz}/attempts` | بلا تغيير — يخدم الأسئلة الجديدة عبر `QuestionResource` الموسّع. |
| `POST /api/v1/assessment/attempts/{attempt}/submit` | بلا تغيير — `answers` يبقى `{question_id: answer}`؛ شكل `answer` لكل نوع في §5.3. |

> الصلاحية: `update` على المقرر (تأليف)، و`canParticipate` (أداء) — **بلا تغيير** (`QuestionController` · `QuizAttemptController`). الحدود (rate limits) ورموز الأخطاء الموحّدة (422 تحقّق، 403 تخويل) تُورَّث كما هي.

### 5.1 `QuestionAdminResource` (المؤلّف — يرى المفتاح)
إضافة `'config' => $this->config` إلى المخرجات (بجانب `choices`/`correct` القائمة). لا حذف.

### 5.2 `QuestionResource` (الطالب — يُحجب المفتاح)
- `correct` يبقى **محجوباً** لكل الأنواع (بلا تغيير — حرج لـ`regex`/`numerical`: كشف النمط/القيمة = كشف الإجابة).
- `config`: **يُكشف جزئياً بحذر**:
  - `numerical`: لا يُكشف `tolerance` (قد يُلمّح للدقّة المطلوبة لكنه ليس الإجابة؛ القرار v1: **حجبه** للبساطة والأمان — الطالب لا يحتاجه للإدخال).
  - `regex`: `config.flags`/`pattern` **محجوبان كلياً** (كشفهما = كشف الإجابة).
  - **القرار النهائي:** `QuestionResource` **لا يضيف `config`** — يبقى يكشف `id, type, body, choices, points` فقط. `choices` لـ`dropdown`/`multi_select` يُكشف (مطلوب للعرض)؛ `choices = null` للرقمي/النصّي.

### 5.3 شكل `answer` المُرسَل من الطالب (في خريطة `answers`)
| النوع | شكل `answer` | مثال |
|------|-------------|------|
| `dropdown` | مصفوفة معرّف واحد | `["b"]` |
| `multi_select` | مصفوفة معرّفات | `["a","c"]` |
| `numerical` | نصّ/عدد قابل للتحويل (نقطة عشرية لاتينية) | `"3.14"` أو `42` |
| `regex` | نصّ | `"الإجابة"` |

> غياب إجابة لسؤال = `null` ⇒ خطأ (`is_correct=false`) بأمان في كل الفروع (فحوص النوع تردّ `false`). مطابق سلوك الأنواع القائمة.

### 5.4 تغذية راجعة بعد التسليم (`QuizAttemptController::submit`)
بلا تغيير هيكلي — يعيد `correct` و`explanation` لكل سؤال **بعد** التسليم فقط (تغذية راجعة تكوينية، PRD §5.هـ). يصحّ لكل الأنواع الجديدة (الطالب يرى الإجابة الرقمية/النمط بعد انتهاء محاولته).

---

## 6. الأمان (Regex خصوصاً)

| الخطر | الضابط | الموضع |
|------|--------|--------|
| تنفيذ كود عبر النمط | **لا** لاحقة `e` ولا تقييم — `preg_match` فقط، التحديد مُولَّد محلياً، الأعلام مقصورة على `''`/`i`. | `AnswerGrader::gradeRegex` · `validateRegex` |
| ReDoS (نمط كارثي × إدخال طويل) | (1) حدّ طول النمط 200 عند التأليف؛ (2) حدّ طول إجابة الطالب (مثلاً 2000) يردّ `false` قبل `preg_match`؛ (3) `pcre.backtrack_limit`/`recursion_limit` خطّ أخير؛ فشل `preg_match` (`=== false`) يعيد `false` بلا استثناء. | `gradeRegex` · `validateRegex` |
| نمط غير صالح يكسر التصحيح | تحقّق صحّة عند التأليف (`@preg_match` تجريبي ≠ false)؛ وعند التصحيح كاتم أخطاء `@` + فحص `=== false`. لا 500، لا توقّف لباقي الأسئلة. | كلاهما |
| حقن في التحديد | `str_replace('/', '\/', pattern)` يهرّب الشرطة المائلة؛ المؤلّف لا يكتب محدِّدات. | كلاهما |
| الدرجات | `points` و`tolerance` تُعامَل كأرقام؛ `points` يبقى `unsignedInteger` (نقاط صحيحة)؛ `tolerance` رقم `≥0` فقط. لا نقود في هذه الدفعة. | المخطط · القواعد |
| كشف الإجابة للطالب | `correct` و`config` محجوبان في `QuestionResource` (§5.2). | `QuestionResource` |
| PDPL | لا بيانات شخصية جديدة — أسئلة محتوى تعليمي بحت. | — |

---

## 7. الواجهة الأمامية

### 7.1 الأنواع (`frontend/src/lib/types.ts`)
```ts
export type QuestionKind =
  | 'mcq' | 'true_false' | 'short_answer'
  | 'dropdown' | 'multi_select' | 'numerical' | 'regex';

export interface BankQuestion {
  id: number; type: QuestionKind; body: string;
  choices: QuestionChoice[] | null;
  correct?: unknown;
  config?: { tolerance?: number; flags?: string } | null;   // جديد
  points: number;
}
```
ونوع `RunnerQuestion` في `quiz/[id]/page.tsx` يوسَّع `type` للأنواع السبعة (لا يحمل `correct`/`config`).

### 7.2 التأليف — `AssessmentsPanel.tsx` (`QuestionForm`)
- توسعة `KIND_LABELS` بأربع تسميات عربية: `dropdown: 'قائمة منسدلة'`، `multi_select: 'اختيار متعدّد الإجابات'`، `numerical: 'إدخال رقمي'`، `regex: 'مطابقة نصّ (Regex)'`.
- حقول إضافية حسب النوع (حالة جديدة لكلّ منها):
  - **`dropdown`:** نفس واجهة خيارات MCQ، لكن اختيار الصحيح **راديو (واحد فقط)** بدل صناديق. عند التسليم: `correct = [selectedId]`، `choices = الخيارات`.
  - **`multi_select`:** نفس واجهة MCQ حرفياً (صناديق اختيار للصحيح، ≥1). عند التسليم: `correct = correctIds` (≥1)، `choices = الخيارات`.
  - **`numerical`:** حقلان رقميان `dir="ltr"`: «القيمة الصحيحة» و«هامش الخطأ ±» (افتراض 0). عند التسليم: `correct = [value]`، `config = { tolerance }`، `choices = null`.
  - **`regex`:** حقل نصّ «نمط Regex» + صندوق اختيار «تجاهل حالة الأحرف» (⇒ `flags:'i'`)، ونصّ مساعد: «بلا محدِّدات / / — اكتب النمط فقط». عند التسليم: `correct = [pattern]`، `config = { flags }`، `choices = null`. تحقّق واجهة خفيف (نمط غير فارغ، طول ≤200) قبل الإرسال؛ الخادم هو الحَكَم.
- بقية المنطق (نص/نقاط/شرح) بلا تغيير.

### 7.3 الأداء — `quiz/[id]/page.tsx` (عرض كل نوع للطالب)
- **`dropdown`:** `<select>` من `q.choices`؛ عند التغيير `answers[q.id] = [value]`. عنصر «— اختر —» افتراضي فارغ.
- **`multi_select`:** **يعيد استخدام تماماً** كتلة عرض `mcq` القائمة (صناديق اختيار، `answers[q.id]` مصفوفة معرّفات). (شرط العرض يصبح `q.type === 'mcq' || q.type === 'multi_select'`.)
- **`numerical`:** `<input type="number" dir="ltr">`؛ `answers[q.id] = e.target.value` (نصّ). تطبيع الفاصلة العربية إلى `.` قبل الإرسال.
- **`regex`:** `<input>` نصّي عادي (مثل `short_answer`)؛ `answers[q.id] = e.target.value`.
- التغذية الراجعة بعد التسليم (بطاقات `feedback`) تعمل دون تغيير (تعتمد `is_correct`/`explanation`/`correct` المُعادة).

### 7.4 i18n / RTL
- كل التسميات والرسائل عربية، الاتجاه RTL افتراضاً؛ الحقول الرقمية ونمط Regex `dir="ltr"` (محتوى لاتيني).

---

## 8. معايير القبول (قابلة للاختبار)

### 8.1 المجال — `AnswerGrader` (اختبارات وحدة، بلا قاعدة بيانات)
1. **dropdown:** `correct=["b"]`، إجابة `["b"]` ⇒ صحّ؛ `["a"]` ⇒ خطأ؛ `[]`/`null`/نصّ ⇒ خطأ.
2. **multi_select (تطابق):** `correct=["a","c"]`، إجابة `["c","a"]` ⇒ صحّ (order-independent).
3. **multi_select (لا جزئي):** `correct=["a","c"]`، إجابة `["a"]` ⇒ **خطأ**؛ `["a","c","b"]` ⇒ **خطأ** (خيار خاطئ زائد).
4. **numerical ضمن الهامش:** `correct=[100]`, `config={tolerance:2}`, إجابة `"101"` ⇒ صحّ؛ `"98"` ⇒ صحّ (حدّ شامل)؛ `"97"` ⇒ خطأ؛ `"103"` ⇒ خطأ.
5. **numerical (هامش 0):** `correct=[3.14]`, `tolerance=0`, إجابة `"3.14"` ⇒ صحّ؛ `"3.15"` ⇒ خطأ. إجابة غير رقمية `"كثير"` ⇒ خطأ.
6. **regex يطابق:** `correct=["^\\d{3}$"]`، إجابة `"123"` ⇒ صحّ؛ `"12"` ⇒ خطأ؛ `"abc"` ⇒ خطأ.
7. **regex علَم i:** `correct=["^yes$"]`, `config={flags:"i"}`, إجابة `"YES"` ⇒ صحّ؛ بلا علَم ⇒ خطأ.
8. **regex آمن:** إجابة تتجاوز حدّ الطول ⇒ خطأ بلا استثناء؛ نمط يفشل وقت التنفيذ (لو مرّ) ⇒ `false` لا 500.
9. **عدم كسر القائم:** اختبارات `mcq`/`true_false`/`short_answer` القائمة تمرّ **دون تعديل**.

### 8.2 التأليف — `StoreQuestionRequest` (اختبارات ميزة)
10. **dropdown:** `correct` بعنصرين ⇒ 422 («إجابة واحدة فقط»)؛ بعنصر واحد موجود ⇒ 201.
11. **multi_select:** `correct=[]` ⇒ 422؛ معرّف غير موجود ⇒ 422؛ ≥1 موجود ⇒ 201.
12. **numerical:** بلا قيمة عددية ⇒ 422؛ `tolerance=-1` ⇒ 422؛ قيمة + `tolerance≥0` ⇒ 201.
13. **regex:** نمط غير صالح (`"("`) ⇒ 422؛ نمط > 200 محرف ⇒ 422؛ `flags` غير `''`/`i` ⇒ 422؛ نمط صالح ⇒ 201.
14. **القائم:** إنشاء `mcq`/`true_false`/`short_answer` يبقى ناجحاً كما قبل الدفعة.

### 8.3 الأداء + الأمان (اختبارات ميزة E2E خفيفة)
15. أداء اختبار يضمّ نوعاً واحداً من كلّ نوع جديد: إجابات صحيحة ⇒ `score=100`, `passed`؛ خاطئة ⇒ `score=0`.
16. `QuestionResource` للطالب **لا يحوي** `correct` ولا `config` لأي نوع جديد (تأكيد عدم تسريب النمط/القيمة).
17. النقاط أعداد صحيحة؛ لا كسر في `QuizAttemptService` (binary scoring) لأي نوع جديد.

---

## 9. تقسيم المسؤوليات

| المكوّن | المسؤول | الملفات |
|--------|---------|--------|
| التعداد + التصحيح (Domain) | backend-dev | `QuestionType.php` (+4 حالات) · `AnswerGrader.php` (+4 فروع/دوال، توقيع `isCorrect` يقبل `config`) |
| التخزين (Infrastructure) | backend-dev | هجرة `add_config_to_question_bank` · `Question.php` (`config` في fillable+casts) |
| الربط (Application) | backend-dev | `QuizAttemptService:113` (تمرير `$question->config`) |
| التحقّق + الموارد (Http) | backend-dev | `StoreQuestionRequest.php` (+قواعد/فروع) · `QuestionController` (تمرير `config`) · `QuestionAdminResource` (+`config`) · `QuestionResource` (بلا تغيير — تأكيد الحجب) |
| الأنواع + التأليف + الأداء (Frontend) | frontend-dev | `types.ts` · `AssessmentsPanel.tsx` (`QuestionForm`) · `quiz/[id]/page.tsx` (عرض الأنواع) |
| الاختبارات | backend-dev (وحدة Domain + ميزة Http) · frontend-dev (تفاعل النماذج) | حسب §8 |

> **حدود الموديول:** المنطق كلّه داخل سياق **Assessment**. Domain (`AnswerGrader`/`QuestionType`) لا يعرف Http/Infrastructure — `config` يُمرَّر إليه كمصفوفة عادية من طبقة Application، لا يقرأ النموذج. لا تسرّب لـ Catalog/Enrollment.
