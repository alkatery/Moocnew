<?php

declare(strict_types=1);

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Enrollment\Application\EnrollmentService;
use App\Contexts\Enrollment\Domain\EnrollmentStatus;
use App\Contexts\Enrollment\Infrastructure\Persistence\Enrollment;
use App\Contexts\Identity\Domain\Role;
use App\Contexts\Identity\Infrastructure\Persistence\ActivityLog;
use App\Contexts\Notification\Application\NotificationPreferences;
use App\Contexts\Notification\Domain\NotificationChannel;
use App\Contexts\Notification\Domain\NotificationType;
use App\Contexts\Notification\Infrastructure\Notifications\CourseAnnouncementNotification;
use App\Contexts\Notification\Infrastructure\Notifications\CourseBulkEmailNotification;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

// ─── إعداد مشترك ──────────────────────────────────────────────────────────────

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

// ─── دوال مساعدة ──────────────────────────────────────────────────────────────

/**
 * ينشئ مقرراً منشوراً مع مدرّسه (طاقم المقرر).
 */
function c3Course(): array
{
    $instructor = userWithRole(Role::Instructor);
    $course = Course::factory()
        ->for($instructor, 'instructor')
        ->published()
        ->create(['pricing_type' => 'free', 'price_minor' => 0]);

    return [$course, $instructor];
}

/**
 * يُسجّل متعلّماً في المقرر بالحالة المطلوبة.
 * ملاحظة: يُرسل EnrollmentConfirmedNotification (متزامن) عند الالتحاق.
 * لذا يجب استدعاء Notification::fake() بعد c3Enroll إن أردنا assertCount دقيقاً.
 */
function c3Enroll(User $learner, Course $course, EnrollmentStatus $status = EnrollmentStatus::Active): void
{
    app(EnrollmentService::class)->enroll($learner, $course);

    if ($status !== EnrollmentStatus::Active) {
        Enrollment::query()
            ->where('user_id', $learner->id)
            ->where('course_id', $course->id)
            ->update(['status' => $status->value]);
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// § 1 — الطوابير: ShouldQueue + الإشعارات تُدفع مهاماً
// ═══════════════════════════════════════════════════════════════════════════════

it('CourseAnnouncementNotification يُطبّق ShouldQueue', function () {
    expect(CourseAnnouncementNotification::class)->toImplement(ShouldQueue::class);
});

it('CourseBulkEmailNotification يُطبّق ShouldQueue', function () {
    expect(CourseBulkEmailNotification::class)->toImplement(ShouldQueue::class);
});

it('نشر إعلان يُدفع إشعاراً مُطابوراً لكل ملتحق نشط', function () {
    [$course, $instructor] = c3Course();
    $learner1 = userWithRole(Role::Student);
    $learner2 = userWithRole(Role::Student);
    $learner3 = userWithRole(Role::Student);

    c3Enroll($learner1, $course);
    c3Enroll($learner2, $course);
    c3Enroll($learner3, $course);

    // Notification::fake بعد c3Enroll لتجنّب تسجيل EnrollmentConfirmedNotification
    Notification::fake();

    Sanctum::actingAs($instructor);

    $this->postJson("/api/v1/courses/{$course->slug}/announcements", [
        'title' => 'موعد الاختبار النهائي',
        'body' => 'سيُعقد الاختبار يوم الأحد.',
    ])->assertCreated();

    // إشعار مُدفوع منفصل لكل ملتحق (×3)
    Notification::assertSentTo($learner1, CourseAnnouncementNotification::class);
    Notification::assertSentTo($learner2, CourseAnnouncementNotification::class);
    Notification::assertSentTo($learner3, CourseAnnouncementNotification::class);

    // 3 مهام منفصلة فقط (إشعارات الالتحاق الثلاثة حدثت قبل fake)
    Notification::assertCount(3);
});

it('إرسال بريد جماعي يُدفع إشعاراً مُطابوراً لكل ملتحق نشط', function () {
    [$course, $instructor] = c3Course();
    $learner1 = userWithRole(Role::Student);
    $learner2 = userWithRole(Role::Student);
    $learner3 = userWithRole(Role::Student);

    c3Enroll($learner1, $course);
    c3Enroll($learner2, $course);
    c3Enroll($learner3, $course);

    Notification::fake();

    Sanctum::actingAs($instructor);

    $this->postJson("/api/v1/courses/{$course->slug}/bulk-email", [
        'subject' => 'مستجدات المقرر',
        'body' => 'نود إعلامكم بما يلي…',
    ])->assertStatus(202);

    Notification::assertSentTo($learner1, CourseBulkEmailNotification::class);
    Notification::assertSentTo($learner2, CourseBulkEmailNotification::class);
    Notification::assertSentTo($learner3, CourseBulkEmailNotification::class);

    // 3 مهام بريد — كل مستلم يحصل على رسالته المنفصلة
    Notification::assertCount(3);
});

it('recipients_queued في الاستجابة يطابق عدد الملتحقين النشطين', function () {
    [$course, $instructor] = c3Course();
    $learner1 = userWithRole(Role::Student);
    $learner2 = userWithRole(Role::Student);

    c3Enroll($learner1, $course);
    c3Enroll($learner2, $course);

    Notification::fake();

    Sanctum::actingAs($instructor);

    $responseAnnounce = $this->postJson("/api/v1/courses/{$course->slug}/announcements", [
        'title' => 'إعلان',
        'body' => 'نصّ',
    ])->assertCreated();

    expect($responseAnnounce->json('recipients_queued'))->toBe(2);

    $responseBulk = $this->postJson("/api/v1/courses/{$course->slug}/bulk-email", [
        'subject' => 'موضوع',
        'body' => 'نصّ',
    ])->assertStatus(202);

    expect($responseBulk->json('recipients_queued'))->toBe(2);
});

// ═══════════════════════════════════════════════════════════════════════════════
// § 2 — opt-out: متعلّم عطّل قناة لا يستقبلها
// ═══════════════════════════════════════════════════════════════════════════════

it('متعلّم عطّل mail لـ course_bulk_email لا يستقبل البريد؛ الآخرون يستقبلون', function () {
    [$course, $instructor] = c3Course();
    $optedOut = userWithRole(Role::Student);
    $active = userWithRole(Role::Student);

    c3Enroll($optedOut, $course);
    c3Enroll($active, $course);

    // المتعلّم الأول يُعطّل mail لنوع البريد الجماعي
    app(NotificationPreferences::class)->set(
        $optedOut,
        NotificationType::CourseBulkEmail,
        NotificationChannel::Mail,
        false,
    );

    Notification::fake();

    Sanctum::actingAs($instructor);

    $this->postJson("/api/v1/courses/{$course->slug}/bulk-email", [
        'subject' => 'موضوع',
        'body' => 'نصّ',
    ])->assertStatus(202);

    // CourseBulkEmail قناته mail فقط — إذا عُطِّلت → via() = [] → لا يُسجَّل في fake
    // المتعلّم المُعطَّل لا يستقبل أي إشعار
    Notification::assertNotSentTo($optedOut, CourseBulkEmailNotification::class);

    // المتعلّم النشط يستقبل البريد عادةً
    Notification::assertSentTo(
        $active,
        CourseBulkEmailNotification::class,
        fn ($notification, array $channels) => in_array('mail', $channels, true),
    );
});

it('متعلّم عطّل mail لـ course_announcement لا يستقبله بريداً؛ لكن يُرسَل له database', function () {
    [$course, $instructor] = c3Course();
    $optedOut = userWithRole(Role::Student);

    c3Enroll($optedOut, $course);

    // عطّل قناة mail للإعلانات
    app(NotificationPreferences::class)->set(
        $optedOut,
        NotificationType::CourseAnnouncement,
        NotificationChannel::Mail,
        false,
    );

    Notification::fake();

    Sanctum::actingAs($instructor);

    $this->postJson("/api/v1/courses/{$course->slug}/announcements", [
        'title' => 'إعلان',
        'body' => 'نصّ',
    ])->assertCreated();

    // يصله database دون mail (via() يُرجع ['database'] فقط)
    Notification::assertSentTo(
        $optedOut,
        CourseAnnouncementNotification::class,
        function ($notification, array $channels) {
            return in_array('database', $channels, true)
                && ! in_array('mail', $channels, true);
        },
    );
});

it('متعلّم عطّل database لـ course_announcement لا يستقبله في الوارد', function () {
    [$course, $instructor] = c3Course();
    $optedOut = userWithRole(Role::Student);

    c3Enroll($optedOut, $course);

    app(NotificationPreferences::class)->set(
        $optedOut,
        NotificationType::CourseAnnouncement,
        NotificationChannel::Database,
        false,
    );

    Notification::fake();

    Sanctum::actingAs($instructor);

    $this->postJson("/api/v1/courses/{$course->slug}/announcements", [
        'title' => 'إعلان',
        'body' => 'نصّ',
    ])->assertCreated();

    // via() يُرجع ['mail'] فقط — database مُعطَّل
    Notification::assertSentTo(
        $optedOut,
        CourseAnnouncementNotification::class,
        fn ($notification, array $channels) => ! in_array('database', $channels, true),
    );
});

// ═══════════════════════════════════════════════════════════════════════════════
// § 3 — التخويل: 403 لغير الطاقم
// ═══════════════════════════════════════════════════════════════════════════════

it('متعلّم عادي يحصل على 403 عند نشر إعلان', function () {
    [$course] = c3Course();
    $student = userWithRole(Role::Student);
    c3Enroll($student, $course);

    Sanctum::actingAs($student);

    $this->postJson("/api/v1/courses/{$course->slug}/announcements", [
        'title' => 'محاولة غير مصرّح بها',
        'body' => 'نصّ',
    ])->assertForbidden();
});

it('متعلّم عادي يحصل على 403 عند إرسال بريد جماعي', function () {
    [$course] = c3Course();
    $student = userWithRole(Role::Student);
    c3Enroll($student, $course);

    Sanctum::actingAs($student);

    $this->postJson("/api/v1/courses/{$course->slug}/bulk-email", [
        'subject' => 'محاولة',
        'body' => 'نصّ',
    ])->assertForbidden();
});

it('مدرّس مقرر آخر يحصل على 403 عند نشر إعلان', function () {
    [$course] = c3Course();
    [, $otherInstructor] = c3Course();

    Sanctum::actingAs($otherInstructor);

    $this->postJson("/api/v1/courses/{$course->slug}/announcements", [
        'title' => 'محاولة',
        'body' => 'نصّ',
    ])->assertForbidden();
});

it('مالك المقرر يستطيع نشر إعلان (201)', function () {
    Notification::fake();
    [$course, $instructor] = c3Course();

    Sanctum::actingAs($instructor);

    $this->postJson("/api/v1/courses/{$course->slug}/announcements", [
        'title' => 'إعلان المالك',
        'body' => 'نصّ',
    ])->assertCreated();
});

it('مستخدم بصلاحية courses.review يستطيع نشر إعلان (201)', function () {
    Notification::fake();
    [$course] = c3Course();
    $supervisor = userWithRole(Role::Supervisor);

    Sanctum::actingAs($supervisor);

    $this->postJson("/api/v1/courses/{$course->slug}/announcements", [
        'title' => 'إعلان المشرف',
        'body' => 'نصّ',
    ])->assertCreated();
});

it('مستخدم غير مُصادَق يحصل على 401', function () {
    [$course] = c3Course();

    $this->postJson("/api/v1/courses/{$course->slug}/announcements", [
        'title' => 'إعلان',
        'body' => 'نصّ',
    ])->assertUnauthorized();

    $this->postJson("/api/v1/courses/{$course->slug}/bulk-email", [
        'subject' => 'بريد',
        'body' => 'نصّ',
    ])->assertUnauthorized();
});

// ═══════════════════════════════════════════════════════════════════════════════
// § 4 — التدقيق: يُكتب بالعدد لا بنصّ البريد
// ═══════════════════════════════════════════════════════════════════════════════

it('announcement.sent يُدقَّق بـ recipients وcourse_id بعد نشر الإعلان', function () {
    Notification::fake();

    [$course, $instructor] = c3Course();
    $learner = userWithRole(Role::Student);
    c3Enroll($learner, $course);

    Sanctum::actingAs($instructor);

    $this->postJson("/api/v1/courses/{$course->slug}/announcements", [
        'title' => 'إعلان',
        'body' => 'نصّ',
    ])->assertCreated();

    $log = ActivityLog::query()
        ->where('event', 'announcement.sent')
        ->latest()
        ->first();

    expect($log)->not->toBeNull();
    expect($log->properties['recipients'])->toBe(1);
    expect($log->properties['course_id'])->toBe($course->id);

    // لا يجب أن يحوي نصّ الإعلان (تقليل بيانات)
    expect(isset($log->properties['title']))->toBeFalse();
    expect(isset($log->properties['body']))->toBeFalse();
});

it('bulk_email.sent يُدقَّق بـ recipients وaudience دون نصّ البريد', function () {
    Notification::fake();

    [$course, $instructor] = c3Course();
    $learner = userWithRole(Role::Student);
    c3Enroll($learner, $course);

    Sanctum::actingAs($instructor);

    $this->postJson("/api/v1/courses/{$course->slug}/bulk-email", [
        'subject' => 'موضوع',
        'body' => 'نصّ سري — لا يجب أن يُحفظ',
    ])->assertStatus(202);

    $log = ActivityLog::query()
        ->where('event', 'bulk_email.sent')
        ->latest()
        ->first();

    expect($log)->not->toBeNull();
    expect($log->properties['recipients'])->toBe(1);
    expect($log->properties['audience'])->toBe('all_active');

    // التحقّق من تقليل البيانات: لا نصّ البريد في التدقيق
    expect(isset($log->properties['subject']))->toBeFalse();
    expect(isset($log->properties['body']))->toBeFalse();
});

// ═══════════════════════════════════════════════════════════════════════════════
// § 5 — الإعلان يُخزَّن ويظهر في GET؛ البريد لا يُخزَّن
// ═══════════════════════════════════════════════════════════════════════════════

it('POST /announcements يُنشئ صفّاً في course_announcements', function () {
    Notification::fake();

    [$course, $instructor] = c3Course();

    Sanctum::actingAs($instructor);

    $this->postJson("/api/v1/courses/{$course->slug}/announcements", [
        'title' => 'موعد الاختبار',
        'body' => 'الاختبار يوم الأحد.',
    ])->assertCreated();

    $this->assertDatabaseHas('course_announcements', [
        'course_id' => $course->id,
        'author_id' => $instructor->id,
        'title' => 'موعد الاختبار',
    ]);
});

it('GET /announcements يُعيد الإعلانات لمالك المقرر', function () {
    Notification::fake();

    [$course, $instructor] = c3Course();

    Sanctum::actingAs($instructor);

    $this->postJson("/api/v1/courses/{$course->slug}/announcements", [
        'title' => 'إعلان 1',
        'body' => 'نصّ',
    ])->assertCreated();

    $response = $this->getJson("/api/v1/courses/{$course->slug}/announcements")
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.title'))->toBe('إعلان 1');
    expect($response->json('data.0.author.id'))->toBe($instructor->id);
    expect($response->json('meta'))->toHaveKeys(['current_page', 'last_page', 'per_page', 'total']);
});

it('GET /announcements يُعيد الإعلانات لمتعلّم نشط', function () {
    [$course, $instructor] = c3Course();
    $learner = userWithRole(Role::Student);
    c3Enroll($learner, $course);

    Notification::fake();

    Sanctum::actingAs($instructor);
    $this->postJson("/api/v1/courses/{$course->slug}/announcements", [
        'title' => 'إعلان للمتعلّم',
        'body' => 'نصّ',
    ])->assertCreated();

    Sanctum::actingAs($learner);
    $this->getJson("/api/v1/courses/{$course->slug}/announcements")
        ->assertOk()
        ->assertJsonPath('data.0.title', 'إعلان للمتعلّم');
});

it('GET /announcements يُعيد 403 لمستخدم غير ملتحق وغير طاقم', function () {
    [$course] = c3Course();
    $stranger = userWithRole(Role::Student);

    Sanctum::actingAs($stranger);

    $this->getJson("/api/v1/courses/{$course->slug}/announcements")
        ->assertForbidden();
});

it('POST /bulk-email لا يُنشئ صفّاً في قاعدة البيانات', function () {
    Notification::fake();

    [$course, $instructor] = c3Course();
    $learner = userWithRole(Role::Student);
    c3Enroll($learner, $course);

    Sanctum::actingAs($instructor);

    $this->postJson("/api/v1/courses/{$course->slug}/bulk-email", [
        'subject' => 'بريد جماعي',
        'body' => 'هذا النصّ لا يُخزَّن.',
    ])->assertStatus(202);

    // لا جدول مخصّص للبريد الجماعي
    $this->assertDatabaseCount('course_announcements', 0);
});

it('استجابة POST /announcements تحمل بنية البيانات المتعاقدة', function () {
    Notification::fake();

    [$course, $instructor] = c3Course();

    Sanctum::actingAs($instructor);

    $response = $this->postJson("/api/v1/courses/{$course->slug}/announcements", [
        'title' => 'موعد الاختبار النهائي',
        'body' => 'سيُعقد الاختبار يوم الأحد…',
    ])->assertCreated();

    $response->assertJsonStructure([
        'data' => ['id', 'course_id', 'title', 'body', 'author' => ['id', 'name'], 'created_at'],
        'recipients_queued',
    ]);
    expect($response->json('data.title'))->toBe('موعد الاختبار النهائي');
    expect($response->json('data.course_id'))->toBe($course->id);
});

it('استجابة POST /bulk-email تحمل recipients_queued وaudience فقط (202)', function () {
    Notification::fake();

    [$course, $instructor] = c3Course();
    $learner = userWithRole(Role::Student);
    c3Enroll($learner, $course);

    Sanctum::actingAs($instructor);

    $response = $this->postJson("/api/v1/courses/{$course->slug}/bulk-email", [
        'subject' => 'مستجدات',
        'body' => 'نصّ',
        'audience' => 'all_active',
    ])->assertStatus(202);

    $response->assertJsonStructure(['recipients_queued', 'audience']);
    expect($response->json('audience'))->toBe('all_active');
    expect($response->json('recipients_queued'))->toBe(1);
});

// ═══════════════════════════════════════════════════════════════════════════════
// § 6 — حصر المستلمين بالنشطين فقط
// ═══════════════════════════════════════════════════════════════════════════════

it('ملتحق Expired لا يُرسَل له إعلان', function () {
    [$course, $instructor] = c3Course();
    $expiredLearner = userWithRole(Role::Student);
    c3Enroll($expiredLearner, $course, EnrollmentStatus::Expired);

    Notification::fake();

    Sanctum::actingAs($instructor);

    $response = $this->postJson("/api/v1/courses/{$course->slug}/announcements", [
        'title' => 'إعلان',
        'body' => 'نصّ',
    ])->assertCreated();

    expect($response->json('recipients_queued'))->toBe(0);
    Notification::assertNotSentTo($expiredLearner, CourseAnnouncementNotification::class);
});

it('ملتحق Refunded لا يُرسَل له بريد جماعي', function () {
    [$course, $instructor] = c3Course();
    $refunded = userWithRole(Role::Student);
    c3Enroll($refunded, $course, EnrollmentStatus::Refunded);

    Notification::fake();

    Sanctum::actingAs($instructor);

    $response = $this->postJson("/api/v1/courses/{$course->slug}/bulk-email", [
        'subject' => 'موضوع',
        'body' => 'نصّ',
    ])->assertStatus(202);

    expect($response->json('recipients_queued'))->toBe(0);
    Notification::assertNotSentTo($refunded, CourseBulkEmailNotification::class);
});

it('ملتحق منتهي الوصول (access_expires_at في الماضي) لا يُرسَل له', function () {
    [$course, $instructor] = c3Course();
    $expiredAccess = userWithRole(Role::Student);
    c3Enroll($expiredAccess, $course);

    // نُعيّن access_expires_at في الماضي
    Enrollment::query()
        ->where('user_id', $expiredAccess->id)
        ->where('course_id', $course->id)
        ->update(['access_expires_at' => now()->subDay()]);

    Notification::fake();

    Sanctum::actingAs($instructor);

    $response = $this->postJson("/api/v1/courses/{$course->slug}/announcements", [
        'title' => 'إعلان',
        'body' => 'نصّ',
    ])->assertCreated();

    expect($response->json('recipients_queued'))->toBe(0);
    Notification::assertNotSentTo($expiredAccess, CourseAnnouncementNotification::class);
});

it('ملتحق Completed مع وصول غير منتهٍ يستقبل الإعلانات', function () {
    [$course, $instructor] = c3Course();
    $completed = userWithRole(Role::Student);
    c3Enroll($completed, $course, EnrollmentStatus::Completed);

    Notification::fake();

    Sanctum::actingAs($instructor);

    $response = $this->postJson("/api/v1/courses/{$course->slug}/announcements", [
        'title' => 'إعلان للمتمّم',
        'body' => 'نصّ',
    ])->assertCreated();

    expect($response->json('recipients_queued'))->toBe(1);
    Notification::assertSentTo($completed, CourseAnnouncementNotification::class);
});

// ═══════════════════════════════════════════════════════════════════════════════
// § 7 — التحقّق من المُدخلات: 422
// ═══════════════════════════════════════════════════════════════════════════════

it('title مفقود في إعلان يُعيد 422', function () {
    [$course, $instructor] = c3Course();
    Sanctum::actingAs($instructor);

    $this->postJson("/api/v1/courses/{$course->slug}/announcements", [
        'body' => 'نصّ',
    ])->assertUnprocessable();
});

it('body مفقود في إعلان يُعيد 422', function () {
    [$course, $instructor] = c3Course();
    Sanctum::actingAs($instructor);

    $this->postJson("/api/v1/courses/{$course->slug}/announcements", [
        'title' => 'عنوان',
    ])->assertUnprocessable();
});

it('body يتجاوز 5000 حرف في إعلان يُعيد 422', function () {
    [$course, $instructor] = c3Course();
    Sanctum::actingAs($instructor);

    $this->postJson("/api/v1/courses/{$course->slug}/announcements", [
        'title' => 'عنوان',
        'body' => str_repeat('أ', 5001),
    ])->assertUnprocessable();
});

it('subject مفقود في بريد جماعي يُعيد 422', function () {
    [$course, $instructor] = c3Course();
    Sanctum::actingAs($instructor);

    $this->postJson("/api/v1/courses/{$course->slug}/bulk-email", [
        'body' => 'نصّ',
    ])->assertUnprocessable();
});

it('body مفقود في بريد جماعي يُعيد 422', function () {
    [$course, $instructor] = c3Course();
    Sanctum::actingAs($instructor);

    $this->postJson("/api/v1/courses/{$course->slug}/bulk-email", [
        'subject' => 'موضوع',
    ])->assertUnprocessable();
});

it('body يتجاوز 5000 حرف في بريد جماعي يُعيد 422', function () {
    [$course, $instructor] = c3Course();
    Sanctum::actingAs($instructor);

    $this->postJson("/api/v1/courses/{$course->slug}/bulk-email", [
        'subject' => 'موضوع',
        'body' => str_repeat('ب', 5001),
    ])->assertUnprocessable();
});

it('audience بقيمة غير مدعومة يُعيد 422', function () {
    [$course, $instructor] = c3Course();
    Sanctum::actingAs($instructor);

    $this->postJson("/api/v1/courses/{$course->slug}/bulk-email", [
        'subject' => 'موضوع',
        'body' => 'نصّ',
        'audience' => 'premium_only',
    ])->assertUnprocessable();
});

it('title يتجاوز 255 حرف في إعلان يُعيد 422', function () {
    [$course, $instructor] = c3Course();
    Sanctum::actingAs($instructor);

    $this->postJson("/api/v1/courses/{$course->slug}/announcements", [
        'title' => str_repeat('أ', 256),
        'body' => 'نصّ',
    ])->assertUnprocessable();
});

// ═══════════════════════════════════════════════════════════════════════════════
// § 8 — عزل المستلمين: كل إشعار لمستخدم واحد (لا BCC)
// ═══════════════════════════════════════════════════════════════════════════════

it('كل إشعار موجَّه لمستخدم واحد فقط (لا BCC — عزل المستلمين)', function () {
    [$course, $instructor] = c3Course();
    $learner1 = userWithRole(Role::Student);
    $learner2 = userWithRole(Role::Student);
    $learner3 = userWithRole(Role::Student);

    c3Enroll($learner1, $course);
    c3Enroll($learner2, $course);
    c3Enroll($learner3, $course);

    Notification::fake();

    Sanctum::actingAs($instructor);

    $this->postJson("/api/v1/courses/{$course->slug}/bulk-email", [
        'subject' => 'موضوع',
        'body' => 'نصّ',
    ])->assertStatus(202);

    // كل مستخدم يستقبل إشعاره المنفصل (Notification::fake يتحقق من المرسَل إليه)
    Notification::assertSentTo($learner1, CourseBulkEmailNotification::class);
    Notification::assertSentTo($learner2, CourseBulkEmailNotification::class);
    Notification::assertSentTo($learner3, CourseBulkEmailNotification::class);

    // إجمالي = 3 مهام منفصلة (لكل مستخدم واحدة — لا BCC مشترك)
    Notification::assertCount(3);
});

// ═══════════════════════════════════════════════════════════════════════════════
// § 9 — عزل المقررات
// ═══════════════════════════════════════════════════════════════════════════════

it('لا يُرسَل لملتحقي مقرر آخر عند نشر إعلان', function () {
    [$course, $instructor] = c3Course();
    [$otherCourse] = c3Course();

    $myLearner = userWithRole(Role::Student);
    $otherLearner = userWithRole(Role::Student);

    c3Enroll($myLearner, $course);
    c3Enroll($otherLearner, $otherCourse);

    Notification::fake();

    Sanctum::actingAs($instructor);

    $response = $this->postJson("/api/v1/courses/{$course->slug}/announcements", [
        'title' => 'إعلان مقرري',
        'body' => 'نصّ',
    ])->assertCreated();

    expect($response->json('recipients_queued'))->toBe(1);
    Notification::assertSentTo($myLearner, CourseAnnouncementNotification::class);
    Notification::assertNotSentTo($otherLearner, CourseAnnouncementNotification::class);
});

// ═══════════════════════════════════════════════════════════════════════════════
// § 10 — قنوات الإشعارات (candidateChannels)
// ═══════════════════════════════════════════════════════════════════════════════

it('CourseAnnouncementNotification يرشّح فقط قناتَي database وmail', function () {
    $notification = new CourseAnnouncementNotification(
        courseTitle: 'دورة اختبار',
        announcementId: 1,
        title: 'عنوان',
        body: 'نصّ',
    );

    // candidateChannels خاص — نختبره عبر via() مع مستخدم مُفعَّل كل قنواته
    $user = User::factory()->create();

    $via = $notification->via($user);

    // يجب أن يكون database وmail فقط (لا SMS/push/whatsapp)
    expect($via)->toContain('database');
    expect($via)->toContain('mail');
    expect($via)->not->toContain('sms');
    expect($via)->not->toContain('push');
    expect($via)->not->toContain('whatsapp');
    expect($via)->toHaveCount(2);
});

it('CourseBulkEmailNotification يرشّح قناة mail فقط', function () {
    $notification = new CourseBulkEmailNotification(
        courseTitle: 'دورة اختبار',
        subject: 'موضوع',
        body: 'نصّ',
    );

    $user = User::factory()->create();

    $via = $notification->via($user);

    expect($via)->toContain('mail');
    expect($via)->not->toContain('database');
    expect($via)->not->toContain('sms');
    expect($via)->toHaveCount(1);
});

// ═══════════════════════════════════════════════════════════════════════════════
// § 11 — مقرر غير موجود يُعيد 404
// ═══════════════════════════════════════════════════════════════════════════════

it('مقرر غير موجود يُعيد 404 على نقاط C3', function () {
    $user = userWithRole(Role::Instructor);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/courses/slug-غير-موجود/announcements', [
        'title' => 'إعلان',
        'body' => 'نصّ',
    ])->assertNotFound();

    $this->postJson('/api/v1/courses/slug-غير-موجود/bulk-email', [
        'subject' => 'موضوع',
        'body' => 'نصّ',
    ])->assertNotFound();

    $this->getJson('/api/v1/courses/slug-غير-موجود/announcements')
        ->assertNotFound();
});
