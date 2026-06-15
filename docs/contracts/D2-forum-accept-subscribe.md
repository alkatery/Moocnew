# عقد الدفعة D2 — المنتدى: تمييز الإجابة + متابعة الموضوع

> **الحالة:** نهائي للتنفيذ — يسلّمه `backend-dev` و`frontend-dev` حرفياً.
> **المرجع:** `docs/implementation-plan.md §5 D2` · `docs/PRD-MOOC-Platform-v2.md §5.ز` (منتدى المقرر) · `docs/architecture/CONTEXTS.md` (Communication + Enrollment + Notification).
> **الهدف:** (1) **تمييز إجابة:** صاحب الموضوع أو طاقم المقرر يميّز رداً واحداً كإجابة مقبولة على موضوعه. (2) **متابعة موضوع:** أي مشارك في المقرر يتابع موضوعاً ليُشعَر بالردود الجديدة (مُطابور، يحترم opt-out، لا يُشعِر كاتب الرد نفسه).
> **المبدأ الحاكم:** **إعادة استخدام البنية القائمة بالكامل** — `ForumController`/`ForumService`/`CourseAccess::canParticipate`/`Permission::Moderate`/`PreferenceAwareNotification` (نمط C3). لا سياق جديد، لا منطق تخويل جديد. أقلّ عقد يحقّق القبول.

---

## 0. حقائق مرصودة بعد قراءة الكود

| البند | الحالة المرصودة | المصدر المقروء |
|------|------------------|----------------|
| السياق المالك للمنتدى | **Communication** — `ForumThread`/`ForumPost`/`ForumService`/`ForumController` كلها هنا | `app/Contexts/Communication/...` |
| ملكية الموضوع | `forum_threads.user_id` = منشئ الموضوع؛ علاقة `author()` على `user_id` | `ForumThread.php:35`, هجرة `forum_threads` |
| إنشاء الرد | `ForumService::reply()` → `$thread->posts()->create([...])` (يردّ `ForumPost` بحقول `thread_id,user_id,parent_id,body`) | `ForumService.php:44` |
| ربط الموضوع بالمقرر | `forum_threads.course_id` → `Course`؛ `$thread->course` | `ForumThread.php:29` |
| تخويل المشاركة | `CourseAccess::canParticipate($user, $course)` = طاقم المقرر **أو** التحاق نشط — يُستدعى في كل دوال `ForumController` (`authorizeParticipation`) | `ForumController.php:108`, `CourseAccess.php:49` |
| تخويل الإشراف | `$user->can(Permission::Moderate->value)` (إخفاء/حظر) — صلاحية **عامّة** لا مرتبطة بمقرر | `ForumController.php:90,97` |
| تعريف «طاقم المقرر» | `CourseAccess::isStaffFor($user, $course)` = مالك المقرر (`instructor_id`) أو `courses.review` (+super_admin عبر Gate) | `CourseAccess.php:43` |
| استجابة `show` | **خام، بلا Resource:** `{ data: { thread: ForumThread, posts: ForumPost[] } }` — لا يكشف `thread.user_id`، والردود تحمل `user_id` | `ForumController.php:62` |
| فلترة الردود المخفيّة | غير المشرف لا يرى `hidden_at != null` | `ForumController.php:57` |
| **لا إشعار منتدى قائم** | لا يوجد أي إشعار يُرسَل عند رد جديد اليوم؛ لا متابعة | بحث المنتدى → لا notify |
| البنية التحتية للإشعار | `PreferenceAwareNotification` (يرث منه كل إشعار)؛ `via()` يفلتر القنوات حسب opt-out آلياً؛ `enabledChannels` | `PreferenceAwareNotification.php:36` |
| نمط الإشعار المُطابور | `CourseAnnouncementNotification` (C3): `implements ShouldQueue` + `use Queueable` + `onQueue('notifications')` + `candidateChannels()` + `toMail`/`toArray` | `CourseAnnouncementNotification.php` |
| أنواع الإشعار | `NotificationType` enum (8 قيم حالياً)؛ **لا نوع منتدى** | `NotificationType.php` |
| المستخدم الأمامي الحالي | `useAuth().user` يحمل `id` (لمعرفة ملكية الموضوع لإظهار زر التمييز) | `frontend/src/lib/auth.tsx:40` |
| الواجهة | `community/thread/[id]/page.tsx` يعرض الردود (الأول = صاحب الموضوع)؛ `community/[slug]/page.tsx` قائمة المواضيع | الملفان |

> **الخلاصة:** نضيف (1) حقل `accepted_post_id` على `forum_threads`، (2) جدول `forum_subscriptions`، (3) نوع إشعار `forum_reply` + صنف مُطابور يرث `PreferenceAwareNotification`، (4) 4 دوال جديدة في `ForumController` + بثّ المتابعين داخل `ForumService::reply`. **لا اختراع.**

---

## 1. قرارات معمارية مثبتة

| البند | القرار | التبرير |
|------|--------|---------|
| **مكان «الإجابة المقبولة»** | **`accepted_post_id` (nullable FK) على `forum_threads`** — لا `is_accepted` على `forum_posts` | الموضوع له **إجابة واحدة** مقبولة بحكم التعريف؛ تخزينها على الموضوع يجعل «إجابة واحدة» قيداً بنيوياً (تغيير الإشارة، لا تعديل صفّ آخر) ويُلغي السابق تلقائياً. أنظف من مسح/ضبط boolean على صفوف متعدّدة. |
| **من يميّز** | **صاحب الموضوع (`thread.user_id == user`) أو طاقم المقرر (`isStaffFor`)** | يطابق الهدف: صاحب السؤال يعرف ما حلّ مشكلته؛ الطاقم يصحّح. لا نستخدم `Permission::Moderate` العام (إخفاء/حظر فعل إشرافي مختلف؛ التمييز قرار تربوي خاص بمقرر). |
| **الرد المميَّز يجب أن يكون في الموضوع** | `post.thread_id == thread.id` (تحقّق إلزامي) ولا يكون رداً مخفيّاً (`hidden_at = null`) | لا يُميَّز رد من موضوع آخر؛ لا تُميَّز مشاركة محجوبة إشرافياً. |
| **إلغاء التمييز** | **نعم — toggle:** تمييز نفس الرد المميَّز حالياً يلغيه (`accepted_post_id = null`)؛ تمييز رد آخر يبدّل الإشارة | أبسط من مسار `DELETE` منفصل؛ زرّ واحد على كل رد بحالة `aria-pressed`. |
| **من يتابع** | **أي مشارك في المقرر** (`canParticipate`) — متعلّم نشط أو طاقم | المتابعة فعل مشاركة عادي؛ نفس بوّابة بقية المنتدى. |
| **idempotency المتابعة** | **idempotent:** `POST …/subscribe` لاشتراك قائم → `200` بلا تكرار (قيد `unique(thread_id,user_id)`)؛ `DELETE …/subscribe` لغير مشترك → `204` (لا خطأ) | يبسّط زرّ toggle؛ لا حالة خطأ للواجهة. |
| **متابعة ضمنية؟** | **لا في v1** — لا اشتراك تلقائي لمنشئ الموضوع أو للمجيب | لا هندسة زائدة؛ المعيار يطلب متابعة **صريحة**. (محجوز للتوسعة، انظر §9.) |
| **إشعار الرد الجديد** | نوع جديد `forum_reply` + صنف `ForumReplyNotification` (مُطابور، `ShouldQueue`)؛ قنوات `[database, mail]` | تنبيه تعليمي خفيف داخل التطبيق + بريد اختياري (مطابق منطق `CourseAnnouncement`). لا SMS/واتساب. |
| **تجنّب إشعار الذات** | كاتب الرد **مُستبعَد** من قائمة المتابعين قبل البثّ (`where user_id != author`) | لا يُشعَر أحد بردّه هو. |
| **مكان بثّ الإشعار** | داخل `ForumService::reply()` بعد إنشاء الرد (نقطة الحقيقة الوحيدة لإنشاء الردود) | يضمن الإشعار لأي مسار يُنشئ ردّاً، لا تكرار في المتحكّم. |
| **soft-delete** | **لا** للاشتراكات (قابلة لإعادة الإنشاء؛ لا قيمة تدقيقية)؛ `accepted_post_id` حقل لا صفّ | حذف فعلي/تفريغ الإشارة. |
| النقود | لا تنطبق | — |
| راية `payments.enabled` | **لا تمسّ هذه الدفعة** | المنتدى لا يعتمد على التجارة. |

---

## 2. مخطط البيانات

### 2.أ — تعديل `forum_threads` (هجرة جديدة: إضافة عمود)

`database/migrations/XXXX_add_accepted_post_to_forum_threads.php`

| العمود | النوع | قيود/ملاحظات |
|-------|------|--------------|
| `accepted_post_id` | `foreignId` nullable → `forum_posts` | `->nullable()->constrained('forum_posts')->nullOnDelete()` — حذف الرد المميَّز يفرّغ الإشارة بلا حذف الموضوع |

- **`nullOnDelete` (لا cascade):** إن حُذف الرد المميَّز (إشراف/cascade الموضوع)، يصبح `accepted_post_id = null` — لا يُحذف الموضوع، لا إشارة معلّقة لرد مفقود.
- **`up()`:** `$table->foreignId('accepted_post_id')->nullable()->after('locked')->constrained('forum_posts')->nullOnDelete();`
- **`down()`:** إسقاط FK ثم العمود (`dropConstrainedForeignId('accepted_post_id')`) — تراجع نظيف.
- **لا فهرس إضافي:** الوصول دائماً عبر `thread->id` (المفتاح)؛ `accepted_post_id` يُقرأ من صفّ الموضوع المحمَّل.
- **النموذج `ForumThread`:** إضافة `accepted_post_id` إلى `$fillable`، وعلاقة:
  ```php
  public function acceptedPost(): BelongsTo {
      return $this->belongsTo(ForumPost::class, 'accepted_post_id');
  }
  ```

### 2.ب — جدول `forum_subscriptions` (هجرة جديدة)

`database/migrations/XXXX_create_forum_subscriptions_table.php`

| العمود | النوع | قيود/ملاحظات |
|-------|------|--------------|
| `id` | `id` (bigint) | مفتاح أساسي |
| `thread_id` | `foreignId` → `forum_threads` | `constrained('forum_threads')->cascadeOnDelete()` — حذف الموضوع يحذف اشتراكاته |
| `user_id` | `foreignId` → `users` | `constrained()->cascadeOnDelete()` — تقاعد/حذف المستخدم (PDPL) يحذف اشتراكاته |
| `created_at` / `updated_at` | `timestamps` | — |

- **قيد التفرّد (إلزامي):** `$table->unique(['thread_id', 'user_id']);` — اشتراك واحد لكل (موضوع، مستخدم)؛ طبقة دفاع خلف `firstOrCreate`. يفهرس أيضاً استعلام «متابعو الموضوع» (`where thread_id`) فلا حاجة لفهرس إضافي.
- **`down()`:** `Schema::dropIfExists('forum_subscriptions');`.
- **soft-delete:** لا.
- **النموذج الجديد:** `App\Contexts\Communication\Infrastructure\Persistence\ForumSubscription`
  ```php
  protected $fillable = ['thread_id', 'user_id'];
  public function thread(): BelongsTo { return $this->belongsTo(ForumThread::class, 'thread_id'); }
  public function user(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
  ```
  وعلاقة على `ForumThread`: `public function subscriptions(): HasMany { return $this->hasMany(ForumSubscription::class, 'thread_id'); }`

### 2.ج — `NotificationType` (تعديل enum، لا هجرة)

إضافة حالة واحدة + تسميتها العربية:
```php
case ForumReply = 'forum_reply';   // label: 'ردود المنتدى'
```
> تظهر آلياً في مصفوفة مركز التفضيلات (`NotificationPreferences::matrix`) → يستطيع المستخدم تعطيل `mail` أو `database` لها → احترام opt-out مضمون عبر `via()`. لا هجرة (`notification_preferences` تخزّن صفوف التعطيل فقط؛ النوع الجديد مُفعّل افتراضياً).

---

## 3. عقود الـ API

كل المسارات تُضاف داخل مجموعة `community` القائمة في `routes/api.php` (~السطر 443، بعد `bans.store`) بأسماء `api.community.threads.*`. المتحكّم: `ForumController` (توسعة، لا متحكّم جديد). الـ binding للموضوع/الرد بالـ **id** (نمط `threads/{thread}`/`posts/{post}` القائم).

```
POST   /api/v1/community/threads/{thread}/accept       → accept       (api.community.threads.accept)
POST   /api/v1/community/threads/{thread}/subscribe    → subscribe    (api.community.threads.subscribe)
DELETE /api/v1/community/threads/{thread}/subscribe    → unsubscribe  (api.community.threads.unsubscribe)
```

> ملاحظة: التمييز **على مستوى الرد** عبر جسم `{ post_id }` (لا `posts/{post}/accept`) لأنّ الإجابة المقبولة خاصّية للموضوع لا للرد؛ المسار على الموضوع أنسب دلالياً وأبسط للـ toggle.

### 3.أ — `POST /threads/{thread}/accept` (تمييز / إلغاء — toggle)

- **التخويل (معيار قبول أساسي):**
  ```php
  abort_unless(
      $thread->user_id === $request->user()->getKey()
      || $this->access->isStaffFor($request->user(), $thread->course),
      403
  );
  ```
  صاحب الموضوع **أو** طاقم المقرر فقط. أي مشارك آخر → `403`.
- **الطلب:**
  | الحقل | التحقّق |
  |------|---------|
  | `post_id` | `required` · `integer` · `exists:forum_posts,id` |
- **تحقّقات سلامة إضافية (بعد جلب الرد):**
  - الرد ينتمي للموضوع: `abort_unless($post->thread_id === $thread->getKey(), 422)` (لا تمييز رد من موضوع آخر).
  - الرد غير مخفيّ: `abort_unless($post->hidden_at === null, 422)` (لا تمييز مشاركة محجوبة).
- **السلوك (toggle، إجابة واحدة):**
  - إن كان `thread.accepted_post_id === post_id` (نفس الرد المميَّز) → **إلغاء**: `accepted_post_id = null`.
  - وإلا → **تمييز/تبديل**: `accepted_post_id = post_id` (يُلغي أيّ تمييز سابق ضمنياً — حقل واحد).
  - يُحفظ على الموضوع. **لا إشعار** عند التمييز (فعل خفيف؛ خارج النطاق — انظر §9).
- **الاستجابة `200 OK`:**
  ```jsonc
  {
    "data": {
      "thread_id": 12,
      "accepted_post_id": 87   // أو null بعد الإلغاء
    }
  }
  ```

### 3.ب — `POST /threads/{thread}/subscribe` (متابعة / idempotent)

- **التخويل:** `abort_unless($this->access->canParticipate($request->user(), $thread->course), 403);` — مشارك في المقرر (طاقم أو ملتحق نشط). نفس بوّابة بقية المنتدى.
- **الطلب:** لا جسم.
- **السلوك — idempotent:**
  `ForumSubscription::firstOrCreate(['thread_id' => $thread->id, 'user_id' => $user->id])`. اشتراك جديد أو قائم → نفس الاستجابة (لا `409`).
- **الاستجابة `200 OK`:**
  ```jsonc
  { "data": { "thread_id": 12, "subscribed": true } }
  ```

### 3.ج — `DELETE /threads/{thread}/subscribe` (إلغاء المتابعة / idempotent)

- **التخويل:** نفس `canParticipate` (لمنع تعداد المواضيع خارج المقرر؛ المستخدم يلغي اشتراكه الخاصّ فقط بحكم فلتر `user_id`).
- **السلوك:** `ForumSubscription::where('thread_id', $thread->id)->where('user_id', $user->id)->delete();`. غير مشترك أصلاً → لا خطأ.
- **الاستجابة `200 OK`:**
  ```jsonc
  { "data": { "thread_id": 12, "subscribed": false } }
  ```
  > (`200` لا `204` — لتوحيد شكل `{ subscribed }` مع `POST` فيقرأ زرّ toggle الأمامي الحالة مباشرة من الاستجابة.)

### 3.د — كشف حالة المتابعة والتمييز للواجهة (توسعة `show`)

لتمكين الأزرار من معرفة الحالة الأولية دون استعلام إضافي، يُوسَّع `ForumController::show` ليُرجع:
```jsonc
{
  "data": {
    "thread": { "id": 12, "title": "...", "user_id": 5, "accepted_post_id": 87 },
    "posts": [ { "id": 87, "body": "...", "user_id": 9, "created_at": "..." }, ... ],
    "subscribed": true,                 // هل المستخدم الحالي متابع؟
    "can_accept": true                  // هل يحقّ للمستخدم الحالي التمييز؟ (صاحب الموضوع أو طاقم)
  }
}
```
- `thread.user_id` و`thread.accepted_post_id`: مكشوفان الآن (كان `user_id` غير مستهلَك أماميّاً؛ نحتاجه لإظهار زر التمييز والإجابة المقبولة). لا بيانات شخصية إضافية (مجرّد معرّف).
- `subscribed`: `$thread->subscriptions()->where('user_id', $user->id)->exists()`.
- `can_accept`: `$thread->user_id === $user->id || $access->isStaffFor($user, $thread->course)`.
- **فلترة الردود المخفيّة تبقى كما هي** (`whereNull('hidden_at')` لغير المشرف) — لا تغيير.

### رموز الأخطاء (موحّدة مع المنصّة)

| الحالة | الرمز |
|-------|------|
| غير مصادَق | `401` |
| تمييز من غير صاحب الموضوع/الطاقم | `403` |
| متابعة/إلغاء من غير مشارك في المقرر | `403` |
| الموضوع/الرد غير موجود | `404` |
| `post_id` مفقود/غير موجود | `422` |
| الرد لا ينتمي للموضوع، أو مخفيّ | `422` |

### حدود المعدّل

- المتابعة/الإلغاء/التمييز: ترث rate limiter الافتراضي لمجموعة `auth:sanctum` (لا حدّ خاص) — أفعال خفيفة idempotent. **حماية الإشعار من السبام مضمونة عبر مسار الرد القائم** (`assertNotDuplicate` في `ForumService` يمنع الردود المكرّرة → لا فيض إشعارات).

---

## 4. الموديول (حدود السياقات)

- **Communication (يملك المنطق):**
  - هجرتان (`accepted_post_id`، `forum_subscriptions`) + نموذج `ForumSubscription` + علاقتان على `ForumThread` (`acceptedPost`, `subscriptions`).
  - توسعة `ForumService`:
    - `accept(ForumThread $thread, ForumPost $post): ?int` — منطق toggle، يردّ `accepted_post_id` الجديد (أو null). يفترض أنّ المتحكّم تحقّق من الانتماء/الإخفاء (أو يكرّر التحقّق دفاعياً).
    - `subscribe(ForumThread $thread, User $user): void` / `unsubscribe(ForumThread $thread, User $user): void`.
    - **توسعة `reply()`:** بعد إنشاء الرد، يبثّ `ForumReplyNotification` للمتابعين عدا الكاتب (انظر §5). البثّ في الخدمة (نقطة الحقيقة لإنشاء الردود).
  - توسعة `ForumController` بدوال `accept`/`subscribe`/`unsubscribe` + توسعة `show`.
- **Enrollment (يملك قاعدة الوصول):** التخويل عبر `CourseAccess::canParticipate`/`isStaffFor` — يُعاد استخدامه حرفياً، **لا منطق تخويل جديد**.
- **Notification (يملك البثّ المُطابور):** نوع `NotificationType::ForumReply` + صنف `ForumReplyNotification` (يرث `PreferenceAwareNotification`) في `app/Contexts/Notification/Infrastructure/Notifications/`. مطابق طبقية C3.
- **Catalog:** `Course` يُقرأ فقط (`$thread->course` للتخويل) — لا تعديل.
- **Domain لا يعرف Infrastructure:** `ForumService` (Application) يمرّر كيانات Infrastructure (`ForumThread`/`ForumPost`/`User`) ويستدعي `CourseAccess` (Application) — نفس طبقية الخدمة القائمة. الإشعار (Infrastructure/Notification) يُنشأ ويُرسَل من Application، لا تسرّب لـ Domain.

### الصنف الجديد `ForumReplyNotification`

`app/Contexts/Notification/Infrastructure/Notifications/ForumReplyNotification.php` — **مرآة `CourseAnnouncementNotification`**:
- `extends PreferenceAwareNotification implements ShouldQueue`, `use Queueable`, `onQueue('notifications')` في الباني.
- `type(): NotificationType::ForumReply`.
- `candidateChannels(): ['database', 'mail']`.
- الباني: `(int $threadId, string $threadTitle, string $courseTitle, string $replierName)` — لا نمرّر نصّ الرد كاملاً للوارد (تقليل البيانات؛ الرابط يكفي).
- `toMail()`: subject = «رد جديد على: {threadTitle}»، أسطر «أضاف {replierName} رداً جديداً في نقاش {courseTitle}» + «اطّلع على الموضوع».
- `toArray()`: `{ type, thread_id, thread_title, course_title }` (للوارد `database` + الربط بـ `/community/thread/{thread_id}`).

---

## 5. منطق بثّ إشعار الرد (في `ForumService::reply`)

بعد إنشاء `$post` داخل `reply()`:
```text
1. جلب متابعي الموضوع المستبعَد منهم الكاتب:
   ForumSubscription::where('thread_id', $thread->id)
       ->where('user_id', '!=', $author->getKey())   // تجنّب إشعار الذات (معيار قبول)
       ->with('user') أو chunk على user_ids
2. لكل متابع: $subscriber->notify(new ForumReplyNotification($thread->id, $thread->title, $thread->course->title, $author->name));
   → ShouldQueue يدفع كل إرسال للطابور (notifications)؛ via() يُسقط أي قناة عطّلها المتابع (opt-out).
```
- **العزل/الكفاءة:** إرسال **فردي** لكل متابع (`$user->notify`) — لا قائمة جماعية، لا كشف بريد متابع لآخر. `chunkById` على المتابعين إن كثروا (خفيف الذاكرة؛ كل إرسال مهمة مُطابورة).
- **الكاتب نفسه:** مستبعَد بفلتر `user_id != author` — لا يُشعَر بردّه. (وإن كان مشتركاً، يبقى اشتراكه ولا يُرسَل له لهذا الرد.)
- **احترام opt-out:** مجّاني عبر `PreferenceAwareNotification::via()` — متابع عطّل `mail` (أو `database`) لنوع `forum_reply` لا يستقبل عبرها.
- **الردود المكرّرة:** محجوبة مسبقاً بـ `assertNotDuplicate` القائم → لا فيض إشعارات.

---

## 6. الواجهة (frontend-dev)

### 6.أ — زر «تمييز كإجابة» (صفحة الموضوع، لكل رد)

**الملف:** `frontend/src/app/community/thread/[id]/page.tsx`.
- يُجلب الآن من `show` الحقول الجديدة: `thread.user_id`, `thread.accepted_post_id`, `subscribed`, `can_accept`.
- **يظهر زرّ «تمييز كإجابة» على كل رد** (عدا الرد الأول = نصّ السؤال نفسه، اختياري الإخفاء عليه) **فقط إذا `can_accept === true`**.
- **السلوك:** نقرة → `POST /community/threads/{id}/accept { post_id: p.id }` → تحديث `accepted_post_id` من الاستجابة (إعادة تحميل أو تحديث محلّي).
- **عرض الإجابة المقبولة (لكل المشاهدين، لا الطاقم فقط):** الرد الذي `p.id === accepted_post_id` يُميَّز بصرياً — شارة «إجابة مقبولة» + إطار/لون مميّز (أخضر مثلاً)، ويُفضَّل رفعه أعلى القائمة أو إبرازه.
- **a11y (تنسيق compliance):**
  - الزرّ `<button>` أصيل مع `aria-pressed={p.id === accepted_post_id}` (حالة toggle معلَنة).
  - تسمية متبدّلة: «تمييز كإجابة مقبولة» / «إلغاء تمييز الإجابة» (نص مرئي أو `aria-label`).
  - شارة «إجابة مقبولة» تحمل تسمية نصيّة (لا لون فقط — تباين AA)؛ أيقونة مزيّنة `aria-hidden`.
  - النجاح/الخطأ عبر `StatusMessage` (`SuccessMsg` `role="status"` / `ErrorMsg` `role="alert"`).

### 6.ب — زر «متابعة / إلغاء المتابعة» (رأس الموضوع)

**الملف:** نفس الصفحة، بجوار `PageHeader`/عنوان الموضوع.
- **يظهر لأي مشارك مصادَق** (الصفحة خلف مصادَقة؛ من ليس مشاركاً سيقع على `403` يُعرض كرسالة).
- **السلوك toggle:**
  - غير متابع → `POST /community/threads/{id}/subscribe` → يصبح متابعاً.
  - متابع → `DELETE /community/threads/{id}/subscribe` → يصبح غير متابع.
  - الحالة الأولية من `subscribed` في استجابة `show`؛ تُحدَّث من استجابة الـ POST/DELETE (`data.subscribed`).
- **a11y (إلزامي — `aria-pressed`):**
  - `<button aria-pressed={subscribed}>` مع تسمية متبدّلة «متابعة الموضوع» / «إلغاء المتابعة».
  - أيقونة جرس مزيّنة `aria-hidden`؛ تباين AA؛ تشغيل بلوحة المفاتيح.
  - رسائل عبر `StatusMessage`.

### 6.ج — الأنواع (`frontend/src/lib/types.ts` — تعديل + إضافة)

توسعة `ForumPost` (لا كسر) وإضافة شكل استجابة الموضوع:
```ts
export interface ForumPost {
  id: number;
  body: string;
  user_id: number;
  created_at: string | null;
}

// D2: استجابة GET /community/threads/{id}
export interface ThreadDetail {
  thread: { id: number; title: string; user_id: number; accepted_post_id: number | null };
  posts: ForumPost[];
  subscribed: boolean;
  can_accept: boolean;
}
```

### 6.د — مفاتيح i18n (`frontend/src/i18n/dictionary.ts` — ar + en)

`community.accept` («تمييز كإجابة مقبولة»)، `community.unaccept` («إلغاء تمييز الإجابة»)، `community.acceptedBadge` («إجابة مقبولة»)، `community.subscribe` («متابعة الموضوع»)، `community.unsubscribe` («إلغاء المتابعة»)، `community.subscribed` («تتابع هذا الموضوع — ستُشعَر بالردود الجديدة»)، `community.acceptError` («تعذّر تمييز الإجابة»)، `community.subscribeError` («تعذّر تحديث المتابعة»).

---

## 7. الأمان والامتثال

- **التخويل الدقيق:** التمييز حصراً لصاحب الموضوع أو طاقم المقرر (`isStaffFor`) → `403` لغيره (مشارك عادي/زائر). المتابعة حصراً لمشارك في المقرر (`canParticipate`).
- **عزل المقررات:** كل فعل يمرّ عبر `$thread->course` ثم `canParticipate`/`isStaffFor` — مشارك مقرر آخر لا يتابع/يميّز موضوع مقرر ليس فيه (`403`). الرد المميَّز يجب أن يكون **في نفس الموضوع** (`422` وإلا).
- **احترام opt-out (PDPL §5.ط):** `ForumReplyNotification` يرث `PreferenceAwareNotification`؛ متابع عطّل القناة لنوع `forum_reply` لا يستقبل عبرها. النوع منفصل في مركز التفضيلات.
- **تجنّب إشعار الذات:** الكاتب مستبعَد بفلتر `user_id != author` قبل البثّ — لا أحد يُشعَر بردّه.
- **عدم كشف بيانات المستلمين:** إرسال فردي لكل متابع (`$user->notify`) — لا قائمة/BCC مشترك.
- **تقليل البيانات:** الاشتراك يخزّن `(thread_id, user_id)` فقط؛ الوارد يحمل عنوان الموضوع/المقرر واسم المجيب والرابط، لا نصّ الرد كاملاً. `cascadeOnDelete` على `user_id` يحذف اشتراكات المستخدم عند تقاعده (لا بيانات متبقية).
- **منع XSS:** عنوان الموضوع/اسم المجيب يُعرضان كنصّ (escaped) في الواجهة والبريد — لا HTML خام.
- **منع السبام:** الردود المكرّرة محجوبة مسبقاً (`assertNotDuplicate`) → لا فيض إشعارات؛ لا حدّ معدّل خاص لازم على المتابعة/التمييز (idempotent خفيف).

---

## 8. معايير القبول (قابلة للاختبار)

### خلفي — Pest Feature/Unit (qa-tester)

**تمييز الإجابة:**
1. **صاحب الموضوع يميّز:** منشئ الموضوع → `POST …/accept {post_id}` لرد في موضوعه → `200`، `thread.accepted_post_id == post_id`.
2. **الطاقم يميّز:** مالك المقرر (`instructor_id`) أو `courses.review` → `200` على موضوع متعلّم آخر.
3. **غير المخوّل → 403:** مشارك عادي (ليس صاحب الموضوع ولا طاقم) → `POST …/accept` → `403`، لا تغيير.
4. **إجابة واحدة:** تمييز رد (أ) ثم رد (ب) في نفس الموضوع → `accepted_post_id == post_b` فقط (أُلغي الأول ضمنياً) — **لا إجابتان**.
5. **إلغاء (toggle):** تمييز نفس الرد المميَّز مرّتين → الثانية تُرجع `accepted_post_id == null`.
6. **رد من موضوع آخر → 422:** `post_id` لرد لا ينتمي للموضوع → `422`، لا تغيير.
7. **رد مخفيّ → 422:** تمييز رد `hidden_at != null` → `422`.
8. **حذف الرد المميَّز يفرّغ الإشارة:** حذف الرد المميَّز → `accepted_post_id` يصبح `null` (`nullOnDelete`)، الموضوع باقٍ.

**المتابعة:**
9. **متابعة:** مشارك نشط → `POST …/subscribe` → `200` `subscribed:true`، صفّ في `forum_subscriptions`.
10. **idempotent:** `POST …/subscribe` مرّتين → الثانية `200`، **صفّ واحد فقط** (يثبت `unique`).
11. **إلغاء المتابعة:** `DELETE …/subscribe` → `200` `subscribed:false`، يختفي الصفّ؛ إلغاء غير مشترك أصلاً → `200` بلا خطأ.
12. **غير مشارك → 403:** مستخدم غير ملتحق وليس طاقماً → `POST …/subscribe` → `403`.

**الإشعار:**
13. **إشعار المتابعين عند رد جديد:** `Notification::fake()`؛ موضوع له متابعان (غير الكاتب) → ردّ جديد يبثّ `ForumReplyNotification` للمتابعَين (`assertSentTo` ×2)، الصنف `implements ShouldQueue`.
14. **لا إشعار للكاتب:** كاتب الرد مشترك في الموضوع → بعد ردّه **لا** يُرسَل له `ForumReplyNotification` (`assertNotSentTo`).
15. **opt-out يُحترم:** متابع عطّل `mail` لنوع `forum_reply` → `via()` له لا يُرجع `mail`؛ متابعون آخرون يستقبلون عبر قنواتهم.
16. **لا متابع → لا إشعار:** ردّ على موضوع بلا متابعين → صفر إشعارات (بثّ آمن).

### أمامي — Vitest (qa-tester)
- زرّ المتابعة يعكس `aria-pressed` بعد النجاح؛ زرّ «تمييز كإجابة» يظهر فقط حين `can_accept` ويعكس `aria-pressed` للرد المميَّز؛ شارة «إجابة مقبولة» تظهر على الرد المميَّز؛ حالات نجاح/خطأ عبر `StatusMessage`؛ RTL محفوظ.

---

## 9. تقسيم المسؤوليات

| الدور | المهام |
|------|--------|
| **backend-dev** | هجرة `accepted_post_id` على `forum_threads` (`nullOnDelete`) + هجرة `forum_subscriptions` (`unique(thread_id,user_id)` + cascade) + نموذج `ForumSubscription` + علاقتا `acceptedPost`/`subscriptions` على `ForumThread`؛ نوع `NotificationType::ForumReply`؛ صنف `ForumReplyNotification` (`ShouldQueue`، مرآة `CourseAnnouncementNotification`)؛ توسعة `ForumService` (`accept`/`subscribe`/`unsubscribe` + بثّ المتابعين في `reply`)؛ توسعة `ForumController` (`accept`/`subscribe`/`unsubscribe` + حقول `show`)؛ 3 أسطر مسار. إعادة استخدام `CourseAccess`/`PreferenceAwareNotification` — لا اختراع. |
| **frontend-dev** | زرّ «متابعة/إلغاء» (`aria-pressed`) في رأس `community/thread/[id]/page.tsx` + زرّ «تمييز كإجابة» (`aria-pressed`) لكل رد عند `can_accept` + شارة «إجابة مقبولة» على الرد المميَّز + استهلاك حقول `show` الجديدة + نوعا `ThreadDetail`/توسعة `ForumPost` في `types.ts` + مفاتيح i18n (ar/en). إعادة استخدام `StatusMessage`/`PageHeader`/`useAuth`. |
| **qa-tester** | Feature tests §8 (تمييز: صاحب/طاقم/403/إجابة واحدة/toggle/422/nullOnDelete؛ متابعة: idempotent بصفّ واحد/إلغاء/403؛ إشعار: بثّ للمتابعين `Notification::fake`/لا للكاتب/opt-out/لا متابع) + اختبار أمامي للأزرار والشارة والحالات. |
| **compliance** | التخويل الدقيق (التمييز لصاحب/طاقم، المتابعة لمشارك)؛ عزل المقررات (لا تمييز/متابعة عبر المقررات)؛ opt-out (متابع معطّل القناة لا يستقبل)؛ تجنّب إشعار الذات؛ عدم كشف المستلمين (إرسال فردي)؛ تقليل البيانات (`thread_id,user_id` فقط؛ لا نصّ رد في الوارد؛ cascade عند تقاعد المستخدم)؛ XSS (عرض نصّ)؛ a11y (`aria-pressed` للزرّين، شارة بتسمية لا لون فقط، تباين AA)؛ RTL. |

---

## 10. ما هو خارج النطاق (لا هندسة زائدة)

- لا إشعار عند **تمييز** إجابتك كمقبولة (تنبيه «قُبلت إجابتك» مؤجَّل — انظر §9 D3 لاحقاً عند الحاجة).
- لا متابعة ضمنية تلقائية (لمنشئ الموضوع/المجيب) — متابعة صريحة فقط؛ المعامل محجوز للتوسعة.
- لا تصويت/إعجاب على الردود، لا «أفضل إجابة» متعدّدة — إجابة واحدة مقبولة فقط.
- لا تجميع إشعارات الردود (digest) — هذا دور D3 (ملخّصات الإشعارات).
- لا ترقيم لمتابعي الموضوع/إدارتهم؛ لا صفحة «مواضيع أتابعها» (يُضاف لاحقاً عند الحاجة).
- لا تحويل الإشعارات القائمة الأخرى إلى `ShouldQueue` (سلوكها المتزامن كما هو).
- لا علاقة براية `payments.enabled` (المنتدى مستقلّ عن التجارة).
