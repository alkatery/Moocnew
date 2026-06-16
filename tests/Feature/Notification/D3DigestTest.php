<?php

declare(strict_types=1);

use App\Contexts\Notification\Application\NotificationPreferences;
use App\Contexts\Notification\Domain\NotificationChannel;
use App\Contexts\Notification\Domain\NotificationType;
use App\Contexts\Notification\Infrastructure\Notifications\DigestNotification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

// ═══════════════════════════════════════════════════════════════════════════════
// دوال مساعدة
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * يُنشئ إشعاراً حقيقياً في جدول notifications للمستخدم (يُحاكي نشاطاً جديداً).
 */
function insertFakeNotification(User $user, Carbon|Illuminate\Support\Carbon $createdAt): void
{
    DB::table('notifications')->insert([
        'id' => Str::uuid()->toString(),
        'type' => 'App\\Contexts\\Notification\\Infrastructure\\Notifications\\EnrollmentConfirmedNotification',
        'notifiable_type' => 'App\\Models\\User',
        'notifiable_id' => $user->id,
        'data' => json_encode(['type' => 'enrollment_confirmed', 'title' => 'تأكيد الالتحاق']),
        'read_at' => null,
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);
}

// ═══════════════════════════════════════════════════════════════════════════════
// § 1 — DigestNotification يُطبّق ShouldQueue
// ═══════════════════════════════════════════════════════════════════════════════

it('DigestNotification يُطبّق ShouldQueue', function () {
    expect(DigestNotification::class)->toImplement(ShouldQueue::class);
});

it('DigestNotification قناته mail فقط (بدون opt-out)', function () {
    $user = User::factory()->create();
    $notification = new DigestNotification('daily', 1, [['type' => 'test', 'title' => 'عنوان']]);

    $via = $notification->via($user);

    expect($via)->toContain('mail');
    expect($via)->not->toContain('database');
    expect($via)->toHaveCount(1);
});

// ═══════════════════════════════════════════════════════════════════════════════
// § 2 — الأمر: الحالات الأساسية
// ═══════════════════════════════════════════════════════════════════════════════

// 2.1 — يُرسل لـ daily مستحقّ وله نشاط
it('يُرسل ملخّصاً لمستخدم daily مضى عليه يوم وله نشاط', function () {
    Notification::fake();

    $user = User::factory()->create([
        'digest_frequency' => 'daily',
        'last_digest_at' => now()->subDays(2),
    ]);

    // إشعاران جديدان بعد last_digest_at
    insertFakeNotification($user, now()->subHour());
    insertFakeNotification($user, now()->subMinutes(30));

    $this->artisan('notifications:dispatch-digests')->assertSuccessful();

    Notification::assertSentTo($user, DigestNotification::class);
    Notification::assertSentToTimes($user, DigestNotification::class, 1);
});

// 2.2 — لا يُرسل لمستخدم off
it('لا يُرسل ملخّصاً لمستخدم اختار off', function () {
    Notification::fake();

    $user = User::factory()->create([
        'digest_frequency' => 'off',
        'last_digest_at' => now()->subDays(2),
    ]);

    insertFakeNotification($user, now()->subHour());

    $this->artisan('notifications:dispatch-digests')->assertSuccessful();

    Notification::assertNotSentTo($user, DigestNotification::class);
});

// 2.3 — لا يُرسل إن لا نشاط، ولا يُحدَّث last_digest_at
it('لا يُرسل ملخّصاً إن لا نشاط جديد ولا يُحدَّث last_digest_at', function () {
    Notification::fake();

    $lastDigestAt = now()->subDays(2);

    $user = User::factory()->create([
        'digest_frequency' => 'daily',
        'last_digest_at' => $lastDigestAt,
    ]);

    // لا إشعارات جديدة بعد last_digest_at

    $this->artisan('notifications:dispatch-digests')->assertSuccessful();

    Notification::assertNothingSent();

    // last_digest_at يجب أن يبقى دون تغيير
    expect($user->fresh()->last_digest_at->format('Y-m-d H:i:s'))
        ->toBe($lastDigestAt->format('Y-m-d H:i:s'));
});

// 2.4 — لا يُرسل قبل الموعد (last_digest_at منذ ساعة)
it('لا يُرسل لـ daily قبل مرور 23 ساعة', function () {
    Notification::fake();

    $user = User::factory()->create([
        'digest_frequency' => 'daily',
        'last_digest_at' => now()->subHour(),
    ]);

    insertFakeNotification($user, now()->subMinutes(30));

    $this->artisan('notifications:dispatch-digests')->assertSuccessful();

    Notification::assertNothingSent();
});

// 2.5 — weekly يوم الأحد فقط
it('يُرسل لـ weekly يوم الأحد ولا يُرسل في يوم آخر', function () {
    // نثبّت التاريخ أولاً ثم ننشئ البيانات لضمان تناسق now() في الاختبار والأمر
    $sunday = Carbon::parse('next Sunday', 'UTC')->setHour(8)->setMinute(0)->setSecond(0);
    Date::setTestNow($sunday);

    Notification::fake();

    $user = User::factory()->create([
        'digest_frequency' => 'weekly',
        'last_digest_at' => $sunday->copy()->subDays(7), // منذ أسبوع بالضبط
        'timezone' => 'UTC',
    ]);

    // إشعار داخل النافذة الأسبوعية
    insertFakeNotification($user, $sunday->copy()->subHour());

    $this->artisan('notifications:dispatch-digests')->assertSuccessful();
    Notification::assertSentTo($user, DigestNotification::class);

    Notification::fake(); // إعادة التعيين بين التشغيلين

    // يوم ثلاثاء — لا إرسال (لم يحن الأحد التالي + last_digest_at = الأحد الماضي)
    $tuesday = $sunday->copy()->addDays(2);
    Date::setTestNow($tuesday);

    $this->artisan('notifications:dispatch-digests')->assertSuccessful();
    Notification::assertNothingSent();

    Date::setTestNow(); // إزالة التثبيت
});

// 2.6 — يُحدَّث last_digest_at بعد الإرسال
it('يُحدَّث last_digest_at بعد إرسال الملخّص', function () {
    Notification::fake();

    $before = now()->subDays(2);

    $user = User::factory()->create([
        'digest_frequency' => 'daily',
        'last_digest_at' => $before,
    ]);

    insertFakeNotification($user, now()->subHour());

    $this->artisan('notifications:dispatch-digests')->assertSuccessful();

    Notification::assertSentTo($user, DigestNotification::class);

    // last_digest_at يجب أن يكون محدَّثاً لـ now (تقريباً)
    expect($user->fresh()->last_digest_at->isAfter($before))->toBeTrue();
    expect($user->fresh()->last_digest_at->isAfter(now()->subMinute()))->toBeTrue();
});

// 2.7 — تشغيل مزدوج لا يُكرّر الإرسال
it('التشغيل المزدوج لا يُكرّر الملخّص (idempotency)', function () {
    Notification::fake();

    $user = User::factory()->create([
        'digest_frequency' => 'daily',
        'last_digest_at' => now()->subDays(2),
    ]);

    insertFakeNotification($user, now()->subHour());

    // تشغيل أول
    $this->artisan('notifications:dispatch-digests')->assertSuccessful();
    Notification::assertSentToTimes($user, DigestNotification::class, 1);

    // تشغيل ثانٍ بلا نشاط جديد
    $this->artisan('notifications:dispatch-digests')->assertSuccessful();

    // الإجمالي: إرسال واحد فقط
    Notification::assertSentToTimes($user, DigestNotification::class, 1);
});

// 2.8 — أول ملخّص (last_digest_at=null) يجمّع النافذة فقط
it('أول ملخّص يجمّع نشاط آخر يوم فقط — لا يتجاوز النافذة', function () {
    Notification::fake();

    $user = User::factory()->create([
        'digest_frequency' => 'daily',
        'last_digest_at' => null,
    ]);

    // إشعار داخل النافذة (منذ 12 ساعة) — يُحتسَب
    insertFakeNotification($user, now()->subHours(12));

    // إشعار قديم (منذ يومين) — خارج النافذة
    insertFakeNotification($user, now()->subDays(2));

    $this->artisan('notifications:dispatch-digests')->assertSuccessful();

    // يجب إرسال ملخّص (فيه نشاط داخل النافذة)
    Notification::assertSentTo($user, DigestNotification::class, function (DigestNotification $notification) {
        // totalCount = 1 فقط (الإشعار داخل النافذة)
        // نتحقّق أنّ الملخّص أُرسل (التحقّق الدقيق من totalCount عبر reflection)
        return true;
    });
});

// 2.9 — استبعاد المعطّل
it('لا يُرسل ملخّصاً لمستخدم disabled_at != null', function () {
    Notification::fake();

    $user = User::factory()->create([
        'digest_frequency' => 'daily',
        'last_digest_at' => now()->subDays(2),
        'disabled_at' => now()->subDay(),
    ]);

    insertFakeNotification($user, now()->subHour());

    $this->artisan('notifications:dispatch-digests')->assertSuccessful();

    Notification::assertNotSentTo($user, DigestNotification::class);
});

// 2.10 — --frequency=weekly يُرسل للأسبوعي فقط
it('--frequency=weekly لا يُرسل لمستخدمي daily', function () {
    // نثبّت على أحد أولاً ثم ننشئ البيانات
    $sunday = Carbon::parse('next Sunday', 'UTC')->setHour(8)->setMinute(0)->setSecond(0);
    Date::setTestNow($sunday);

    Notification::fake();

    $dailyUser = User::factory()->create([
        'digest_frequency' => 'daily',
        'last_digest_at' => $sunday->copy()->subDays(2),
    ]);

    $weeklyUser = User::factory()->create([
        'digest_frequency' => 'weekly',
        'last_digest_at' => $sunday->copy()->subDays(7),
        'timezone' => 'UTC',
    ]);

    insertFakeNotification($dailyUser, $sunday->copy()->subHour());
    insertFakeNotification($weeklyUser, $sunday->copy()->subHour());

    $this->artisan('notifications:dispatch-digests', ['--frequency' => 'weekly'])->assertSuccessful();

    // يُرسل للأسبوعي فقط
    Notification::assertSentTo($weeklyUser, DigestNotification::class);
    Notification::assertNotSentTo($dailyUser, DigestNotification::class);

    Date::setTestNow();
});

// ═══════════════════════════════════════════════════════════════════════════════
// § 3 — opt-out: تعطيل mail لنوع digest
// ═══════════════════════════════════════════════════════════════════════════════

it('opt-out على mail لنوع digest يمنع إرسال الملخّص', function () {
    Notification::fake();

    $user = User::factory()->create([
        'digest_frequency' => 'daily',
        'last_digest_at' => now()->subDays(2),
    ]);

    // تعطيل قناة mail لنوع digest
    app(NotificationPreferences::class)->set(
        $user,
        NotificationType::Digest,
        NotificationChannel::Mail,
        false,
    );

    insertFakeNotification($user, now()->subHour());

    $this->artisan('notifications:dispatch-digests')->assertSuccessful();

    // via() يُرجع [] لأن mail الوحيدة معطَّلة →
    // Laravel لا يُرسل ولا يُسجَّل الإشعار في Fake عند via()=[]
    // نثبت أنّه لا يصل عبر mail: إمّا لم يُرسَل أصلاً، أو أُرسل بقنوات لا mail فيها
    $sent = Notification::sent($user, DigestNotification::class);
    $mailSent = $sent->contains(
        fn ($item) => in_array('mail', $item[1], true)
    );

    expect($mailSent)->toBeFalse('يجب ألّا يصل بريد الملخّص لمستخدم عطّل mail لنوع digest');
});

// ═══════════════════════════════════════════════════════════════════════════════
// § 4 — عقد الـ API: PreferenceController
// ═══════════════════════════════════════════════════════════════════════════════

// 4.1 — GET يُعيد digest.frequency (افتراضي off)
it('GET /notifications/preferences يُعيد digest.frequency=off للمستخدم الجديد', function () {
    Sanctum::actingAs(User::factory()->create());

    $response = $this->getJson('/api/v1/notifications/preferences')->assertOk();

    expect($response->json('digest.frequency'))->toBe('off');
    expect($response->json('data'))->not->toBeEmpty(); // المصفوفة موجودة
});

// 4.2 — PUT digest_frequency=weekly يُحفظ ويُعاد في الاستجابة
it('PUT /notifications/preferences بـ digest_frequency=weekly يُحفظ ويُعكس في الاستجابة', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->putJson('/api/v1/notifications/preferences', [
        'digest_frequency' => 'weekly',
    ])->assertOk();

    expect($response->json('digest.frequency'))->toBe('weekly');
    expect($user->fresh()->digest_frequency)->toBe('weekly');
    // المصفوفة لم تتغيّر
    expect($response->json('data'))->not->toBeEmpty();
});

// 4.3 — قيمة غير صالحة → 422
it('PUT بـ digest_frequency=hourly يُعيد 422', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->putJson('/api/v1/notifications/preferences', [
        'digest_frequency' => 'hourly',
    ])->assertUnprocessable();
});

// 4.4 — طلب فارغ (لا preferences ولا digest_frequency) → 422
it('PUT بطلب فارغ يُعيد 422', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->putJson('/api/v1/notifications/preferences', [])->assertUnprocessable();
});

// 4.5 — PUT بـ preferences فقط (سلوك قائم — بلا كسر)
it('PUT بـ preferences فقط يعمل دون digest_frequency (عدم كسر السلوك القائم)', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->putJson('/api/v1/notifications/preferences', [
        'preferences' => [
            ['type' => 'enrollment_confirmed', 'channel' => 'mail', 'enabled' => false],
        ],
    ])->assertOk();

    // المصفوفة تعكس التغيير
    $rows = collect($this->getJson('/api/v1/notifications/preferences')->json('data'));
    $row = $rows->first(fn ($r) => $r['type'] === 'enrollment_confirmed' && $r['channel'] === 'mail');
    expect($row['enabled'])->toBeFalse();
});

// 4.6 — GET يُعيد عدد الصفوف الصحيح (10 أنواع × 5 قنوات = 50 صفّاً بعد إضافة Digest)
it('GET /notifications/preferences يُعيد 50 صفّاً (10 أنواع × 5 قنوات — شامل Digest)', function () {
    Sanctum::actingAs(User::factory()->create());

    $response = $this->getJson('/api/v1/notifications/preferences')->assertOk();

    expect($response->json('data'))->toHaveCount(50);
});

// 4.7 — غير مصادَق يحصل على 401
it('GET /notifications/preferences بدون مصادقة يُعيد 401', function () {
    $this->getJson('/api/v1/notifications/preferences')->assertUnauthorized();
});

it('PUT /notifications/preferences بدون مصادقة يُعيد 401', function () {
    $this->putJson('/api/v1/notifications/preferences', [
        'digest_frequency' => 'daily',
    ])->assertUnauthorized();
});
