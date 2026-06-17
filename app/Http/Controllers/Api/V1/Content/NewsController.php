<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Content;

use App\Contexts\Content\Infrastructure\Persistence\NewsPost;
use App\Contexts\Identity\Domain\Permission;
use App\Contexts\Shared\Application\SlugGenerator;
use App\Http\Controllers\Controller;
use App\Http\Requests\Content\StoreNewsPostRequest;
use App\Http\Requests\Content\UpdateNewsPostRequest;
use App\Http\Resources\NewsPostResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Platform news: the public site lists published posts; staff holding the
 * content-management permission author and publish them.
 */
final class NewsController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min((int) $request->integer('per_page', 9), 24);

        $query = NewsPost::query()->with('author');

        // Only staff may opt into seeing drafts (powers the admin panel).
        $includeDrafts = $request->boolean('include_drafts')
            && ($request->user('sanctum')?->can(Permission::ManageContent->value) ?? false);

        if (! $includeDrafts) {
            $query->published();
        }

        return NewsPostResource::collection(
            $query->orderByDesc('published_at')->orderByDesc('id')->paginate($perPage),
        );
    }

    public function show(Request $request, NewsPost $news): NewsPostResource
    {
        $canManage = $request->user('sanctum')?->can(Permission::ManageContent->value) ?? false;

        abort_unless($news->isPublished() || $canManage, 404);

        return new NewsPostResource($news->load('author'));
    }

    public function store(StoreNewsPostRequest $request, SlugGenerator $slugs): JsonResponse
    {
        $post = NewsPost::query()->create([
            'author_id' => $request->user()->getKey(),
            'title' => $request->validated('title'),
            'slug' => $slugs->forTitle($request->validated('title'), 'news_posts'),
            'excerpt' => $request->validated('excerpt'),
            'body' => $request->validated('body'),
            'published_at' => $request->boolean('published') ? now() : null,
        ]);

        return (new NewsPostResource($post))->response()->setStatusCode(201);
    }

    public function update(UpdateNewsPostRequest $request, NewsPost $news): NewsPostResource
    {
        $data = $request->safe()->only(['title', 'excerpt', 'body']);

        if ($request->has('published')) {
            $data['published_at'] = $request->boolean('published')
                ? ($news->published_at ?? now())
                : null;
        }

        $news->update($data);

        return new NewsPostResource($news->fresh('author'));
    }

    public function destroy(Request $request, NewsPost $news): JsonResponse
    {
        abort_unless($request->user()?->can(Permission::ManageContent->value) ?? false, 403);

        $news->delete();

        return response()->json(status: 204);
    }
}
