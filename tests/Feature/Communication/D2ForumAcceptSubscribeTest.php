<?php

declare(strict_types=1);

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Communication\Infrastructure\Persistence\ForumPost;
use App\Contexts\Communication\Infrastructure\Persistence\ForumSubscription;
use App\Contexts\Enrollment\Application\EnrollmentService;
use App\Contexts\Identity\Domain\Role;
use App\Contexts\Notification\Application\NotificationPreferences;
use App\Contexts\Notification\Domain\NotificationChannel;
use App\Contexts\Notification\Domain\NotificationType;
use App\Contexts\Notification\Infrastructure\Notifications\ForumReplyNotification;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

// ─── إعداد مشترك ──────────────────────────────────────────────────────────────

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

// ─── دوال مساعدة محلّية ───────────────────────────────────────────────────────

/**
 * ينشئ مقرراً مع مدرّسه (طاقم المقرر).
 *
 * @return array{Course, User}
 */
function d2Course(): array
{
    $instructor = userWithRole(Role::Instructor);
    $course = Course::factory()
        ->for($instructor, 'instructor')
        ->published()
        ->create(['pricing_type' => 'free', 'price_minor' => 0]);

    return [$course, $instructor];
}

/**
 * يُسجّل متعلّماً في المقرر (الحالة Active) ويُعيده.
 */
function d2Enroll(Course $course): User
{
    $learner = userWithRole(Role::Student);
    app(EnrollmentService::class)->enroll($learner, $course);

    return $learner;
}

/**
 * ينشئ موضوعاً بمنشور أوّلي ويُعيد [thread_id, post_id_first].
 * الناشر: $author (يجب أن يكون مشاركاً في المقرر مسبقاً).
 *
 * @return array{int, int}
 */
function d2CreateThread(Course $course, User $author): array
{
    Sanctum::actingAs($author);
    $threadId = test()->postJson(
        "/api/v1/community/courses/{$course->slug}/threads",
        ['title' => 'سؤال اختبار D2', 'body' => 'نصّ السؤال']
    )->assertCreated()->json('data.id');

    $firstPostId = ForumPost::query()->where('thread_id', $threadId)->first()->id;

    return [$threadId, $firstPostId];
}

/**
 * ينشئ رداً في الموضوع ويُعيد post_id.
 * المستخدم الحالي (actingAs) هو الكاتب.
 */
function d2Reply(int $threadId, string $body = 'رد مفيد'): int
{
    return test()->postJson(
        "/api/v1/community/threads/{$threadId}/posts",
        ['body' => $body]
    )->assertCreated()->json('data.id');
}

// ═══════════════════════════════════════════════════════════════════════════════
// § 1 — تمييز الإجابة
// ═══════════════════════════════════════════════════════════════════════════════

it('[D2-1] صاحب الموضوع يميّز رداً → 200 وaccepted_post_id يُعيَّن', function () {
    [$course] = d2Course();
    $owner = d2Enroll($course);

    [$threadId] = d2CreateThread($course, $owner);

    // ردّ من متعلّم آخر
    $replier = d2Enroll($course);
    Sanctum::actingAs($replier);
    $replyId = d2Reply($threadId, 'إجابة المتعلّم');

    // صاحب الموضوع يميّز الرد
    Sanctum::actingAs($owner);
    $response = $this->postJson("/api/v1/community/threads/{$threadId}/accept", ['post_id' => $replyId]);

    $response->assertOk()
        ->assertJsonPath('data.thread_id', $threadId)
        ->assertJsonPath('data.accepted_post_id', $replyId);

    $this->assertDatabaseHas('forum_threads', ['id' => $threadId, 'accepted_post_id' => $replyId]);
});

it('[D2-2] طاقم المقرر (المدرّس) يميّز رداً على موضوع متعلّم آخر → 200', function () {
    [$course, $instructor] = d2Course();
    $learner = d2Enroll($course);

    [$threadId] = d2CreateThread($course, $learner);

    // رد من متعلّم آخر
    $replier = d2Enroll($course);
    Sanctum::actingAs($replier);
    $replyId = d2Reply($threadId);

    // المدرّس (طاقم) يميّز
    Sanctum::actingAs($instructor);
    $this->postJson("/api/v1/community/threads/{$threadId}/accept", ['post_id' => $replyId])
        ->assertOk()
        ->assertJsonPath('data.accepted_post_id', $replyId);
});

it('[D2-3] مشارك عادي ليس صاحب الموضوع ولا طاقماً → 403', function () {
    [$course] = d2Course();
    $owner = d2Enroll($course);
    $stranger = d2Enroll($course);   // مشارك عادي ≠ صاحب الموضوع

    [$threadId] = d2CreateThread($course, $owner);

    Sanctum::actingAs($stranger);
    $replyId = d2Reply($threadId, 'رد الغريب');

    // الغريب يحاول التمييز → 403
    $this->postJson("/api/v1/community/threads/{$threadId}/accept", ['post_id' => $replyId])
        ->assertForbidden();

    $this->assertDatabaseHas('forum_threads', ['id' => $threadId, 'accepted_post_id' => null]);
});

it('[D2-4] إجابة واحدة: تمييز (أ) ثم (ب) → accepted_post_id == postB فقط (يلغي السابق ضمنياً)', function () {
    [$course] = d2Course();
    $owner = d2Enroll($course);
    $replier = d2Enroll($course);

    [$threadId] = d2CreateThread($course, $owner);

    Sanctum::actingAs($replier);
    $postA = d2Reply($threadId, 'الإجابة الأولى');
    $postB = d2Reply($threadId, 'الإجابة الأفضل');

    Sanctum::actingAs($owner);
    $this->postJson("/api/v1/community/threads/{$threadId}/accept", ['post_id' => $postA])
        ->assertOk()->assertJsonPath('data.accepted_post_id', $postA);

    // تبديل إلى (ب)
    $this->postJson("/api/v1/community/threads/{$threadId}/accept", ['post_id' => $postB])
        ->assertOk()->assertJsonPath('data.accepted_post_id', $postB);

    // لا إجابتان — قيمة واحدة فقط على الموضوع
    $this->assertDatabaseHas('forum_threads', ['id' => $threadId, 'accepted_post_id' => $postB]);
    $this->assertDatabaseMissing('forum_threads', ['id' => $threadId, 'accepted_post_id' => $postA]);
});

it('[D2-5] toggle: تمييز نفس الرد مرّتين → accepted_post_id يصبح null', function () {
    [$course] = d2Course();
    $owner = d2Enroll($course);
    $replier = d2Enroll($course);

    [$threadId] = d2CreateThread($course, $owner);

    Sanctum::actingAs($replier);
    $replyId = d2Reply($threadId);

    Sanctum::actingAs($owner);
    // تمييز أوّل
    $this->postJson("/api/v1/community/threads/{$threadId}/accept", ['post_id' => $replyId])
        ->assertOk()->assertJsonPath('data.accepted_post_id', $replyId);

    // تمييز ثانٍ على نفس الرد → إلغاء
    $this->postJson("/api/v1/community/threads/{$threadId}/accept", ['post_id' => $replyId])
        ->assertOk()->assertJsonPath('data.accepted_post_id', null);

    $this->assertDatabaseHas('forum_threads', ['id' => $threadId, 'accepted_post_id' => null]);
});

it('[D2-6] post_id من موضوع آخر → 422 ولا تغيير', function () {
    [$course] = d2Course();
    $owner = d2Enroll($course);

    [$threadId] = d2CreateThread($course, $owner);

    // موضوع آخر في نفس المقرر
    Sanctum::actingAs($owner);
    $otherThreadId = test()->postJson(
        "/api/v1/community/courses/{$course->slug}/threads",
        ['title' => 'موضوع آخر', 'body' => 'نصّ']
    )->json('data.id');
    $foreignPostId = ForumPost::query()->where('thread_id', $otherThreadId)->first()->id;

    // محاولة تمييز رد من موضوع آخر
    $this->postJson("/api/v1/community/threads/{$threadId}/accept", ['post_id' => $foreignPostId])
        ->assertUnprocessable();

    $this->assertDatabaseHas('forum_threads', ['id' => $threadId, 'accepted_post_id' => null]);
});

it('[D2-7] تمييز رد مخفيّ → 422', function () {
    [$course, $instructor] = d2Course();
    $owner = d2Enroll($course);
    $replier = d2Enroll($course);

    [$threadId] = d2CreateThread($course, $owner);

    Sanctum::actingAs($replier);
    $replyId = d2Reply($threadId, 'رد سيُخفى');

    // المشرف يخفي الرد
    $superAdmin = userWithRole(Role::Supervisor);
    app(EnrollmentService::class)->enroll($superAdmin, $course);
    Sanctum::actingAs($superAdmin);
    $this->postJson("/api/v1/community/posts/{$replyId}/hide")->assertOk();

    // صاحب الموضوع يحاول تمييز الرد المخفيّ
    Sanctum::actingAs($owner);
    $this->postJson("/api/v1/community/threads/{$threadId}/accept", ['post_id' => $replyId])
        ->assertUnprocessable();

    $this->assertDatabaseHas('forum_threads', ['id' => $threadId, 'accepted_post_id' => null]);
});

it('[D2-8] حذف الرد المميَّز يُفرّغ accepted_post_id (nullOnDelete) — الموضوع يبقى', function () {
    // نختبر آلية قاعدة البيانات مباشرة (nullOnDelete من الهجرة)
    [$course] = d2Course();
    $owner = d2Enroll($course);
    $replier = d2Enroll($course);

    [$threadId] = d2CreateThread($course, $owner);

    Sanctum::actingAs($replier);
    $replyId = d2Reply($threadId);

    Sanctum::actingAs($owner);
    $this->postJson("/api/v1/community/threads/{$threadId}/accept", ['post_id' => $replyId])
        ->assertOk();

    // حذف الرد مباشرة من قاعدة البيانات (محاكاة حذف إشرافي/cascade)
    ForumPost::findOrFail($replyId)->delete();

    // الموضوع لا يزال موجوداً وaccepted_post_id أصبح null
    $this->assertDatabaseHas('forum_threads', ['id' => $threadId, 'accepted_post_id' => null]);
    $this->assertDatabaseHas('forum_threads', ['id' => $threadId]);
});

// ═══════════════════════════════════════════════════════════════════════════════
// § 2 — show: حقول إضافية للواجهة
// ═══════════════════════════════════════════════════════════════════════════════

it('[D2-show] GET threads/{thread} يُرجع thread.user_id وaccepted_post_id وsubscribed وcan_accept', function () {
    [$course] = d2Course();
    $owner = d2Enroll($course);

    [$threadId] = d2CreateThread($course, $owner);

    Sanctum::actingAs($owner);
    $response = $this->getJson("/api/v1/community/threads/{$threadId}")
        ->assertOk();

    // بنية الاستجابة الجديدة
    $response->assertJsonStructure([
        'data' => [
            'thread' => ['id', 'user_id', 'accepted_post_id'],
            'posts',
            'subscribed',
            'can_accept',
        ],
    ]);

    // صاحب الموضوع: can_accept = true، subscribed = false (لم يشترك بعد)
    expect($response->json('data.thread.user_id'))->toBe($owner->id);
    expect($response->json('data.thread.accepted_post_id'))->toBeNull();
    expect($response->json('data.can_accept'))->toBeTrue();
    expect($response->json('data.subscribed'))->toBeFalse();
});

it('[D2-show] can_accept = false لمشارك عادي ليس صاحب الموضوع', function () {
    [$course] = d2Course();
    $owner = d2Enroll($course);
    $other = d2Enroll($course);

    [$threadId] = d2CreateThread($course, $owner);

    Sanctum::actingAs($other);
    $response = $this->getJson("/api/v1/community/threads/{$threadId}")->assertOk();

    expect($response->json('data.can_accept'))->toBeFalse();
});

it('[D2-show] subscribed = true بعد اشتراك المستخدم', function () {
    [$course] = d2Course();
    $learner = d2Enroll($course);

    [$threadId] = d2CreateThread($course, $learner);

    // اشتراك
    Sanctum::actingAs($learner);
    $this->postJson("/api/v1/community/threads/{$threadId}/subscribe")->assertOk();

    // الآن show يُعيد subscribed=true
    $response = $this->getJson("/api/v1/community/threads/{$threadId}")->assertOk();
    expect($response->json('data.subscribed'))->toBeTrue();
});

// ═══════════════════════════════════════════════════════════════════════════════
// § 3 — متابعة الموضوع
// ═══════════════════════════════════════════════════════════════════════════════

it('[D2-9] مشارك نشط يتابع موضوعاً → 200 subscribed:true + صفّ في forum_subscriptions', function () {
    [$course] = d2Course();
    $learner = d2Enroll($course);

    [$threadId] = d2CreateThread($course, $learner);

    $subscriber = d2Enroll($course);
    Sanctum::actingAs($subscriber);

    $this->postJson("/api/v1/community/threads/{$threadId}/subscribe")
        ->assertOk()
        ->assertJsonPath('data.thread_id', $threadId)
        ->assertJsonPath('data.subscribed', true);

    $this->assertDatabaseHas('forum_subscriptions', [
        'thread_id' => $threadId,
        'user_id' => $subscriber->id,
    ]);
});

it('[D2-10] POST subscribe مرّتين → idempotent: 200 والصفّ واحد فقط', function () {
    [$course] = d2Course();
    $owner = d2Enroll($course);
    $subscriber = d2Enroll($course);

    [$threadId] = d2CreateThread($course, $owner);

    Sanctum::actingAs($subscriber);
    $this->postJson("/api/v1/community/threads/{$threadId}/subscribe")->assertOk();
    $this->postJson("/api/v1/community/threads/{$threadId}/subscribe")->assertOk();

    // صفّ واحد فقط (قيد unique)
    expect(
        ForumSubscription::query()
            ->where('thread_id', $threadId)
            ->where('user_id', $subscriber->id)
            ->count()
    )->toBe(1);
});

it('[D2-11] DELETE subscribe → 200 subscribed:false + الصفّ يختفي؛ إلغاء غير مشترك → 200 بلا خطأ', function () {
    [$course] = d2Course();
    $owner = d2Enroll($course);
    $subscriber = d2Enroll($course);

    [$threadId] = d2CreateThread($course, $owner);

    Sanctum::actingAs($subscriber);

    // اشترك أوّلاً
    $this->postJson("/api/v1/community/threads/{$threadId}/subscribe")->assertOk();
    $this->assertDatabaseHas('forum_subscriptions', ['thread_id' => $threadId, 'user_id' => $subscriber->id]);

    // إلغاء
    $this->deleteJson("/api/v1/community/threads/{$threadId}/subscribe")
        ->assertOk()
        ->assertJsonPath('data.subscribed', false);

    $this->assertDatabaseMissing('forum_subscriptions', ['thread_id' => $threadId, 'user_id' => $subscriber->id]);

    // إلغاء مرّة ثانية لغير مشترك → 200 بلا خطأ (idempotent)
    $this->deleteJson("/api/v1/community/threads/{$threadId}/subscribe")
        ->assertOk()
        ->assertJsonPath('data.subscribed', false);
});

it('[D2-12] مستخدم غير مشارك في المقرر → POST subscribe → 403', function () {
    [$course] = d2Course();
    $owner = d2Enroll($course);
    [$threadId] = d2CreateThread($course, $owner);

    $outsider = userWithRole(Role::Student);  // لم يُسجَّل في المقرر
    Sanctum::actingAs($outsider);

    $this->postJson("/api/v1/community/threads/{$threadId}/subscribe")
        ->assertForbidden();
});

// ═══════════════════════════════════════════════════════════════════════════════
// § 4 — الإشعار
// ═══════════════════════════════════════════════════════════════════════════════

it('[D2-إشعار] ForumReplyNotification يُطبّق ShouldQueue', function () {
    expect(ForumReplyNotification::class)->toImplement(ShouldQueue::class);
});

it('[D2-إشعار] ForumReplyNotification يرشّح قناتَي database وmail فقط', function () {
    $notification = new ForumReplyNotification(
        threadId: 1,
        threadTitle: 'سؤال',
        courseTitle: 'دورة PHP',
        replierName: 'أحمد',
    );
    $user = User::factory()->create();
    $via = $notification->via($user);

    expect($via)->toContain('database');
    expect($via)->toContain('mail');
    expect($via)->not->toContain('sms');
    expect($via)->toHaveCount(2);
});

it('[D2-13] المتابعون (عدا الكاتب) يُشعَرون عند رد جديد', function () {
    [$course] = d2Course();
    $owner = d2Enroll($course);
    $subscriber1 = d2Enroll($course);
    $subscriber2 = d2Enroll($course);
    $author = d2Enroll($course);       // كاتب الرد (لا يُشعَر)

    [$threadId] = d2CreateThread($course, $owner);

    // المتابعون يشتركون
    Sanctum::actingAs($subscriber1);
    $this->postJson("/api/v1/community/threads/{$threadId}/subscribe")->assertOk();
    Sanctum::actingAs($subscriber2);
    $this->postJson("/api/v1/community/threads/{$threadId}/subscribe")->assertOk();
    // الكاتب يشترك أيضاً (لكن لا يُشعَر بردّه)
    Sanctum::actingAs($author);
    $this->postJson("/api/v1/community/threads/{$threadId}/subscribe")->assertOk();

    Notification::fake();

    // الكاتب يضيف رداً
    Sanctum::actingAs($author);
    d2Reply($threadId, 'ردّي لكم');

    // المتابعان يُشعَران
    Notification::assertSentTo($subscriber1, ForumReplyNotification::class);
    Notification::assertSentTo($subscriber2, ForumReplyNotification::class);

    // كاتب الرد لا يُشعَر بردّه
    Notification::assertNotSentTo($author, ForumReplyNotification::class);
});

it('[D2-14] كاتب الرد المشترك لا يُشعَر بردّه', function () {
    [$course] = d2Course();
    $owner = d2Enroll($course);

    [$threadId] = d2CreateThread($course, $owner);

    // صاحب الموضوع يشترك في موضوعه
    Sanctum::actingAs($owner);
    $this->postJson("/api/v1/community/threads/{$threadId}/subscribe")->assertOk();

    Notification::fake();

    // صاحب الموضوع يردّ
    Sanctum::actingAs($owner);
    d2Reply($threadId, 'ردّي على موضوعي');

    Notification::assertNotSentTo($owner, ForumReplyNotification::class);
});

it('[D2-15] opt-out: متابع عطّل mail → لا يصله بريد؛ قناة database تبقى', function () {
    [$course] = d2Course();
    $owner = d2Enroll($course);
    $optedOut = d2Enroll($course);
    $replier = d2Enroll($course);

    [$threadId] = d2CreateThread($course, $owner);

    // المتابع يشترك ثم يُعطّل قناة mail
    Sanctum::actingAs($optedOut);
    $this->postJson("/api/v1/community/threads/{$threadId}/subscribe")->assertOk();

    app(NotificationPreferences::class)->set(
        $optedOut,
        NotificationType::ForumReply,
        NotificationChannel::Mail,
        false,
    );

    Notification::fake();

    // رد جديد
    Sanctum::actingAs($replier);
    d2Reply($threadId, 'رد يُشغّل الإشعار');

    // المتابع المُعطِّل: يصله database فقط (mail مُعطَّل)
    Notification::assertSentTo(
        $optedOut,
        ForumReplyNotification::class,
        fn ($notification, array $channels): bool => in_array('database', $channels, true)
            && ! in_array('mail', $channels, true)
    );
});

it('[D2-16] opt-out كامل: متابع عطّل database وmail → لا إشعار', function () {
    [$course] = d2Course();
    $owner = d2Enroll($course);
    $optedOut = d2Enroll($course);
    $replier = d2Enroll($course);

    [$threadId] = d2CreateThread($course, $owner);

    Sanctum::actingAs($optedOut);
    $this->postJson("/api/v1/community/threads/{$threadId}/subscribe")->assertOk();

    // تعطيل كلّ القنوات المرشَّحة
    app(NotificationPreferences::class)->set(
        $optedOut, NotificationType::ForumReply, NotificationChannel::Database, false,
    );
    app(NotificationPreferences::class)->set(
        $optedOut, NotificationType::ForumReply, NotificationChannel::Mail, false,
    );

    Notification::fake();

    Sanctum::actingAs($replier);
    d2Reply($threadId, 'رد آخر');

    // via() يُعيد [] → لا يُسجَّل في fake
    Notification::assertNotSentTo($optedOut, ForumReplyNotification::class);
});

it('[D2-17] لا متابعون → لا إشعار (بثّ آمن)', function () {
    [$course] = d2Course();
    $owner = d2Enroll($course);
    $replier = d2Enroll($course);

    [$threadId] = d2CreateThread($course, $owner);

    Notification::fake();

    // رد بلا أي مشترك
    Sanctum::actingAs($replier);
    d2Reply($threadId, 'رد على موضوع بلا متابعين');

    Notification::assertNothingSent();
});

// ═══════════════════════════════════════════════════════════════════════════════
// § 5 — عزل المقررات
// ═══════════════════════════════════════════════════════════════════════════════

it('[D2-عزل] مشارك مقرر آخر → POST subscribe → 403', function () {
    [$courseA] = d2Course();
    [$courseB] = d2Course();

    $ownerA = d2Enroll($courseA);
    [$threadId] = d2CreateThread($courseA, $ownerA);

    // مشارك في مقرر (ب) فقط — لا (أ)
    $learnerB = d2Enroll($courseB);

    Sanctum::actingAs($learnerB);
    $this->postJson("/api/v1/community/threads/{$threadId}/subscribe")
        ->assertForbidden();
});

it('[D2-عزل] مشارك مقرر آخر → POST accept → 403', function () {
    [$courseA] = d2Course();
    [$courseB, $instructorB] = d2Course();

    $ownerA = d2Enroll($courseA);
    [$threadId] = d2CreateThread($courseA, $ownerA);

    $replierA = d2Enroll($courseA);
    Sanctum::actingAs($replierA);
    $replyId = d2Reply($threadId);

    // مدرّس (ب) ليس طاقم (أ)
    Sanctum::actingAs($instructorB);
    $this->postJson("/api/v1/community/threads/{$threadId}/accept", ['post_id' => $replyId])
        ->assertForbidden();
});
