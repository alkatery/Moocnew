# عقد الدفعة C3 — البريد الجماعي وإعلانات المقرر

> **الحالة:** نهائي للتنفيذ — يسلّمه `backend-dev` (رئيسي) و`frontend-dev` (نماذج الإرسال) حرفياً.
> **المرجع:** `docs/implementation-plan.md §4 C3` · `docs/PRD-MOOC-Platform-v2.md §5.ط` (مركز التفضيلات) · `docs/architecture/CONTEXTS.md` (Notification + Enrollment + Catalog).
> **الهدف:** يبثّ طاقم المقرر **إعلاناً** يظهر للمتعلّمين الملتحقين، ويُرسل **بريداً جماعياً** عبر الطوابير يحترم opt-out. القبول: إرسال مُطابور موثّق (مُدقَّق)؛ يحترم opt-out؛ مختبَر. تخويل: طاقم المقرر فقط.

---

## 0. حقيقة مرصودة بعد قراءة الكود — البنية التحتية للإشعارات جاهزة، نُعيد استخدامها

| البند | الحالة المرصودة | المصدر المقروء |
|------|------------------|----------------|
| مركز التفضيلات (نوع×قناة) | جاهز: opt-out افتراضي مُفعّل؛ صفّ في `notification_preferences` يعطّل زوج (type, channel). الغياب = مُفعّل | `NotificationPreferences.php` + هجرة `notification_preferences` |
| احترام opt-out في القنوات | **آلي**: كل إشعار يرث `PreferenceAwareNotification`؛ `via()` يمرّر القنوات المرشَّحة عبر `enabledChannels($user, $type, $candidates)` فيُسقط أي قناة عطّلها المستخدم | `PreferenceAwareNotification::via()` |
| أنواع الإشعارات | enum `NotificationType` (6 قيم حالياً: `enrollment_confirmed`…). **لا يوجد نوع لإعلان/بريد مقرر** | `NotificationType.php` |
| القنوات المدعومة | enum `NotificationChannel`: `database`, `mail`, `sms`, `whatsapp`, `push` | `NotificationChannel.php` |
| قناة داخل التطبيق | `database` — صندوق وارد المتعلّم عبر `GET /api/v1/notifications` (`NotificationController::index`) يقرأ `notifications` (جدول Laravel القياسي، `data` JSON) | `NotificationController.php` + هجرة `notifications` |
| نمط إشعار قائم | `EnrollmentConfirmedNotification` (يرث `PreferenceAwareNotification`، `toMail`/`toSms`/`toArray`) — **هو القالب الحرفي** للأصناف الجديدة | `EnrollmentConfirmedNotification.php` |
| **الطوابير** | **مرصد حرج:** الإشعارات القائمة **لا تطبّق `ShouldQueue`** (متزامنة). Horizon **مثبَّت** (`config/horizon.php`). البريد الجماعي **يجب** أن يُطابور | `EnrollmentConfirmedNotification.php` (لا `implements ShouldQueue`) + `config/horizon.php` |
| جلب الملتحقين النشطين | نمط جاهز في `GradebookService::enrolledStudents`: `Enrollment` بحالة `Active|Completed` + نافذة وصول غير منتهية، join `users` | `GradebookService.php:198` |
| فحص حالة الملتحق | `CourseAccess::hasActiveEnrollment` (Active/Completed + غير منتهٍ) | `CourseAccess.php:20` |
| التخويل طاقم المقرر | `CourseAccess::isStaffFor($user, $course)` = المالك (`instructor_id`) أو `courses.review` (+super_admin عبر `Gate::before`) — مطابق C1/C2 | `CourseAccess.php:43` |
| التدقيق | `ActivityLogger::log($event, $causer, $subject, $properties)` يكتب `activity_logs` (append-only) | `ActivityLogger.php:21` |
| ربط `{course}` | بالـ **slug** (binding ضمني) | `Course::getRouteKeyName()` |
| **إعلانات المقرر** | **لا جدول ولا مفهوم قائم.** `news_posts` يخصّ سياق Content (أخبار المنصّة) ولا علاقة له بإعلان مقرر | بحث `announcement` → لا نتيجة في app؛ `news_posts` في Content |

> **الخلاصة:** احترام opt-out **مجّاني** بمجرّد وراثة `PreferenceAwareNotification`. العمل الجديد: (1) نوعا إشعار جديدان + صنفان `ShouldQueue`، (2) جدول `course_announcements` بسيط للظهور الدائم للمتعلّم، (3) خدمة بثّ جماعي مُطابورة، (4) متحكّمان + تخويل + rate limit + تدقيق.

---

## 1. قرارات معمارية مثبتة

| البند | القرار | التبرير |
|------|--------|---------|
| **هل نخزّن الإعلان؟** | **نعم — جدول `course_announcements` + بثّ إشعار `database`** | الهدف «يظهر للمتعلّمين الملتحقين» يتطلّب ظهوراً **دائماً** على صفحة المقرر (لا يعتمد على وارد إشعار قد يُقرأ/يُمسح). الإشعار `database` تنبيه فوري؛ الجدول هو مصدر الحقيقة للعرض. |
| **هل نخزّن البريد الجماعي؟** | **لا جدول للرسائل** — يُطابور فوراً ويُدقَّق ملخّصه فقط | الرسالة الجماعية حدث إرسال لا كيان دائم؛ تخزين نصوص بريدية لكل مستلم تضخّم بلا قيمة v1. التدقيق (`bulk_email.sent` + العدد) كافٍ للمساءلة. |
| **النوعان الجديدان** | `course_announcement` و`course_bulk_email` يُضافان إلى `NotificationType` | يتيحان للمتعلّم opt-out **منفصلاً** لكلٍّ في مركز التفضيلات (PDPL/مكافحة سبام). |
| **قنوات كل نوع** | **الإعلان:** `[database, mail]` فقط (لا SMS/واتساب — تنبيه تعليمي خفيف). **البريد الجماعي:** `[mail]` فقط | البريد الجماعي قناته البريد بحكم تعريفه؛ الإعلان يظهر داخل التطبيق + بريد اختياري. قصر القنوات يمنع إزعاج SMS مكلفاً ويبسّط v1. |
| **«الفئة» (audience)** | v1: **كل الملتحقين النشطين فقط** (`active`)؛ معامل `audience` اختياري بقيمة واحدة مدعومة `all_active` (الافتراضي). التقسيم المتقدّم (حسب التقدّم/الحالة/القسم) **مؤجَّل** | لا هندسة زائدة؛ المصفوفة لا تتطلّب تقسيماً. ترك `audience` في العقد يفتح التوسعة دون كسر. |
| **«النشط»** | `Enrollment.status ∈ {Active, Completed}` + نافذة وصول غير منتهية (مطابق `enrolledStudents`/`hasActiveEnrollment`) | اتّساق مع تعريف «الوصول الفعّال» في كل المنصّة. `Completed` يبقى مستلِماً (لا يزال متعلّماً للمقرر). |
| **الطوابير** | الصنفان الجديدان `implements ShouldQueue` + `Queueable`. البثّ يكرّر على المستلمين ويستدعي `Notification::send`/`$user->notify` لكلٍّ → كل إرسال يُدفع للطابور | الهدف يشترط الطابور صراحةً؛ منع إرسال متزامن لمئات في الطلب (timeout/فشل جزئي). |
| **الطابور المخصّص** | الصنفان على طابور `notifications` (`onQueue('notifications')`) | عزل البريد الجماعي عن طوابير حسّاسة للزمن؛ Horizon يوازن. |
| **العزل بين المستلمين** | إرسال **فردي** لكل مستخدم (`$user->notify(...)` لكلٍّ) — لا BCC/قائمة جماعية | كل مستلم يحصل على رسالته الخاصّة؛ **لا يُكشف بريد مستلم لآخر** إطلاقاً (PDPL + لا حقل to مشترك). |
| النقود | لا تنطبق | — |

---

## 2. مخطّط البيانات (هجرة جديدة واحدة)

### جدول `course_announcements`
`database/migrations/XXXX_create_course_announcements_table.php`

| العمود | النوع | قيود/ملاحظات |
|-------|------|--------------|
| `id` | `id` (bigint) | مفتاح أساسي |
| `course_id` | `foreignId` → `courses` | `constrained()->cascadeOnDelete()` (حذف المقرر يحذف إعلاناته) |
| `author_id` | `foreignId` → `users` | `constrained()` — مُنشئ الإعلان (طاقم المقرر)؛ **لا** cascade (نُبقي الإعلان إن أُخفيت هوية المؤلف — راجع PDPL §6) |
| `title` | `string` (255) | مطلوب |
| `body` | `text` | مطلوب — نصّ عادي (لا HTML خام؛ يُعرض كنصّ، انظر §6 الأمان) |
| `created_at` / `updated_at` | `timestamps` | — |

- **الفهارس:** `index('course_id')` (جلب إعلانات مقرر مرتّبة `latest`). فهرس مركّب `['course_id', 'created_at']` مقبول للترتيب الزمني.
- **soft-delete:** **لا** (v1: الإعلان دائم؛ الحذف خارج النطاق — انظر §8). إن طُلب لاحقاً يُضاف `softDeletes`.
- **تراجع نظيف:** `down()` → `Schema::dropIfExists('course_announcements')`.
- **النموذج:** `App\Contexts\Notification\Infrastructure\Persistence\CourseAnnouncement` (`$fillable = ['course_id','author_id','title','body']`)، علاقتا `course()` و`author()`.

> **قرار الموضع:** الجدول والنموذج في سياق **Notification** (يملك البثّ والإعلانات). الإعلان كيان تواصل لا كيان كتالوج.

### تعديل `NotificationType` (لا هجرة — enum)
إضافة حالتين + تسميتيهما العربيتين:
```
case CourseAnnouncement = 'course_announcement';   // label: 'إعلانات المقرر'
case CourseBulkEmail    = 'course_bulk_email';     // label: 'رسائل المعلّم'
```
> أثرها: تظهران آلياً في مصفوفة مركز التفضيلات (`NotificationPreferences::matrix`)، فيستطيع المتعلّم تعطيل قناة `mail` لأيٍّ منهما → احترام opt-out مضمون عبر `via()`. **لا هجرة:** `notification_preferences` لا تخزّن إلا صفوف التعطيل؛ الأنواع الجديدة مُفعّلة افتراضياً.

---

## 3. عقود الـ API

> الموضع المقترح في `routes/api.php`: مجموعة جديدة `auth:sanctum` ببادئة `courses` واسم `api.courses.communications.*` (أو ضمن مجموعة Assessment القائمة — قرار backend-dev؛ المهم البادئة `/api/v1/courses/...`). التخويل **داخل المتحكّم** عبر `CourseAccess::isStaffFor` (مطابق `CourseGradebookController`).

### 3.أ — نشر إعلان مقرر
```
POST /api/v1/courses/{course}/announcements        (auth:sanctum, طاقم المقرر، throttle:30,1)
```
- **{course}:** binding بالـ slug.
- **التخويل:** `abort_unless($access->isStaffFor($request->user(), $course), 403);`
- **الطلب** (`StoreAnnouncementRequest`):

| الحقل | التحقّق |
|------|---------|
| `title` | `required` · `string` · `max:255` |
| `body` | `required` · `string` · `max:5000` |

- **السلوك:** يُنشئ صفّ `course_announcements`؛ يبثّ `CourseAnnouncementNotification` (مُطابور) لكل ملتحق نشط عبر قنوات النوع المُفعّلة (`database`, `mail`)؛ يُدقّق `announcement.sent`.
- **الاستجابة `201`:**
```jsonc
{
  "data": {
    "id": 12,
    "course_id": 7,
    "title": "موعد الاختبار النهائي",
    "body": "سيُعقد الاختبار يوم الأحد…",
    "author": { "id": 3, "name": "أ. خالد" },
    "created_at": "2026-06-15T10:00:00+00:00"
  },
  "recipients_queued": 84          // عدد المتعلّمين الذين دُفعت لهم مهام إشعار (قبل فلترة القناة الفردية)
}
```
> `recipients_queued` = عدد الملتحقين النشطين الذين استُدعي `notify` لهم. الفلترة الفردية للقناة (opt-out) تحدث داخل المهمة المُطابورة (`via()`)، لذا العدد يمثّل المستهدَفين لا المُسلَّم فعلياً بكل قناة.

### 3.ب — قائمة إعلانات المقرر (لعرض الطاقم والمتعلّم)
```
GET /api/v1/courses/{course}/announcements        (auth:sanctum)
```
- **التخويل:** طاقم المقرر **أو** متعلّم له التحاق نشط — `abort_unless($access->canParticipate($request->user(), $course), 403);` (`CourseAccess::canParticipate` قائم).
- **الاستجابة `200`:** `{ "data": Announcement[], "meta": { current_page, last_page, per_page, total } }` (Resource collection مُرقّمة 15، مرتّبة `latest()`)، كل عنصر بشكل `data` أعلاه دون `recipients_queued`.

### 3.ج — إرسال بريد جماعي
```
POST /api/v1/courses/{course}/bulk-email        (auth:sanctum, طاقم المقرر، throttle:5,1)
```
- **{course}:** binding بالـ slug.
- **التخويل:** `abort_unless($access->isStaffFor($request->user(), $course), 403);`
- **الطلب** (`SendBulkEmailRequest`):

| الحقل | التحقّق |
|------|---------|
| `subject` | `required` · `string` · `max:255` |
| `body` | `required` · `string` · `max:5000` |
| `audience` | `nullable` · `string` · `in:all_active` (الافتراضي `all_active`) |

- **السلوك:** يجلب الملتحقين النشطين؛ يبثّ `CourseBulkEmailNotification` (مُطابور، قناة `mail` فقط) لكلٍّ **فردياً**؛ يُدقّق `bulk_email.sent`. **لا** يُخزَّن نصّ الرسالة.
- **الاستجابة `202 Accepted`** (لأنّ التسليم لاحق عبر الطابور):
```jsonc
{
  "recipients_queued": 84,
  "audience": "all_active"
}
```
> **`202` لا `200`:** يوصّف أنّ الإرسال **مُطابور** لا منجَز — مطابق لمعيار القبول «إرسال مُطابور موثّق».

### رموز الأخطاء (موحّدة)
| الحالة | الرمز |
|-------|------|
| غير مصادَق | `401` |
| ليس طاقم المقرر (نشر/بريد جماعي) / لا التحاق نشط (قائمة الإعلانات) | `403` |
| المقرر غير موجود | `404` |
| `title`/`subject`/`body` مفقود أو يتجاوز الطول، أو `audience` غير مدعوم | `422` |
| تجاوز حدّ المعدّل | `429` |

### حدود المعدّل (منع إساءة الإرسال الجماعي)
- **الإعلان:** `throttle:30,1` (30/دقيقة لكل مستخدم) — فعل خفيف.
- **البريد الجماعي:** `throttle:5,1` (5/دقيقة لكل مستخدم) — **حاجز مكافحة سبام**؛ البريد الجماعي مكلِف وقابل للإساءة.
- النمط مطابق `throttle:6,1` المستخدم في مسارات الإرسال البريدي القائمة (`routes/api.php:91,103`).

---

## 4. الموديول (backend-dev — رئيسي)

### حدود السياق
- **Notification (يملك المنطق):** الأصناف، الخدمة، الجدول/النموذج، الأنواع — كلها داخل `app/Contexts/Notification`. Domain (`NotificationType`/`NotificationChannel`) لا يعرف Infrastructure. الخدمة (Application) تنسّق؛ النماذج والقنوات (Infrastructure) تنفّذ.
- **Enrollment:** جلب المستلمين والتخويل عبر `CourseAccess` (Application قائم) — يُعاد استخدامه، لا منطق التحاق جديد.
- **Identity:** التدقيق عبر `ActivityLogger`.
- **Catalog:** `Course` (binding + `instructor_id`) للقراءة فقط.
- راية `payments.enabled` **لا تمسّ** هذه الدفعة (التواصل لا يعتمد على التجارة).

### الأصناف الجديدة
1. **`Domain/NotificationType`** — إضافة الحالتين + التسميتين (§2).
2. **`Infrastructure/Notifications/CourseAnnouncementNotification`** (يرث `PreferenceAwareNotification`، `implements ShouldQueue`, `use Queueable`):
   - `type(): NotificationType::CourseAnnouncement`
   - `candidateChannels(): ['database','mail']`
   - الباني: `(string $courseTitle, int $announcementId, string $title, string $body)`
   - `toMail()`: subject = «إعلان جديد في {courseTitle}»، body = `$title` + `$body` (نصّ، لا HTML خام).
   - `toArray()`: `{ type, course_title, announcement_id, title }` (يُغذّي وارد `database`؛ `announcement_id` للربط بصفحة المقرر).
   - `onQueue('notifications')` في الباني.
3. **`Infrastructure/Notifications/CourseBulkEmailNotification`** (نفس الوراثة + `ShouldQueue`):
   - `type(): NotificationType::CourseBulkEmail`
   - `candidateChannels(): ['mail']`
   - الباني: `(string $courseTitle, string $subject, string $body)`
   - `toMail()`: subject = `$subject`، body = `$body` + تذييل «أُرسلت من فريق دورة: {courseTitle}». **لا** `toArray`/`toSms` (بريد فقط).
   - `onQueue('notifications')`.
4. **`Infrastructure/Persistence/CourseAnnouncement`** — النموذج (§2).
5. **`Application/CourseBroadcaster`** (خدمة البثّ — الجوهر):
   - `announce(Course $course, User $author, string $title, string $body): int` — يُنشئ الإعلان، يكرّر على الملتحقين النشطين، `$user->notify(new CourseAnnouncementNotification(...))`، يُعيد عدد المستلمين.
   - `bulkEmail(Course $course, string $subject, string $body, string $audience = 'all_active'): int` — يكرّر على الملتحقين النشطين، `$user->notify(new CourseBulkEmailNotification(...))`، يُعيد العدد.
   - **جلب المستلمين:** استعلام `Enrollment` (Active/Completed + نافذة غير منتهية) join `users`، **chunk** (مثلاً `chunkById(500)`) لتفادي تحميل آلاف المستخدمين دفعةً (التكرار يدفع مهاماً مُطابورة، خفيف الذاكرة). يُعاد استخدام شرط `enrolledStudents`/`hasActiveEnrollment` نفسه.
6. **متحكّمان رفيعان** (طبقة Http):
   - `App\Http\Controllers\Api\V1\Notification\CourseAnnouncementController` (`store` + `index`).
   - `App\Http\Controllers\Api\V1\Notification\CourseBulkEmailController` (`__invoke`).
   - كلاهما: تخويل عبر `CourseAccess`، تفويض لـ `CourseBroadcaster`، تدقيق عبر `ActivityLogger`، إرجاع JSON المتعاقد.
7. **`StoreAnnouncementRequest` / `SendBulkEmailRequest`** (FormRequest، التحقّق §3؛ `authorize()` عبر `isStaffFor`).
8. **المسارات** في `routes/api.php` (§3) مع `throttle`.

### التدقيق (`activity_logs`)
- بعد النشر: `$activity->log('announcement.sent', $request->user(), $announcement, ['recipients' => $count, 'course_id' => $course->id]);`
- بعد البريد الجماعي: `$activity->log('bulk_email.sent', $request->user(), $course, ['recipients' => $count, 'audience' => $audience]);` (الموضوع = المقرر؛ لا كيان رسالة).
- **لا** يُسجَّل نصّ البريد في التدقيق (تقليل البيانات — العدد والموضوع/الجمهور كافيان).

### الطوابير
- الصنفان `ShouldQueue` → كل `notify()` يدفع مهمة. Horizon يلتقطها على طابور `notifications`.
- **مهم:** السلوك المتزامن الحالي للإشعارات الأخرى **لا يتغيّر** (لا نُحوّل `EnrollmentConfirmedNotification` إلخ — خارج النطاق).
- إعداد `QUEUE_CONNECTION` (redis/database) شرط تشغيل؛ **توثيق:** عامل Horizon يجب أن يعمل لتسليم البريد الجماعي.

---

## 5. الواجهة (frontend-dev — نماذج الإرسال)

### المكان
**تبويب جديد «التواصل» (`communications`)** في صفحة الاستوديو `frontend/src/app/studio/[slug]/page.tsx`:
- يُضاف المفتاح إلى `useState<… | 'communications'>` وإلى مصفوفة التبويبات (نمط WAI-ARIA القائم: `role="tab"`/`aria-controls`/تنقّل لوحة المفاتيح — **لا يُكسر**).
- `tabpanel-communications` يحوي مكوّناً جديداً يُحمَّل كسلاً عند فتح التبويب فقط: `frontend/src/components/studio/CommunicationsPanel.tsx`.

> لا تبويب لكل وظيفة: «التواصل» يجمع قسمين (إعلان + بريد جماعي) على غرار اتّساق C1/C2.

### المكوّن `CommunicationsPanel.tsx` — قسمان
1. **«نشر إعلان»:** حقل `title` (input) + `body` (textarea) + زر «نشر». عند النجاح (`201`): رسالة نجاح «نُشر الإعلان — أُبلغ {recipients_queued} متعلّماً» + إعادة تحميل قائمة الإعلانات أدناه.
2. **قائمة الإعلانات السابقة:** تستهلك `GET …/announcements` (مُرقّمة)، تعرض العنوان/النصّ/المؤلّف/التاريخ.
3. **«إرسال بريد جماعي»:** حقل `subject` + `body` + (اختياري) منتقي `audience` (قيمة واحدة «كل المتعلّمين النشطين» — معطّل/ثابت في v1) + زر «إرسال». عند النجاح (`202`): تأكيد «أُرسل البريد إلى {recipients_queued} متعلّماً عبر الطابور».
   - **تأكيد قبل الإرسال:** `window.confirm` («سيُرسَل بريد إلى جميع المتعلّمين النشطين (تقديري {n}). متابعة؟») — حاجز ضد الإرسال العَرَضي.

### الأنواع (`frontend/src/lib/types.ts` — إضافة)
```ts
export interface CourseAnnouncement {
  id: number;
  course_id: number;
  title: string;
  body: string;
  author: { id: number; name: string };
  created_at: string;
}
```

### الحالات
- **تحميل:** `common.loading`. **خطأ (403/422/شبكة):** `ErrorMsg` (`role="alert"`) برسالة من الـ API (422 يعرض رسالة التحقّق). **فراغ (لا إعلانات):** «لا إعلانات بعد». **نجاح:** `SuccessMsg` (`role="status"`).
- **429:** رسالة «أرسلت كثيراً من الرسائل — انتظر دقيقة» (حاجز المعدّل).

### a11y / RTL (تنسيق compliance)
- كل حقل معنون (`<label htmlFor>`)؛ الأخطاء `role="alert"`، النجاح `role="status"` (نمط `AssessmentsPanel`/`InstructorGradebook`).
- التبويب الجديد يحافظ على نمط التبويبات القائم (`tabIndex`/`aria-selected`/أسهم لوحة المفاتيح).
- تباين AA؛ خط Tajawal؛ `dir` موروث.

### مفاتيح i18n (`frontend/src/i18n/dictionary.ts` — ar + en)
`comm.tab` («التواصل»)، `comm.announce.title` («نشر إعلان»)، `comm.announce.fieldTitle` («عنوان الإعلان»)، `comm.announce.body` («نصّ الإعلان»)، `comm.announce.submit` («نشر»)، `comm.announce.success` («نُشر الإعلان وأُبلغ المتعلّمون»)، `comm.announce.empty` («لا إعلانات بعد»)، `comm.email.title` («إرسال بريد جماعي»)، `comm.email.subject` («موضوع البريد»)، `comm.email.body` («نصّ البريد»)، `comm.email.submit` («إرسال»)، `comm.email.confirm` («سيُرسَل بريد إلى جميع المتعلّمين النشطين. متابعة؟»)، `comm.email.queued` («أُرسل عبر الطابور إلى المتعلّمين»)، `comm.audience.allActive` («كل المتعلّمين النشطين»)، `comm.error` («تعذّر الإرسال — تأكّد أنك من طاقم هذا المقرر»)، `comm.rateLimited` («أرسلت كثيراً — انتظر دقيقة»).

---

## 6. الأمان والامتثال

- **التخويل:** نشر الإعلان والبريد الجماعي حصراً لطاقم المقرر (`isStaffFor`) → `403` لغيره (متعلّم/زائر). قائمة الإعلانات للطاقم أو متعلّم نشط (`canParticipate`).
- **احترام opt-out (PDPL §5.ط + مكافحة سبام):** كلا الصنفين يرث `PreferenceAwareNotification`؛ `via()` يُسقط أي قناة عطّلها المستخدم للنوع. متعلّم عطّل `mail` لنوع `course_bulk_email` **لا يستقبل البريد**. النوعان الجديدان منفصلان في مركز التفضيلات.
- **عدم كشف عناوين المستلمين:** إرسال **فردي** لكل مستخدم (`$user->notify`) — لا `to`/BCC مشترك، لا قائمة. لا يرى متعلّم بريد آخر إطلاقاً.
- **تقليل البيانات:** لا تخزين نصوص البريد؛ التدقيق يحفظ العدد/الجمهور فقط. `course_announcements` يخزّن المحتوى التعليمي العلني فقط (لا بيانات شخصية).
- **منع الحقن/XSS:** `title`/`body` تُعرض كنصّ (escaped) في الواجهة والبريد — **لا** يُصيَّر HTML خام من إدخال المعلّم.
- **مكافحة الإساءة:** `throttle:5,1` على البريد الجماعي + تأكيد الواجهة + تدقيق كامل (من أرسل، متى، لكم).
- **الاحتفاظ:** الإعلانات تتبع دورة حياة المقرر (cascade عند حذفه). إخفاء هوية المؤلف لا يحذف الإعلان (`author_id` بلا cascade) — يبقى المحتوى، تنفصل الهوية (مطابق A3).

---

## 7. معايير القبول (قابلة للاختبار)

### خلفي (qa-tester — Pest Feature/Unit)
1. **الطابور يستقبل المهام:** `Queue::fake()` (أو `Notification::fake()`)؛ نشر إعلان/بريد جماعي على مقرر به 3 ملتحقين نشطين → تُدفع مهمة إشعار لكل ملتحق نشط (`assertSentTo`/`assertPushed` ×3)؛ الصنفان `implements ShouldQueue`.
2. **opt-out يُحترم:** متعلّم عطّل قناة `mail` لنوع `course_bulk_email` (`NotificationPreferences::set(... false)`) → عند البثّ، `via()` لذلك المستخدم لا يُرجع `mail` (لا بريد له)؛ المتعلّمون الآخرون يستقبلون. (تأكيد على مستوى `via()` أو `Notification::assertSentTo` بقناة.)
3. **التخويل 403:** متعلّم عادي (غير طاقم) يطلب `POST …/announcements` و`POST …/bulk-email` → `403`. مالك المقرر / `courses.review` → `201`/`202`.
4. **التدقيق يُكتب:** بعد النشر، `activity_logs` فيه `announcement.sent` بـ `properties.recipients = N`؛ بعد البريد، `bulk_email.sent` بـ `recipients`/`audience`. **لا** يحوي نصّ البريد.
5. **الإعلان يُخزَّن ويظهر:** `POST …/announcements` يُنشئ صفّ `course_announcements`؛ `GET …/announcements` يُعيده لطاقم المقرر **ولمتعلّم نشط** (`200`)، ولغير الملتحق غير الطاقم → `403`.
6. **حصر المستلمين بالنشطين:** ملتحق `Expired`/`Refunded` أو منتهي الوصول **لا** يُرسَل له (`recipients_queued` يستثنيه).
7. **التحقّق:** `title`/`subject`/`body` مفقود → `422`؛ `body` يتجاوز 5000 → `422`؛ `audience` غير `all_active` → `422`.
8. **حدّ المعدّل:** تجاوز `throttle:5,1` على البريد الجماعي → `429`.
9. **العزل:** لا BCC/قائمة مشتركة — كل إشعار موجَّه لمستخدم واحد (يُتحقَّق ضمنياً عبر `assertSentTo` لكل مستخدم منفصلاً).

### أمامي (frontend-dev، Vitest عند توفّره)
- التبويب الجديد يفتح؛ نموذجا الإعلان والبريد يعرضان حقولاً معنونة. النشر/الإرسال يستدعي المسار الصحيح بالجسم الصحيح. تأكيد البريد الجماعي يظهر قبل الإرسال. حالات تحميل/خطأ/فراغ/نجاح/429 صحيحة. RTL وa11y محفوظان (تبويبات غير مكسورة).

---

## 8. تقسيم المسؤوليات

| الدور | المهام |
|------|--------|
| **backend-dev (رئيسي)** | هجرة `course_announcements` + نموذج؛ نوعا `NotificationType` الجديدان؛ صنفا `CourseAnnouncementNotification`/`CourseBulkEmailNotification` (`ShouldQueue`، `candidateChannels`، `toMail`/`toArray`)؛ خدمة `CourseBroadcaster` (announce/bulkEmail + chunk المستلمين)؛ متحكّما `CourseAnnouncementController`/`CourseBulkEmailController`؛ `StoreAnnouncementRequest`/`SendBulkEmailRequest`؛ المسارات + `throttle`؛ التدقيق عبر `ActivityLogger`. إعادة استخدام `CourseAccess`/`PreferenceAwareNotification` — لا اختراع. |
| **frontend-dev (نماذج الإرسال)** | تبويب «التواصل» في `studio/[slug]/page.tsx` + مكوّن `CommunicationsPanel.tsx` (نموذج إعلان + قائمة إعلانات + نموذج بريد جماعي + تأكيد + حالات)؛ نوع `CourseAnnouncement` في `types.ts`؛ مفاتيح i18n (ar/en). إعادة استخدام أنماط `AssessmentsPanel`/`InstructorGradebook` (StatusMessage/RTL/a11y/تبويبات WAI-ARIA). |
| **qa-tester** | اختبارات §7 الخلفية (الطابور يستقبل، opt-out يُحترم، 403 لغير الطاقم، التدقيق، التخزين/الظهور، حصر النشطين، التحقّق، 429، العزل) بـ `Queue::fake()`/`Notification::fake()`؛ اختبار أمامي خفيف للنماذج إن توفّر إطار. |
| **compliance** | opt-out (متعلّم معطّل القناة لا يستقبل)؛ عدم كشف عناوين المستلمين (إرسال فردي لا BCC)؛ تقليل البيانات (لا تخزين نصوص بريد؛ تدقيق بالعدد فقط)؛ مكافحة سبام (rate limit + تأكيد)؛ XSS (عرض نصّ لا HTML)؛ a11y/RTL على التبويب الجديد؛ احتفاظ الإعلانات وإخفاء هوية المؤلف (PDPL/A3). |

---

## 9. ما هو خارج النطاق (لا هندسة زائدة)

- لا تقسيم جمهور متقدّم (حسب التقدّم/القسم/الحالة) — `audience=all_active` فقط؛ المعامل محجوز للتوسعة.
- لا تحرير/حذف/جدولة الإعلانات (نشر فقط؛ `softDeletes` غير مضاف).
- لا قوالب بريد غنية/HTML مخصّص؛ نصّ عادي عبر `MailMessage` القياسي.
- لا تخزين سجلّ رسائل البريد الجماعي ولا تتبّع فتح/نقر.
- لا تحويل الإشعارات القائمة الأخرى إلى `ShouldQueue` (يبقى سلوكها المتزامن كما هو).
- لا ردّ المتعلّم على الإعلان/البريد (اتجاه واحد من المعلّم).
- لا علاقة براية `payments.enabled` (التواصل مستقلّ عن التجارة).
