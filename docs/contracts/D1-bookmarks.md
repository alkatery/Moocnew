# عقد الدفعة D1 — العلامات المرجعية (Bookmarks)

> **الحالة:** نهائي للتنفيذ — يسلّمه `backend-dev` و`frontend-dev` حرفياً.
> **المرجع:** `docs/implementation-plan.md §5 D1` · `docs/PRD-MOOC-Platform-v2.md §5.ج (الوصول)` · `docs/architecture/CONTEXTS.md` (Learning + Enrollment).
> **الهدف:** `GET/POST/DELETE /api/v1/bookmarks` + صفحة مخصّصة `/bookmarks`. هجرة `bookmarks`. المتعلّم يحفظ دروساً ضمن مقرراته للرجوع إليها لاحقاً.
> **المبدأ الحاكم لهذه الدفعة:** **تكرار نمط `LessonNote` حرفياً** (موديل/متحكّم/مسار/تخويل/Resource/واجهة). العلامة = `LessonNote` بلا `body` وبلا `at_seconds`، بقيد تفرّد `(user, lesson)`. **لا منطق مجال جديد.**

---

## 0. قرارات معمارية مثبتة (بعد قراءة الكود)

| البند | القرار | المصدر المقروء |
|------|--------|----------------|
| السياق المالك | **Learning** (نفس سياق `LessonNote`) — العلامة بيانات تعلّم خاصة بالمتعلّم على درس | `app/Contexts/Learning/Infrastructure/Persistence/LessonNote.php` |
| الموديل | **جديد** `App\Contexts\Learning\Infrastructure\Persistence\Bookmark` — مرآة `LessonNote` بحقول `user_id, lesson_id` فقط + علاقة `lesson()` | `LessonNote.php` |
| المتحكّم | **جديد** `App\Http\Controllers\Api\V1\Learning\BookmarkController` — مرآة `LessonNoteController` | `app/Http/Controllers/Api/V1/Learning/LessonNoteController.php` |
| التخويل/الوصول | إعادة استخدام `LessonAccess::canAccess($user, $lesson)` **حرفياً** (free-preview/مالك/مراجع/ملتحق نشط أو مكتمل) — نفس قاعدة `LessonNote` | `app/Contexts/Enrollment/Application/LessonAccess.php` |
| ملكية الحذف | `abort_unless($bookmark->user_id === $request->user()->getKey(), 403)` — مرآة `LessonNoteController::destroy` | `LessonNoteController.php:55` |
| ربط `{lesson}` | بالـ **id** (binding ضمني) — كما `lessons/{lesson}/notes` | `routes/api.php:292` |
| مسار العلامة على درس | **`POST /api/v1/bookmarks` بجسم `{ lesson_id }`** (لا `lessons/{lesson}/bookmarks`) — لأن العلامة كيان عالمي للمتعلّم تُعرض في صفحة مستقلّة؛ القائمة عبر مقرّرات متعدّدة | قرار العقد (انظر §1.أ) |
| التكرار | **idempotent**: `POST` لعلامة قائمة يعيد `200` بالعلامة نفسها (لا `409`) — أبسط لزرّ toggle، لا حالة خطأ للواجهة | قرار العقد (انظر §1.ب) |
| الحذف | **`DELETE /api/v1/bookmarks/{bookmark}`** (بالـ id) — مرآة `DELETE /lesson-notes/{note}` | `routes/api.php:294` |
| soft delete | **لا** — حذف فعلي (لا سجلّ مالي/تدقيقي؛ العلامة قابلة لإعادة الإنشاء فوراً) | قرار العقد |
| النقود | لا تنطبق (لا حقول نقدية) | — |
| هجرة | **جديدة واحدة** `bookmarks` (انظر §3) | — |
| ربط `{course}` للعرض | الـ Resource يُرجع `course.slug` للملاحة إلى `/learn/{slug}` | `Course::getRouteKeyName() === 'slug'` |

---

## 1. عقد الـ API

كل المسارات داخل مجموعة `Route::middleware('auth:sanctum')` (مصادَقة إلزامية). تُضاف بجوار مسارات الملاحظات في `routes/api.php` (~السطر 294) بأسماء `api.bookmarks.*`. المتحكّم: `App\Http\Controllers\Api\V1\Learning\BookmarkController`.

```
GET    /api/v1/bookmarks                 → index    (api.bookmarks.index)
POST   /api/v1/bookmarks                 → store    (api.bookmarks.store)
DELETE /api/v1/bookmarks/{bookmark}      → destroy  (api.bookmarks.destroy)
```

### 1.أ — `GET /api/v1/bookmarks` (قائمة علامات المستخدم)

- **التخويل:** أي مستخدم مصادَق يرى **علاماته فقط** (`where('user_id', $user->id)`). لا فحص وصول هنا — الفلترة بالملكية كافية، ومن فقد التحاقه يبقى يرى علاماته (سلوك مقصود ومتسق مع `LessonNote::index` المقيَّد بالمالك).
- **الطلب:** لا جسم. لا مُدخلات إجبارية.
- **الترتيب:** الأحدث أولاً — `orderByDesc('created_at')` ثم `orderByDesc('id')` (مفكّك تعادل حتمي).
- **منع N+1 (إلزامي):** eager-load `lesson.section.course` قبل التحويل — لا استعلام لكل صف.
- **الاستجابة — `200 OK`** (عبر `BookmarkResource`):

```jsonc
{
  "data": [
    {
      "id": 14,
      "lesson": {
        "id": 87,
        "title": "مدخل إلى الحلقات",
        "type": "video"
      },
      "course": {
        "id": 12,
        "title": "أساسيات البرمجة",
        "slug": "programming-basics"   // للملاحة إلى /learn/{slug}
      },
      "created_at": "2026-06-10T08:30:00Z"
    }
  ]
}
```

> **ملاحظة سلامة العرض:** إن حُذف الدرس أو القسم أو المقرر، يُحذف صف العلامة تلقائياً عبر `cascadeOnDelete` (انظر §3) → لا حاجة لمعالجة علامات «معلّقة» بدرس مفقود.

### 1.ب — `POST /api/v1/bookmarks` (إنشاء / idempotent)

- **التخويل (إلزامي — معيار قبول أساسي):** `abort_unless(LessonAccess::canAccess($request->user(), $lesson), 403)` بعد جلب الدرس بالـ `lesson_id` — **نفس قاعدة `LessonNoteController::store` حرفياً**. المستخدم يضع علامة على درس **ضمن مقرر هو ملتحق به فقط** (أو free-preview/طاقم/مراجع).
- **التحقّق (validation):**
  ```php
  'lesson_id' => ['required', 'integer', 'exists:lessons,id'],
  ```
- **السلوك — idempotent:**
  - يُجلب الدرس → فحص `canAccess` (وإلا 403).
  - `firstOrCreate(['user_id' => $user->id, 'lesson_id' => $lesson->id])`.
  - علامة **جديدة** → `201 Created`. علامة **قائمة** (تكرار) → `200 OK` بالعلامة نفسها (لا `409`، لا خطأ).
- **الاستجابة:** نفس شكل عنصر `data` في §1.أ (عبر `BookmarkResource` مع نفس eager-load للدرس/المقرر) ملفوفاً في `{ "data": { ... } }`.

### 1.ج — `DELETE /api/v1/bookmarks/{bookmark}` (حذف فعلي)

- **التخويل:** `abort_unless($bookmark->user_id === $request->user()->getKey(), 403)` — **مرآة `LessonNoteController::destroy:55`**. لا يلمس المستخدم علامة غيره.
- **السلوك:** `$bookmark->delete()` (حذف فعلي).
- **الاستجابة:** `204 No Content` (لا جسم) — مطابق `LessonNoteController::destroy`.
- **غير موجود:** `404` (binding القياسي). علامة مستخدم آخر → `403` (لا `404` — لا نكشف وجودها، لكن نتبع نمط الملاحظات القائم الذي يعيد 403).

### رموز الأخطاء (موحّدة مع المنصّة)
| الحالة | الرمز | الجسم |
|-------|------|------|
| غير مصادَق | `401` | معالج Sanctum القياسي |
| `POST` على درس خارج التحاق المستخدم (غير free-preview/طاقم/مراجع) | `403` | `abort(403)` |
| `DELETE` لعلامة مستخدم آخر | `403` | `abort(403)` |
| `lesson_id` مفقود/غير موجود | `422` | تحقّق قياسي |
| العلامة (`{bookmark}`) غير موجودة | `404` | binding القياسي |

### حدود المعدّل
- ترث rate limiter الافتراضي لمجموعة `auth:sanctum` (لا حدّ خاص) — مطابق مسارات الملاحظات. عمليات خفيفة بأثر جانبي بسيط.

---

## 2. حدود الموديول (Bounded Contexts)

- **Learning (يملك العلامة):**
  - موديل جديد `App\Contexts\Learning\Infrastructure\Persistence\Bookmark` — مرآة `LessonNote`:
    ```php
    protected $fillable = ['user_id', 'lesson_id'];
    public function lesson(): BelongsTo { return $this->belongsTo(Lesson::class); }
    ```
    لا casts خاصة، لا حقول إضافية.
  - **لا خدمة Application جديدة** — لا منطق مجال (`firstOrCreate`/`delete` في المتحكّم مباشرةً، كما `LessonNoteController`).
- **Enrollment (يملك قاعدة الوصول):** التخويل عبر `LessonAccess::canAccess` المُعاد استخدامها — **لا منطق تخويل جديد**.
- **Catalog (يملك الدرس/القسم/المقرر):** يُقرأ فقط عبر علاقة `lesson.section.course` للـ Resource — لا تعديل.
- **Domain لا يعرف Infrastructure:** المتحكّم يمرّر كيانات Infrastructure (`Lesson`, `Bookmark`) إلى `LessonAccess` (Application) — نفس طبقية `LessonNoteController`. لا تسرّب Infrastructure إلى Domain.
- **Resource:** `App\Http\Resources\BookmarkResource` (أو تشكيل مصفوفة مباشر في المتحكّم على نمط `$note->only([...])` إن لم يوجد مجلد Resources مماثل — backend-dev يطابق النمط السائد في الكود). الشكل = §1.أ بالضبط.

---

## 3. مخطط الهجرة `bookmarks`

هجرة جديدة `database/migrations/XXXX_create_bookmarks_table.php` — مرآة بنية `lesson_notes` (انظر `2026_06_10_200000_add_video_interaction.php`) بلا `at_seconds`/`body`، **مع قيد تفرّد**:

```php
Schema::create('bookmarks', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
    $table->timestamps();
    $table->unique(['user_id', 'lesson_id']);   // علامة واحدة لكل (مستخدم، درس)
});
```

- **`unique(['user_id','lesson_id'])`:** يضمن منع التكرار على مستوى قاعدة البيانات (طبقة دفاع ثانية خلف `firstOrCreate`). يفهرس أيضاً استعلام `index` (`where user_id`) فلا حاجة لفهرس إضافي.
- **`cascadeOnDelete` على `user_id`:** حذف/تقاعد المستخدم (PDPL) يحذف علاماته تلقائياً — لا بيانات شخصية متبقية.
- **`cascadeOnDelete` على `lesson_id`:** حذف الدرس (ومن خلفه القسم/المقرر إن كانت سلاسلهم cascade) يحذف العلامة → لا علامات معلّقة.
- **`down()`:** `Schema::dropIfExists('bookmarks');` — تراجع نظيف.
- **soft delete:** لا — حذف فعلي (لا قيمة قانونية/تدقيقية للاحتفاظ).
- **النقود:** لا حقول نقدية.

---

## 4. الواجهة (frontend-dev)

### 4.أ — زر toggle «علامة مرجعية» في مشغّل التعلّم

- **المكان:** `frontend/src/app/learn/[slug]/page.tsx` — في شريط أسفل الدرس النشط بجوار زرّ «إكمال الدرس» (السطر ~143–147، حيث `<SuccessMsg>` و«إكمال الدرس»). يظهر فقط عند وجود `active` (درس مفتوح).
- **السلوك:** زر toggle يعكس الحالة:
  - غير محفوظ → نقرة تستدعي `POST /bookmarks { lesson_id: active.id }` → يصبح محفوظاً.
  - محفوظ → نقرة تستدعي `DELETE /bookmarks/{id}` → يصبح غير محفوظ.
- **معرفة الحالة الأولية لكل درس:** عند فتح درس (`open(lesson)`)، لا حاجة لاستعلام مستقل — اجلب قائمة علامات المستخدم مرّة واحدة عند تحميل الصفحة (`GET /bookmarks`) واحتفظ بـ `Set<lessonId>` + خريطة `lessonId → bookmarkId` للحذف. بديل أبسط مقبول: حالة محلية تُحدَّث من استجابة `POST`/`DELETE` فقط (تفاؤلية). frontend-dev يختار الأبسط المحقِّق للمعيار.
- **a11y (إلزامي — تنسيق compliance):**
  - `aria-pressed={isBookmarked}` على الزرّ (حالة toggle معلَنة).
  - تسمية واضحة تتبدّل: «حفظ كعلامة مرجعية» / «إزالة العلامة المرجعية» (`aria-label` أو نص مرئي).
  - أيقونة مزيّنة `aria-hidden`؛ التباين AA؛ قابل للتشغيل بلوحة المفاتيح (زرّ `<button>` أصيل).
  - رسائل النجاح/الخطأ عبر `SuccessMsg`/`ErrorMsg` (`frontend/src/components/StatusMessage.tsx`).
- **لا زرّ لدرس غير متاح:** يكفي إخفاء الزر/تعطيله للدروس التي يُتوقّع لها `403`؛ وإن وقع `403` تُعرض رسالة عبر `ErrorMsg` دون كسر.

### 4.ب — صفحة مخصّصة `/bookmarks`

- **المسار:** `frontend/src/app/bookmarks/page.tsx` (صفحة مستقلّة، client component، خلف مصادَقة كبقية صفحات المتعلّم).
- **المحتوى:** عنوان عبر `PageHeader` («علاماتي المرجعية») + قائمة بطاقات: لكل علامة → عنوان الدرس + شارة نوعه + اسم المقرر، رابط إلى `/learn/{course.slug}` (مشغّل المقرر)، وزرّ «إزالة» (`DELETE /bookmarks/{id}`، ثم تحديث القائمة محلياً).
- **الحالات (إلزامية — معيار قبول):**
  - **تحميل:** نص `common.loading`.
  - **خطأ:** بطاقة عبر `ErrorMsg` («تعذّر تحميل علاماتك المرجعية»).
  - **فراغ:** «لا علامات مرجعية بعد — احفظ دروساً من صفحة التعلّم للرجوع إليها لاحقاً.»
  - **نجاح الحذف:** إزالة فورية من القائمة (تفاؤلية أو بعد `204`).
- **RTL:** الصفحة ترث `dir="rtl"` وخط Tajawal من التخطيط؛ البطاقات/الأزرار تتبع نمط الصفحات القائمة.

### 4.ج — رابط التنقّل

- إضافة رابط `/bookmarks` في `frontend/src/components/Nav.tsx` ضمن روابط المستخدم المصادَق (بعد `/learn`، السطر ~46) بمفتاح i18n `nav.bookmarks`. يظهر فقط عند `user`.

### 4.د — الأنواع (`frontend/src/lib/types.ts` — إضافة، لا كسر)

```ts
// ---- D1: العلامات المرجعية (bookmarks) ----
/** علامة مرجعية واحدة — يطابق عنصر data في GET/POST /api/v1/bookmarks */
export interface Bookmark {
  id: number;
  lesson: { id: number; title: string; type: Lesson['type'] };
  course: { id: number; title: string; slug: string };
  created_at: string;
}
```

### 4.هـ — مفاتيح i18n (`frontend/src/i18n/dictionary.ts` — ar + en)

`nav.bookmarks` («علاماتي»)، `bookmarks.title` («علاماتي المرجعية»)، `bookmarks.empty` («لا علامات مرجعية بعد…»)، `bookmarks.error` («تعذّر تحميل علاماتك المرجعية»)، `bookmarks.add` («حفظ كعلامة مرجعية»)، `bookmarks.remove` («إزالة العلامة المرجعية»)، `bookmarks.open` («فتح الدرس»).

---

## 5. معايير القبول (قابلة للاختبار)

### خلفي — Feature tests (qa-tester)
1. **إنشاء:** متعلّم ملتحق نشط بمقرر → `POST /bookmarks {lesson_id}` لدرس فيه → `201`، صفّ في `bookmarks`، الاستجابة تحوي `lesson`+`course.slug`.
2. **عرض علامات المستخدم:** `GET /bookmarks` يعيد علامات المُصادِق فقط، الأحدث أولاً، كلّ عنصر يحمل الدرس والمقرر.
3. **حذف:** `DELETE /bookmarks/{id}` لعلامة المستخدم → `204`، يختفي الصف.
4. **منع درس خارج الالتحاق → 403:** متعلّم **غير ملتحق** (والدرس ليس free-preview) → `POST /bookmarks {lesson_id}` → `403`، لا صفّ يُنشأ.
5. **free-preview يُسمح:** درس `is_free_preview = true` → `POST` ينجح حتى بلا التحاق (مطابق `canAccess`).
6. **منع التكرار (idempotent):** `POST` مرّتين لنفس `lesson_id` → الثانية `200` (لا `409`)، **صفّ واحد فقط** في القاعدة (يثبت قيد `unique`).
7. **عزل علامات المستخدمين:** المستخدم (أ) لا يرى علامات (ب) في `index`؛ ومحاولة (أ) `DELETE` علامة (ب) → `403`، تبقى علامة (ب) قائمة.
8. **تتالي الحذف:** حذف الدرس يحذف صفوف العلامات المرتبطة (`cascadeOnDelete`) — لا علامة معلّقة.
9. **تحقّق المدخل:** `POST` بلا `lesson_id` أو بـ `lesson_id` غير موجود → `422`.

### أمامي — Vitest (qa-tester)
- زرّ toggle يعكس الحالة و`aria-pressed` يتبدّل بعد النجاح؛ صفحة `/bookmarks` تعرض الحالات الأربع (تحميل/خطأ/فراغ/قائمة)؛ الحذف يُزيل البطاقة؛ روابط الدروس تشير إلى `/learn/{slug}`.

---

## 6. تقسيم المسؤوليات

| الدور | المهام |
|------|--------|
| **backend-dev** | هجرة `bookmarks` (FKs cascade + `unique(user,lesson)`) + موديل `Bookmark` (مرآة `LessonNote`) + `BookmarkController` (`index`/`store`/`destroy`) على نمط `LessonNoteController` + `BookmarkResource` (شكل §1.أ، eager-load `lesson.section.course`) + 3 أسطر مسار `api.bookmarks.*`. التخويل عبر `LessonAccess::canAccess` وفحص الملكية — **لا منطق جديد**. |
| **frontend-dev** | زرّ toggle «علامة مرجعية» في `learn/[slug]/page.tsx` (`aria-pressed`) + صفحة `app/bookmarks/page.tsx` (4 حالات) + رابط `nav.bookmarks` في `Nav.tsx` + النوع `Bookmark` في `types.ts` + مفاتيح i18n (ar/en). إعادة استخدام أنماط `LessonInteraction`/`PageHeader`/`StatusMessage`. |
| **qa-tester** | Feature tests §5 (إنشاء/عرض/حذف، 403 لدرس خارج الالتحاق، free-preview مسموح، idempotent بصفّ واحد، عزل المستخدمين، cascade، 422) + اختبار أمامي للـ toggle والحالات. |
| **compliance** | تأكيد **عزل بيانات المستخدم** (لا تسرّب علامات/دروس مستخدم لآخر؛ `index` مقيّد بالمالك؛ `delete` يفحص الملكية) + PDPL (تتالي الحذف عند تقاعد المستخدم → لا بيانات متبقية، تقليل البيانات: لا حقول زائدة) + a11y زرّ toggle (`aria-pressed`/تسمية متبدّلة/تباين AA/لوحة مفاتيح) + RTL لصفحة `/bookmarks`. |

---

## 7. ما هو خارج النطاق (لا هندسة زائدة)
- لا تصنيف/مجلّدات/وسوم للعلامات (قائمة مسطّحة تكفي معيار القبول).
- لا ملاحظة نصية مع العلامة (هذا دور `LessonNote` القائم — العلامة إشارة سريعة فقط).
- لا ترقيم صفحات لـ `GET /bookmarks` في v1 (حجم متوقّع صغير؛ يُضاف لاحقاً عند الحاجة).
- لا مزامنة/إشعار عند تحديث الدرس المعلَّم.
- لا علامة على مستوى المقرر/القسم — على مستوى الدرس فقط (مطابق هدف الدفعة).
