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

        $isModerator = $request->user()->can(Permission::Moderate->value);

        $posts = $thread->posts()
            ->when(! $isModerator, fn ($q) => $q->whereNull('hidden_at'))
            ->orderBy('created_at')
            ->get();

        return response()->json(['data' => ['thread' => $thread, 'posts' => $posts]]);
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
