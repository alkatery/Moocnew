<?php

declare(strict_types=1);

namespace App\Contexts\Communication\Application;

use App\Contexts\Communication\Infrastructure\Persistence\ForumBan;
use App\Contexts\Communication\Infrastructure\Persistence\ForumPost;
use App\Contexts\Communication\Infrastructure\Persistence\ForumReport;
use App\Contexts\Communication\Infrastructure\Persistence\ForumSubscription;
use App\Contexts\Communication\Infrastructure\Persistence\ForumThread;
use App\Contexts\Notification\Infrastructure\Notifications\ForumReplyNotification;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Course discussion forums with moderation (PRD §5.ز): threaded posts,
 * abuse reports, hiding, per-course bans, plus a light anti-spam guard.
 */
final class ForumService
{
    public function isBanned(int $courseId, int $userId): bool
    {
        return ForumBan::query()->where('course_id', $courseId)->where('user_id', $userId)->exists();
    }

    public function createThread(int $courseId, User $author, string $title, string $body): ForumThread
    {
        $this->assertNotBanned($courseId, $author->getKey());

        return DB::transaction(function () use ($courseId, $author, $title, $body): ForumThread {
            $thread = ForumThread::query()->create([
                'course_id' => $courseId,
                'user_id' => $author->getKey(),
                'title' => $title,
            ]);

            $thread->posts()->create(['user_id' => $author->getKey(), 'body' => $body]);

            return $thread;
        });
    }

    public function reply(ForumThread $thread, User $author, string $body, ?int $parentId = null): ForumPost
    {
        $this->assertNotBanned($thread->course_id, $author->getKey());

        if ($thread->locked) {
            throw ValidationException::withMessages(['thread' => ['النقاش مغلق.']]);
        }

        $this->assertNotDuplicate($thread->getKey(), $author->getKey(), $body);

        $post = $thread->posts()->create([
            'user_id' => $author->getKey(),
            'body' => $body,
            'parent_id' => $parentId,
        ]);

        // D2 — إشعار متابعي الموضوع عدا كاتب الرد (مُطابور، opt-out عبر via())
        $this->notifySubscribers($thread, $author);

        return $post;
    }

    /**
     * D2 — تمييز رد كإجابة مقبولة أو إلغاء تمييزه (toggle).
     *
     * الرد يجب أن يكون في الموضوع وغير مخفيّ — يُتحقّق في المتحكّم قبل الاستدعاء.
     * يُرجع accepted_post_id الجديد (أو null بعد الإلغاء).
     */
    public function accept(ForumThread $thread, ForumPost $post): ?int
    {
        // toggle: نفس الرد المميَّز → إلغاء؛ غيره → تبديل الإشارة
        $newValue = $thread->accepted_post_id === $post->getKey()
            ? null
            : $post->getKey();

        $thread->update(['accepted_post_id' => $newValue]);

        return $newValue;
    }

    /**
     * D2 — متابعة موضوع (idempotent: اشتراك قائم لا يُكرَّر).
     */
    public function subscribe(ForumThread $thread, User $user): void
    {
        ForumSubscription::firstOrCreate([
            'thread_id' => $thread->getKey(),
            'user_id' => $user->getKey(),
        ]);
    }

    /**
     * D2 — إلغاء متابعة موضوع (idempotent: غير مشترك أصلاً → لا خطأ).
     */
    public function unsubscribe(ForumThread $thread, User $user): void
    {
        ForumSubscription::query()
            ->where('thread_id', $thread->getKey())
            ->where('user_id', $user->getKey())
            ->delete();
    }

    /**
     * D2 — بثّ ForumReplyNotification لمتابعي الموضوع عدا كاتب الرد.
     * chunkById لحماية الذاكرة عند كثرة المتابعين؛ كل إرسال مهمة مُطابورة.
     */
    private function notifySubscribers(ForumThread $thread, User $author): void
    {
        // تحميل بيانات المقرر مرة واحدة (قد يكون محمَّلاً بالفعل)
        $course = $thread->course;
        $courseTitle = $course->title ?? '';

        ForumSubscription::query()
            ->where('thread_id', $thread->getKey())
            ->where('user_id', '!=', $author->getKey())   // تجنّب إشعار الذات
            ->with('user')
            ->chunkById(100, function ($subscriptions) use ($thread, $courseTitle, $author): void {
                foreach ($subscriptions as $subscription) {
                    $subscription->user->notify(
                        new ForumReplyNotification(
                            $thread->getKey(),
                            $thread->title,
                            $courseTitle,
                            $author->name,
                        )
                    );
                }
            });
    }

    public function hide(ForumPost $post, User $moderator): ForumPost
    {
        $post->update(['hidden_at' => Date::now(), 'hidden_by' => $moderator->getKey()]);

        return $post;
    }

    public function report(ForumPost $post, User $reporter, string $reason): ForumReport
    {
        return ForumReport::query()->firstOrCreate(
            ['post_id' => $post->getKey(), 'reporter_id' => $reporter->getKey()],
            ['reason' => $reason],
        );
    }

    public function ban(int $courseId, int $userId, User $moderator, ?string $reason = null): ForumBan
    {
        return ForumBan::query()->updateOrCreate(
            ['course_id' => $courseId, 'user_id' => $userId],
            ['banned_by' => $moderator->getKey(), 'reason' => $reason],
        );
    }

    private function assertNotBanned(int $courseId, int $userId): void
    {
        if ($this->isBanned($courseId, $userId)) {
            throw ValidationException::withMessages(['forum' => ['أنت محظور من المشاركة في هذه الدورة.']]);
        }
    }

    private function assertNotDuplicate(int $threadId, int $userId, string $body): void
    {
        $recent = ForumPost::query()
            ->where('thread_id', $threadId)
            ->where('user_id', $userId)
            ->where('created_at', '>=', Date::now()->subMinute())
            ->latest('id')
            ->first();

        if ($recent !== null && $recent->body === $body) {
            throw ValidationException::withMessages(['body' => ['يبدو أنك كرّرت المشاركة نفسها.']]);
        }
    }
}
