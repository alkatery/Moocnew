# عقد الدفعة D3 — ملخّصات الإشعارات المجدولة

> **الحالة:** نهائي للتنفيذ — يسلّمه `backend-dev` و`frontend-dev` حرفياً.
> **المرجع:** `docs/implementation-plan.md §5 D3` · `docs/PRD-MOOC-Platform-v2.md §5.ط` (مركز التفضيلات) · `docs/architecture/CONTEXTS.md` (Notification + Identity/User).
> **الهدف:** أمر مجدول (Horizon/Scheduler) يجمّع نشاط المستخدم (إشعاراته داخل التطبيق) منذ آخر ملخّص، ويُرسل **بريداً ملخّصاً واحداً** حسب تفضيل تكراره (فوري `off` / يومي `daily` / أسبوعي `weekly`). يقابل «الإشعارات الذكية: ملخّصات يومية/أسبوعية» في Open edX.
> **المبدأ الحاكم:** **إعادة استخدام البنية القائمة بالكامل** — جدول `notifications` (قناة database) مصدرَ النشاط؛ نمط `DispatchSessionReminders`/`DispatchStudyPlanReminders` للأمر المجدول؛ نمط `Schedule::command(...)` في `routes/console.php`؛ نمط `CourseAnnouncementNotification` (مُطابور، `ShouldQueue`) للبريد. أقلّ عقد يحقّق القبول. لا سياق جديد.

---

## 0. حقائق مرصودة بعد قراءة الكود

| البند | الحالة المرصودة | المصدر المقروء |
|------|------------------|----------------|
| **بُعد التكرار: غير موجود** | التفضيلات حالياً **ثنائية فقط (نوع × قناة → مفعّل/معطّل)**؛ صفّ في `notification_preferences` يعطّل زوجاً. **لا مفهوم frequency/تكرار إطلاقاً** | `NotificationPreferences.php`, هجرة `notification_preferences` (`type,channel,enabled`), `UpdatePreferencesRequest.php` |
| مركز التفضيلات | `NotificationPreferences::matrix()` يبني مصفوفة type×channel؛ `via()` في `PreferenceAwareNotification` يفلتر القنوات آلياً (opt-out) | `NotificationPreferences.php:58`, `PreferenceAwareNotification.php:36` |
| متحكّم التفضيلات | `PreferenceController::index/update` — `GET/PUT /notifications/preferences`؛ يقرأ/يكتب المصفوفة فقط | `PreferenceController.php`, `routes/api.php:381` |
| جدول النشاط (المصدر) | `notifications` القياسي (Laravel database channel): `id(uuid), type, notifiable_type/id, data(text/json), read_at(nullable), created_at, updated_at` | هجرة `create_notifications_table`, `NotificationController.php` |
| كيف تُقرأ الإشعارات | `$user->notifications()` / `unreadNotifications()`؛ `data['type']` يحمل نوع الإشعار، عنوان مختصر في `data` | `NotificationController.php:18,27` |
| نمط الأمر المجدول | `Command` بـ `$signature`/`$description`/`handle()`؛ `chunkById` للكفاءة؛ `Date::now()`؛ idempotency عبر عمود `*_at` (`reminded_at`/`last_reminded_at`) | `DispatchSessionReminders.php`, `DispatchStudyPlanReminders.php` |
| تسجيل الجدولة | `Schedule::command('...')->everyMinute()/->hourly()->withoutOverlapping()` في `routes/console.php` (مُسجَّل عبر `bootstrap/app.php`) | `routes/console.php:12,15`, `bootstrap/app.php:15` |
| نمط البريد المُطابور | `CourseAnnouncementNotification`: `extends PreferenceAwareNotification implements ShouldQueue` + `use Queueable` + `onQueue('notifications')` + `candidateChannels()` + `toMail()`/`toArray()` | `CourseAnnouncementNotification.php` |
| نموذج المستخدم | `User` (`Notifiable`, `SoftDeletes`)؛ `$fillable` يضمّ `locale,timezone,disabled_at`؛ `casts()` يضبط `datetime` لحقول التواريخ | `app/Models/User.php` |
| المنطقة الزمنية للمستخدم | `User::timezone` (افتراضي `Asia/Riyadh` في الأوامر القائمة) — يُستخدم لتحديد «يوم» الملخّص الأسبوعي | `DispatchSessionReminders.php:41` |
| واجهة الإشعارات | `frontend/src/app/notifications/page.tsx`: صندوق + جدول مصفوفة type×channel (checkboxes)؛ `PUT /notifications/preferences` للتبديل | `notifications/page.tsx` |
| Horizon/الطوابير | موجود؛ طابور `notifications` مخصّص (يُستخدم في C3/D2) | `CourseAnnouncementNotification.php:31` |

> **الخلاصة:** لا يوجد بُعد تكرار. نضيف (1) عمودين على `users`: `digest_frequency ENUM('off','daily','weekly') default 'off'` و`last_digest_at (nullable timestamp)`؛ (2) نقطتي API (قراءة/ضبط) على `PreferenceController` بطلب منفصل بسيط؛ (3) أمر `DispatchNotificationDigests` (نمط `DispatchStudyPlanReminders`) يجمّع `notifications` منذ `last_digest_at` ويبثّ `DigestNotification`؛ (4) سطر جدولة يومي؛ (5) قسم «تكرار الملخّص» في واجهة `/notifications`. **لا اختراع، لا سياق جديد.**

---

## 1. قرارات معمارية مثبتة

| البند | القرار | التبرير |
|------|--------|---------|
| **مكان بُعد التكرار** | **عمودان على `users`** (`digest_frequency`, `last_digest_at`) — لا جدول منفصل، لا توسعة مصفوفة `notification_preferences` | التكرار **إعداد عام للمستخدم** (تردّد بريد الملخّص)، لا خاصّية (نوع×قناة). جدول التفضيلات يعبّر عن «أي زوج معطّل»؛ التكرار بُعد مختلف منطقياً → عمود على المستخدم أبسط وأدقّ دلالياً ويتجنّب تضخيم المصفوفة. |
| **القيمة الافتراضية** | `digest_frequency = 'off'` (فوري) | السلوك القائم يبقى كما هو: الإشعارات تصل فوراً عبر قنواتها؛ من لم يختر صراحةً لا يُرسَل له ملخّص. **لا تغيير سلوكي للمستخدمين الحاليين.** |
| **معنى `off`** | لا ملخّص دوري — الإشعارات الفورية تعمل كالمعتاد | الملخّص **إضافة اختيارية**، لا بديل عن الفوري. `off` = «لا أريد بريد تجميع». |
| **مصدر «النشاط»** | **إشعارات المستخدم في جدول `notifications` المنشأة بعد `last_digest_at`** (كل الأنواع، لا قناة database فقط — الجدول هو سجلّ النشاط) | الأبسط والأكثف قيمة: مصدر واحد موحَّد، يُجمَّع آلياً مع كل نوع جديد (C3/D2 وما يليها) بلا تعديل. لا استعلام نشاط مخصّص لكل سياق (ردود/درجات/إعلانات) — هندسة زائدة. |
| **«غير المقروء» أم «الجديد»؟** | **الجديد منذ `last_digest_at`** (`created_at > last_digest_at`)، لا «غير المقروء» | الملخّص = «ما جدّ منذ آخر ملخّص»، تعريف زمني idempotent لا يعتمد على فعل القراءة (قد يقرأ المستخدم بعضها في التطبيق ويظلّ يريد الملخّص شاملاً). يتجنّب سباق القراءة/الإرسال. (أول ملخّص: `last_digest_at = null` → نطاق منذ آخر `digest_lookback` بحسب التكرار، انظر §3.) |
| **منع الازدواج (idempotency)** | عمود `last_digest_at` — بعد كل إرسال يُضبط إلى `Date::now()`؛ تشغيل الأمر ثانيةً بنفس اليوم → لا نشاط جديد بعد آخر ضبط → **لا إرسال ثانٍ** | نفس مبدأ `reminded_at`/`last_reminded_at` القائم. مثبت ومختبَر النمط. |
| **منع الملخّص الفارغ** | إن لم يوجد نشاط جديد (`count == 0`) → **لا يُرسَل بريد، ولا يُحدَّث `last_digest_at`** | معيار قبول صريح: لا بريد فارغ. عدم تحديث `last_digest_at` يضمن أنّ نشاطاً لاحقاً في نفس النافذة يُجمَّع في الملخّص التالي بلا فقدان. |
| **«المستحقّ» للملخّص اليومي** | `digest_frequency='daily'` **و** (`last_digest_at == null` **أو** `last_digest_at <= now - ~23h`) | كل يوم؛ هامش ~23h (لا 24h حرفية) يمنع الانزلاق التدريجي لو تأخّر التشغيل دقائق. |
| **«المستحقّ» للملخّص الأسبوعي** | `digest_frequency='weekly'` **و** اليوم المحلّي للمستخدم = **الأحد** (يوم بثّ الأسبوع) **و** (`last_digest_at == null` **أو** `last_digest_at <= now - ~6d`) | يوم بثّ ثابت (الأحد، بداية الأسبوع الدراسي) بتوقيت المستخدم (`User::timezone`)؛ هامش ~6d يمنع التكرار داخل اليوم نفسه عند تعدّد التشغيلات. |
| **تواتر تشغيل الأمر** | **يومياً مرّة** (`->dailyAt('07:00')`) — لا كل دقيقة/ساعة | الملخّص اليومي يحتاج تشغيلاً يومياً؛ الأسبوعي يُفلتَر داخلياً بيوم الأحد. تشغيل واحد يخدم النوعين. (توقيت ثابت بتوقيت الخادم؛ v1 لا يخصّص ساعة الإرسال لكل منطقة.) |
| **القنوات** | `DigestNotification` قناة **mail فقط** (`candidateChannels(): ['mail']`) | الملخّص بريد بطبيعته (تجميع دوري)؛ لا معنى لوارد database مكرّر للملخّص (المفردات أصلاً في الوارد). يبقى يحترم opt-out: من عطّل `mail` لنوع `digest` لا يُرسَل له. |
| **النوع الجديد** | `NotificationType::Digest = 'digest'` (label «ملخّص النشاط») | يظهر في مصفوفة التفضيلات → يستطيع المستخدم تعطيل بريد الملخّص حتى لو فعّل تكراراً (طبقة احترام إضافية)؛ ويتيح `via()` فلترة opt-out آلياً. |
| النقود | لا تنطبق | — |
| راية `payments.enabled` | **لا تمسّ هذه الدفعة** | الإشعارات/الملخّصات مستقلّة عن التجارة. |
| soft-delete | لا جداول جديدة؛ `last_digest_at`/`digest_frequency` حقول على `users` (يرث `SoftDeletes`) | المستخدم المتقاعد/المعطّل يُستبعَد (انظر §3 شرط `disabled_at`/`deleted_at`). |

---

## 2. مخطط البيانات

### 2.أ — تعديل `users` (هجرة جديدة: إضافة عمودين)

`database/migrations/XXXX_add_digest_columns_to_users_table.php`

| العمود | النوع | قيود/ملاحظات |
|-------|------|--------------|
| `digest_frequency` | `string`/`enum` | القيم `'off'|'daily'|'weekly'`؛ `default('off')`؛ غير قابل للـ null. (يُنفَّذ كـ `$table->string('digest_frequency')->default('off');` أو `enum` — string أبسط وأكثر قابلية للنقل بين قواعد البيانات.) |
| `last_digest_at` | `timestamp` nullable | `->nullable()` — `null` يعني «لم يُرسَل ملخّص بعد». يُضبط بعد كل إرسال. |

- **`up()`:**
  ```php
  $table->string('digest_frequency')->default('off')->after('timezone');
  $table->timestamp('last_digest_at')->nullable()->after('digest_frequency');
  ```
- **`down()`:** `$table->dropColumn(['digest_frequency', 'last_digest_at']);` — تراجع نظيف.
- **فهرس:** `$table->index(['digest_frequency', 'last_digest_at']);` — يخدم استعلام اختيار المستحقّين (`where digest_frequency != 'off'` + شرط `last_digest_at`). فهرس واحد مركّب كافٍ.
- **`User` model:**
  - إضافة `'digest_frequency'`, `'last_digest_at'` إلى `$fillable` (للضبط من المتحكّم).
  - إضافة `'last_digest_at' => 'datetime'` إلى `casts()` (مقارنات `Date::now()`).
  - **اختياري (نظافة):** enum PHP `DigestFrequency: string { Off='off'; Daily='daily'; Weekly='weekly'; }` في `App\Contexts\Notification\Domain\` للتحقّق والوسم — يُعاد استخدامه في الطلب والأمر. (لا يضيف هجرة.)

### 2.ب — `NotificationType` (تعديل enum، لا هجرة)

إضافة حالة + تسميتها العربية في `app/Contexts/Notification/Domain/NotificationType.php`:
```php
case Digest = 'digest';   // label: 'ملخّص النشاط'
```
> تظهر آلياً في مصفوفة مركز التفضيلات (`matrix()`) → يستطيع المستخدم تعطيل `mail` لها → احترام opt-out مضمون عبر `via()`. لا هجرة (`notification_preferences` تخزّن صفوف التعطيل فقط؛ النوع الجديد مُفعّل افتراضياً).

> **ملاحظة المصفوفة:** نوع `digest` يظهر مع كل القنوات في `matrix()` لكنّ `DigestNotification` يرشّح `mail` فقط؛ القنوات الأخرى لا أثر لها (لا يُرسَل عبرها أصلاً). مقبول في v1 (لا تعقيد إضافي لإخفاء صفوف غير ذات صلة).

---

## 3. الأمر المجدول — `DispatchNotificationDigests`

`app/Console/Commands/DispatchNotificationDigests.php` — **نمط `DispatchStudyPlanReminders`**.

```
signature:   notifications:dispatch-digests {--frequency= : daily|weekly (للاختبار/التشغيل اليدوي؛ افتراضياً كلاهما حسب الاستحقاق)}
description:  Dispatch daily/weekly activity digests to opted-in users
```

### خوارزمية `handle()` (نصّ، لا كود إنتاج)

```text
$now = Date::now();
$sent = 0;

User::query()
    ->where('digest_frequency', '!=', 'off')
    ->whereNull('disabled_at')      // لا ملخّص لمستخدم معطّل
    // (soft-deleted مُستبعَد آلياً عبر SoftDeletes global scope)
    ->chunkById(200, function ($users) use ($now, &$sent) {
        foreach ($users as $user) {
            if (! isDue($user, $now)) { continue; }      // §3.أ

            $since = $user->last_digest_at ?? lookbackStart($user, $now);  // §3.ب
            $activity = $user->notifications()
                ->where('created_at', '>', $since)
                ->where('type', '!=', /* DatabaseNotification لنوع digest نفسه إن وُجد */)
                ->orderByDesc('created_at')
                ->limit(50)                              // سقف لتقليل البيانات/حجم البريد
                ->get(['id', 'data', 'created_at']);

            $total = $user->notifications()->where('created_at', '>', $since)->count();

            if ($total === 0) { continue; }              // §1: لا ملخّص فارغ — لا تحديث last_digest_at

            $user->notify(new DigestNotification(
                frequency: $user->digest_frequency,
                totalCount: $total,
                items: $activity->map(fn ($n) => [
                    'type'  => $n->data['type'] ?? null,
                    'title' => digestLineFor($n),        // عنوان مختصر من data، لا حمولة كاملة
                ])->all(),
            ));

            $user->update(['last_digest_at' => $now]);    // idempotency
            $sent++;
        }
    });

$this->info("Sent {$sent} digest(s).");
return self::SUCCESS;
```

### 3.أ — `isDue($user, $now)`

- إن مُرِّر `--frequency`، يجب أن يطابق `digest_frequency` (وإلا `false`).
- **daily:** `last_digest_at === null || last_digest_at <= now->copy()->subHours(23)`.
- **weekly:**
  - اليوم المحلّي للمستخدم = الأحد: `Date::now($user->timezone ?? 'Asia/Riyadh')->isSunday()`.
  - **و** `last_digest_at === null || last_digest_at <= now->copy()->subDays(6)`.

### 3.ب — `lookbackStart($user, $now)` (أول ملخّص فقط، `last_digest_at === null`)

- daily → `now->copy()->subDay()`.
- weekly → `now->copy()->subWeek()`.

> يضمن أنّ أول ملخّص لا يجمّع تاريخ المستخدم كاملاً (تقليل بيانات + بريد معقول)، بل آخر نافذة فقط.

### 3.ج — تسجيل الجدولة (`routes/console.php`)

سطر واحد يُضاف بعد سطور الجدولة القائمة:
```php
// Activity digests (D3) — يومياً 07:00؛ daily كل يوم، weekly يوم الأحد (يُفلتَر داخلياً).
Schedule::command('notifications:dispatch-digests')->dailyAt('07:00')->withoutOverlapping();
```

### 3.د — حدود/أداء

- `chunkById(200)` — خفيف الذاكرة (نمط `DispatchStudyPlanReminders`).
- كل `$user->notify(...)` مهمة مُطابورة (`ShouldQueue` على `DigestNotification`) على طابور `notifications` → لا إرسال متزامن لآلاف. Horizon يستوعب.
- استعلام الاختيار محدود بالفهرس المركّب `(digest_frequency, last_digest_at)`.

---

## 4. الإشعار — `DigestNotification`

`app/Contexts/Notification/Infrastructure/Notifications/DigestNotification.php` — **مرآة `CourseAnnouncementNotification`**:

- `extends PreferenceAwareNotification implements ShouldQueue`, `use Queueable`, `onQueue('notifications')` في الباني.
- `type(): NotificationType::Digest`.
- `candidateChannels(): ['mail']` — بريد فقط (الملخّص بريدي بطبعه).
- **الباني:** `(string $frequency, int $totalCount, array $items)` — `$items` قائمة `{type, title}` (عنوان مختصر فقط، لا حمولة `data` كاملة — تقليل بيانات).
- **`toMail()`** (نصّ عادي، لا HTML خام — منع XSS):
  - subject: `$frequency === 'weekly' ? 'ملخّصك الأسبوعي' : 'ملخّصك اليومي'`.
  - سطر افتتاحي: «إليك ملخّص نشاطك: {totalCount} تنبيهاً جديداً».
  - لكل عنصر في `$items` (حتى السقف): سطر بعنوانه المختصر (`->line($item['title'])`).
  - إن كان `totalCount > count($items)`: سطر «و{الباقي} تنبيهات أخرى».
  - سطر ختامي + زرّ/رابط: «اطّلع على إشعاراتك» → صفحة `/notifications`.
- **`toArray()`:** غير لازم (لا قناة database للملخّص)؛ إن أُبقي للاتساق فيحمل `{ type: 'digest', count, frequency }` فقط.

> **لا يُرسَل عبر database:** الملخّص لا يُنشئ صفّ `notifications` جديداً (يتجنّب إغراق الوارد بملخّص يجمّع الوارد نفسه + يتجنّب أن يجمّع الملخّص التالي «إشعار الملخّص»).

---

## 5. عقد الـ API — ضبط التكرار

تُوسَّع نقطتا التفضيلات القائمتان (لا متحكّم جديد). **التكرار بُعد منفصل عن مصفوفة القنوات**، فيُضاف حقل مستقلّ.

### 5.أ — `GET /api/v1/notifications/preferences` (توسعة الاستجابة)

`PreferenceController::index` — تُضاف `digest` إلى الجسم بجوار `data` (مصفوفة القنوات تبقى كما هي، لا كسر):

```jsonc
{
  "data": [ /* مصفوفة type×channel كما هي */ ],
  "digest": { "frequency": "off" }      // أو "daily" / "weekly"
}
```

### 5.ب — `PUT /api/v1/notifications/preferences` (توسعة الطلب)

`PreferenceController::update` — يقبل **حقلاً اختيارياً** `digest_frequency` إضافةً إلى `preferences` (كلاهما اختياري بحدّ أدنى أحدهما):

- **الطلب (`UpdatePreferencesRequest` موسَّع):**
  | الحقل | التحقّق |
  |------|---------|
  | `preferences` | `sometimes` · `array` (كما هو؛ يصير `sometimes` بدل `required`) |
  | `preferences.*.type` | `required_with:preferences` · `Rule::enum(NotificationType::class)` |
  | `preferences.*.channel` | `required_with:preferences` · `Rule::enum(NotificationChannel::class)` |
  | `preferences.*.enabled` | `required_with:preferences` · `boolean` |
  | `digest_frequency` | `sometimes` · `Rule::in(['off','daily','weekly'])` (أو `Rule::enum(DigestFrequency::class)`) |
  | (إجمالاً) | قاعدة: على الأقلّ `preferences` أو `digest_frequency` موجود |

- **السلوك:** إن وُجد `digest_frequency` → `$request->user()->update(['digest_frequency' => ...])`. إن وُجد `preferences` → نفس الحلقة القائمة. (تبديل التكرار **لا** يلمس `last_digest_at` — تغيير التفضيل لا يرسل/يلغي ملخّصاً جارياً.)

- **الاستجابة `200 OK`:** نفس شكل `index` (المصفوفة + `digest.frequency` المُحدَّث).

### رموز الأخطاء (موحّدة مع المنصّة)

| الحالة | الرمز |
|-------|------|
| غير مصادَق | `401` |
| `digest_frequency` خارج القيم المسموحة | `422` |
| لا `preferences` ولا `digest_frequency` | `422` |

### حدود المعدّل

- يرث rate limiter الافتراضي لمجموعة `auth:sanctum` (لا حدّ خاص) — فعل إعدادات خفيف.

---

## 6. الموديول (حدود السياقات)

- **Notification (يملك المنطق):**
  - نوع `NotificationType::Digest` + (اختياري) enum `DigestFrequency`.
  - صنف `DigestNotification` (يرث `PreferenceAwareNotification`, `ShouldQueue`) في `app/Contexts/Notification/Infrastructure/Notifications/`.
  - توسعة `PreferenceController` (`index`/`update`) + `UpdatePreferencesRequest`.
- **التطبيق (Console):** `DispatchNotificationDigests` في `app/Console/Commands/` (طبقة تطبيق التشغيل، نمط الأوامر القائمة) — يقرأ `User` + `notifications` ويبثّ الإشعار. لا منطق Domain فيه.
- **Identity/User (يملك بُعد التكرار على الكيان):** عمودا `digest_frequency`/`last_digest_at` على `users` + `$fillable`/`casts`. لا منطق تخويل جديد (المستخدم يضبط تفضيله الخاصّ عبر `$request->user()`).
- **Domain لا يعرف Infrastructure:** الأمر (Console/Application) يستهلك `User`/`DatabaseNotification` (Infrastructure) ويبثّ `DigestNotification` (Infrastructure) — نفس طبقية الأوامر القائمة (`DispatchStudyPlanReminders` يستدعي `StudyPlanService`). لا تسرّب لطبقة Domain.

---

## 7. الأمان والامتثال

- **احترام تفضيل المستخدم (إلزامي):** من اختار `digest_frequency='off'` **مُستبعَد من الاستعلام أصلاً** (`where != 'off'`) → لا يُرسَل له. طبقة ثانية: من عطّل قناة `mail` لنوع `digest` في المصفوفة → `via()` يُسقطها → لا بريد حتى لو فعّل تكراراً.
- **idempotency (لا ازدواج):** `last_digest_at` يُضبط بعد كل إرسال؛ تشغيل الأمر مرّتين في نفس اليوم → الثاني لا يجد نشاطاً جديداً بعد آخر ضبط → لا إرسال. `withoutOverlapping()` يمنع تداخل تشغيلين متزامنين.
- **لا ملخّص فارغ:** صفر نشاط → لا بريد، ولا تحديث `last_digest_at` (النشاط اللاحق يُجمَّع في الملخّص التالي).
- **تقليل البيانات (PDPL §تقليل):** الملخّص يحمل **عناوين مختصرة + عدّ** فقط، لا حمولة `data` كاملة لكل إشعار؛ سقف 50 عنصراً في البريد. الأعمدة الجديدة (`digest_frequency`, `last_digest_at`) إعدادات تشغيلية لا بيانات شخصية حسّاسة.
- **استبعاد المتقاعد/المعطّل:** `whereNull('disabled_at')` + `SoftDeletes` (المحذوف خارج النطاق آلياً) → لا بريد لحساب معطّل/متقاعد.
- **منع XSS:** عناوين الإشعارات تُعرض كنصّ في البريد (`->line(...)`)، لا HTML خام.
- **عزل المستخدم:** كل ملخّص يُبنى من `$user->notifications()` (نطاق المستخدم نفسه فقط) — لا تسرّب نشاط مستخدم لآخر؛ إرسال فردي (`$user->notify`).
- **التوقيت بتوقيت المستخدم (الأسبوعي):** يوم البثّ يُحسَب بـ `User::timezone` — لا يُرسَل في يوم خاطئ للمستخدم.

---

## 8. معايير القبول (قابلة للاختبار)

### خلفي — Pest Feature/Unit (qa-tester)

**الأمر `notifications:dispatch-digests`** (`Notification::fake()`):
1. **يرسل لـ daily مستحقّ وله نشاط:** مستخدم `digest_frequency='daily'`، `last_digest_at = منذ يومين`، وله إشعاران منشآن اليوم → تشغيل الأمر يبثّ `DigestNotification` واحداً (`assertSentTo` ×1)، الصنف `implements ShouldQueue`.
2. **لا يرسل لـ off:** مستخدم `digest_frequency='off'` وله نشاط جديد → `assertNotSentTo` — لا ملخّص.
3. **لا يرسل بلا نشاط:** مستخدم `daily` مستحقّ لكن **لا إشعار جديد** بعد `last_digest_at` → `assertNothingSent`، و`last_digest_at` **لم يتغيّر**.
4. **لا يرسل قبل الموعد (daily):** `digest_frequency='daily'`، `last_digest_at = منذ ساعة` → غير مستحقّ → `assertNothingSent`.
5. **weekly يوم الأحد فقط:** `digest_frequency='weekly'` وله نشاط → يُرسَل حين اليوم المحلّي للمستخدم = الأحد؛ في يوم آخر (مع تثبيت `Date::setTestNow`) → `assertNothingSent`.
6. **يحدّث `last_digest_at` بعد الإرسال:** بعد إرسال ناجح → `last_digest_at ≈ now`.
7. **التشغيل المزدوج لا يكرّر:** تشغيل الأمر مرّتين متتاليتين (بلا نشاط جديد بينهما) → الإجمالي **إرسال واحد** فقط (يثبت idempotency عبر `last_digest_at`).
8. **أول ملخّص (`last_digest_at=null`) يجمّع النافذة فقط:** إشعار قديم (قبل النافذة) لا يُحتسَب؛ إشعار داخل آخر يوم/أسبوع يُحتسَب.
9. **استبعاد المعطّل:** مستخدم `daily` بـ `disabled_at != null` وله نشاط → `assertNotSentTo`.
10. **`--frequency=weekly` يقصر على الأسبوعي:** تشغيل بـ `--frequency=weekly` لا يرسل لمستخدمي `daily`.

**التفضيل/opt-out:**
11. **opt-out على mail لنوع digest:** مستخدم `daily` مستحقّ لكنّه عطّل `mail` لنوع `digest` → `via()` لا يُرجع `mail` → لا بريد فعلي (يُثبَت عبر `assertSentTo` ثم فحص قنواته، أو `NotificationFake::assertSentTo($user, DigestNotification, fn ($n, $channels) => ! in_array('mail', $channels))`).

**عقد الـ API:**
12. **`GET /preferences`** يُرجع `digest.frequency` (افتراضي `off` لمستخدم جديد) إضافةً إلى المصفوفة.
13. **`PUT /preferences` بـ `digest_frequency='weekly'`** → `200`، `users.digest_frequency='weekly'`، والاستجابة تعكسه؛ المصفوفة بلا `preferences` لا تتأثّر.
14. **قيمة غير صالحة → 422:** `digest_frequency='hourly'` → `422`، لا تغيير.
15. **طلب فارغ (لا preferences ولا digest_frequency) → 422.**
16. **PUT بـ preferences فقط (سلوك D-سابق)** ما زال يعمل دون `digest_frequency` (عدم كسر).

### أمامي — Vitest (qa-tester)

- قسم «تكرار الملخّص» يعرض الخيار المختار من `digest.frequency`؛ تغييره يستدعي `PUT` بـ `digest_frequency`؛ حالات تحميل/خطأ/نجاح؛ RTL محفوظ؛ a11y (مجموعة راديو مسمّاة، أو `<select>` بـ `<label>`).

---

## 9. الواجهة (frontend-dev)

**الملف:** `frontend/src/app/notifications/page.tsx` (توسعة، لا صفحة جديدة).

### 9.أ — قسم «تكرار الملخّص»

- يُضاف قسم أعلى/أسفل جدول المصفوفة بعنوان «ملخّص النشاط عبر البريد».
- **عنصر تحكّم:** مجموعة راديو (أو `<select>`) بثلاثة خيارات: **فوري (إيقاف الملخّص)** = `off` · **يومي** = `daily` · **أسبوعي** = `weekly`.
- الحالة الأولية من `digest.frequency` في استجابة `GET /notifications/preferences`.
- **التغيير:** `PUT /notifications/preferences` بجسم `{ digest_frequency: <value> }` → تحديث الحالة من الاستجابة.
- نصّ توضيحي مختصر: «يومي: بريد واحد بنشاطك اليومي. أسبوعي: ملخّص كلّ أحد. فوري: تصلك الإشعارات لحظياً دون تجميع.»

### 9.ب — الأنواع (`frontend/src/lib/types.ts`)

```ts
export type DigestFrequency = 'off' | 'daily' | 'weekly';

// توسعة استجابة preferences
export interface PreferencesResponse {
  data: Preference[];
  digest: { frequency: DigestFrequency };
}
```

### 9.ج — مفاتيح i18n (`frontend/src/i18n/dictionary.ts` — ar + en)

`notifications.digestTitle` («ملخّص النشاط عبر البريد»)، `notifications.digestOff` («فوري — دون تجميع»)، `notifications.digestDaily` («ملخّص يومي»)، `notifications.digestWeekly` («ملخّص أسبوعي»)، `notifications.digestHint` (النصّ التوضيحي أعلاه)، `notifications.digestError` («تعذّر تحديث تكرار الملخّص»).

### 9.د — a11y (تنسيق compliance)

- مجموعة الراديو ضمن `<fieldset>` + `<legend>` (أو `<select>` بـ `<label htmlFor>`)؛ تباين AA؛ تشغيل بلوحة المفاتيح.
- رسائل النجاح/الخطأ عبر `StatusMessage` (`role="status"`/`role="alert"`).

---

## 10. تقسيم المسؤوليات

| الدور | المهام |
|------|--------|
| **backend-dev (رئيسي)** | هجرة `digest_frequency`+`last_digest_at` على `users` (+ فهرس مركّب) + `$fillable`/`casts` على `User`؛ نوع `NotificationType::Digest` (+ enum `DigestFrequency` اختياري)؛ صنف `DigestNotification` (`ShouldQueue`، mail فقط، مرآة `CourseAnnouncementNotification`)؛ أمر `DispatchNotificationDigests` (نمط `DispatchStudyPlanReminders`: `chunkById`/استحقاق daily-weekly/تجميع `notifications` منذ `last_digest_at`/منع الفارغ/تحديث `last_digest_at`)؛ سطر جدولة في `routes/console.php`؛ توسعة `PreferenceController` (`index`+`update`) و`UpdatePreferencesRequest`. إعادة استخدام `PreferenceAwareNotification`/`$user->notifications()` — لا اختراع. |
| **frontend-dev** | قسم «تكرار الملخّص» في `notifications/page.tsx` (راديو/select بـ `aria`، حالة من `digest.frequency`، `PUT digest_frequency`) + نوعا `DigestFrequency`/`PreferencesResponse` في `types.ts` + مفاتيح i18n (ar/en). إعادة استخدام `StatusMessage`/`PageHeader`/`api`. |
| **qa-tester** | Feature tests §8 (الأمر: daily مستحقّ بنشاط/off/لا نشاط/قبل الموعد/weekly-أحد/تحديث last_digest_at/تشغيل مزدوج/أول ملخّص/معطّل/`--frequency`؛ opt-out على mail؛ API: GET digest/PUT weekly/422 غير صالح/422 فارغ/عدم كسر preferences) + اختبار أمامي للقسم وحالاته. |
| **compliance** | احترام التفضيل (`off` مُستبعَد + opt-out على `mail/digest`)؛ تقليل البيانات (عناوين+عدّ لا حمولة كاملة، سقف 50)؛ استبعاد المعطّل/المتقاعد؛ idempotency (لا ازدواج/لا فارغ)؛ عزل نشاط المستخدم؛ XSS (عرض نصّ)؛ a11y (fieldset/legend أو label، تباين AA)؛ RTL. |

---

## 11. ما هو خارج النطاق (لا هندسة زائدة)

- لا تخصيص **ساعة إرسال** لكل منطقة زمنية (التشغيل بتوقيت الخادم؛ الأسبوعي فقط يحترم يوم المستخدم) — مؤجَّل.
- لا تخصيص **يوم أسبوعي** قابل للاختيار من المستخدم (ثابت: الأحد) — مؤجَّل.
- لا مصادر نشاط مخصّصة لكل سياق (ردود/درجات/إعلانات منفصلة) — المصدر الموحَّد `notifications` يكفي ويشمل الجميع آلياً.
- لا ملخّص عبر قنوات غير mail (لا SMS/واتساب/push digest).
- لا قناة database للملخّص (لا صفّ وارد للملخّص نفسه).
- لا «شهري» أو ترددات إضافية — `off/daily/weekly` فقط.
- لا تحويل الإشعارات الفورية القائمة إلى تجميع إجباري — الفوري يبقى افتراضاً (`off`).
- لا علاقة براية `payments.enabled`.
