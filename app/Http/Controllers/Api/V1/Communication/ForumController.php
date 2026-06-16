<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Communication;

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Communication\Application\ForumService;
use App\Contexts\Communication\Infrastructure\Persistence\ForumPost;
use App\Contexts\Communication\Infrastructure\Persistence\ForumThread;
use App\Contexts\Enrollment\Application\CourseAccess;
use App\Contexts\Identity\Domain\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class ForumController extends Controller
{
    public function __construct(
        private readonly ForumService $forum,
        private readonly CourseAccess $access,
    ) {}

    public function index(Request $request, Course $course): JsonResponse
    {
        $this->authorizeParticipation($request, $course);

        $threads = ForumThread::query()
            ->where('course_id', $course->getKey())
            ->withCount('posts')
            ->latest()
            ->paginate(20);

        return response()->json(['data' => $threads]);
    }

    public function storeThread(Request $request, Course $course): JsonResponse
    {
        $this->authorizeParticipation($request, $course);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ]);

        $thread = $this->forum->createThread($course->getKey(), $request->user(), $data['title'], $data['body']);

        return response()->json(['data' => $thread->load('posts')], 201);
    }

    public function show(Request $request, ForumThread $thread): JsonResponse
    {
        $course = $thread->course;
        $this->authorizeParticipation($request, $course);

        $user = $request->user();
        $isModerator = $user->can(Permission::Moderate->value);

        $posts = $thread->posts()
            ->when(! $isModerator, fn ($q) => $q->whereNull('hidden_at'))
            ->orderBy('created_at')
            ->get();

        // D2 — حقول إضافية لتمكين أزرار الواجهة بلا استعلام إضافي
        $subscribed = $thread->subscriptions()->where('user_id', $user->getKey())->exists();
        $canAccept = $thread->user_id === $user->getKey()
            || $this->access->isStaffFor($user, $course);

        return response()->json(['data' => [
            'thread' => $thread,
            'posts' => $posts,
            'subscribed' => $subscribed,
            'can_accept' => $canAccept,
        ]]);
    }

    public function reply(Request $request, ForumThread $thread): JsonResponse
    {
        $this->authorizeParticipation($request, $thread->course);
        $data = $request->validate([
            'body' => ['required', 'string'],
            'parent_id' => ['nullable', 'integer', 'exists:forum_posts,id'],
        ]);

        $post = $this->forum->reply($thread, $request->user(), $data['body'], $data['parent_id'] ?? null);

        return response()->json(['data' => $post], 201);
    }

    /**
     * D2 — تمييز رد كإجابة مقبولة أو إلغاء التمييز (toggle).
     *
     * التخويل: صاحب الموضوع أو طاقم المقرر فقط.
     * POST /api/v1/community/threads/{thread}/accept  { post_id }
     */
    public function accept(Request $request, ForumThread $thread): JsonResponse
    {
        // تخويل: صاحب الموضوع أو طاقم المقرر
        abort_unless(
            $thread->user_id === $request->user()->getKey()
                || $this->access->isStaffFor($request->user(), $thread->course),
            403,
            'غير مصرَّح لك بتمييز الإجابة على هذا الموضوع.'
        );

        $data = $request->validate([
            'post_id' => ['required', 'integer', Rule::exists('forum_posts', 'id')],
        ]);

        $post = ForumPost::findOrFail($data['post_id']);

        // الرد يجب أن ينتمي لهذا الموضوع
        abort_unless(
            $post->thread_id === $thread->getKey(),
            422,
            'الرد لا ينتمي إلى هذا الموضوع.'
        );

        // لا يُميَّز رد مخفيّ إشرافياً
        abort_unless(
            $post->hidden_at === null,
            422,
            'لا يمكن تمييز رد محجوب.'
        );

        $acceptedPostId = $this->forum->accept($thread, $post);

        return response()->json(['data' => [
            'thread_id' => $thread->getKey(),
            'accepted_post_id' => $acceptedPostId,
        ]]);
    }

    /**
     * D2 — متابعة موضوع (idempotent).
     *
     * POST /api/v1/community/threads/{thread}/subscribe
     */
    public function subscribe(Request $request, ForumThread $thread): JsonResponse
    {
        $this->authorizeParticipation($request, $thread->course);

        $this->forum->subscribe($thread, $request->user());

        return response()->json(['data' => [
            'thread_id' => $thread->getKey(),
            'subscribed' => true,
        ]]);
    }

    /**
     * D2 — إلغاء متابعة موضوع (idempotent).
     *
     * DELETE /api/v1/community/threads/{thread}/subscribe
     */
    public function unsubscribe(Request $request, ForumThread $thread): JsonResponse
    {
        $this->authorizeParticipation($request, $thread->course);

        $this->forum->unsubscribe($thread, $request->user());

        return response()->json(['data' => [
            'thread_id' => $thread->getKey(),
            'subscribed' => false,
        ]]);
    }

    public function report(Request $request, ForumPost $post): JsonResponse
    {
        $this->authorizeParticipation($request, $post->thread->course);
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        $this->forum->report($post, $request->user(), $data['reason']);

        return response()->json(status: 204);
    }

    public function hide(Request $request, ForumPost $post): JsonResponse
    {
        abort_unless($request->user()->can(Permission::Moderate->value), 403);

        return response()->json(['data' => $this->forum->hide($post, $request->user())]);
    }

    public function ban(Request $request, Course $course): JsonResponse
    {
        abort_unless($request->user()->can(Permission::Moderate->value), 403);
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $this->forum->ban($course->getKey(), (int) $data['user_id'], $request->user(), $data['reason'] ?? null);

        return response()->json(status: 204);
    }

    private function authorizeParticipation(Request $request, Course $course): void
    {
        abort_unless($this->access->canParticipate($request->user(), $course), 403);
    }
}
