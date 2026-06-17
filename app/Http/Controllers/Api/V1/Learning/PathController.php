<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Learning;

use App\Contexts\Identity\Domain\Permission;
use App\Contexts\Learning\Application\PathProgressService;
use App\Contexts\Learning\Domain\PathEnrollmentStatus;
use App\Contexts\Learning\Infrastructure\Persistence\LearningPath;
use App\Contexts\Learning\Infrastructure\Persistence\LearningPathItem;
use App\Contexts\Learning\Infrastructure\Persistence\PathEnrollment;
use App\Contexts\Shared\Application\ImageUploader;
use App\Contexts\Shared\Application\SlugGenerator;
use App\Http\Controllers\Controller;
use App\Http\Requests\Learning\StorePathRequest;
use App\Http\Requests\Learning\SyncPathItemsRequest;
use App\Http\Requests\Learning\UpdatePathRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Specialised learning paths: public browsing of published paths plus
 * staff authoring (create, edit, publish, and define the ordered course
 * list) behind the paths.manage permission.
 */
final class PathController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = LearningPath::query()->with('items');

        $includeDrafts = $request->boolean('include_drafts')
            && ($request->user('sanctum')?->can(Permission::ManagePaths->value) ?? false);

        if (! $includeDrafts) {
            $query->published();
        }

        $paths = $query->latest('published_at')->latest('id')->get();

        return response()->json([
            'data' => $paths->map(fn (LearningPath $path): array => [
                'id' => $path->id,
                'title' => $path->title,
                'slug' => $path->slug,
                'summary' => $path->summary,
                'cover_image' => $path->cover_image,
                'published_at' => $path->published_at?->toIso8601String(),
                'courses_count' => $path->items->count(),
                'levels_count' => $path->items->pluck('level')->unique()->count(),
            ]),
        ]);
    }

    public function show(Request $request, LearningPath $path, PathProgressService $progress): JsonResponse
    {
        $user = $request->user('sanctum');
        $canManage = $user?->can(Permission::ManagePaths->value) ?? false;

        abort_unless($path->isPublished() || $canManage, 404);

        $states = $progress->itemsWithState($path, $user);

        $levels = collect($states)
            ->groupBy(fn (array $row): int => $row['item']->level)
            ->sortKeys()
            ->map(fn ($rows, int $level): array => [
                'level' => $level,
                'items' => $rows->map(function (array $row): array {
                    /** @var LearningPathItem $item */
                    $item = $row['item'];

                    return [
                        'course' => [
                            'id' => $item->course->id,
                            'title' => $item->course->title,
                            'slug' => $item->course->slug,
                            'summary' => $item->course->summary,
                            'pricing_type' => $item->course->pricing_type->value,
                            'price_minor' => $item->course->price_minor,
                            'instructor' => $item->course->instructor?->name,
                        ],
                        'position' => $item->position,
                        'state' => $row['state']->value,
                    ];
                })->values(),
            ])->values();

        $membership = $user === null ? null : PathEnrollment::query()
            ->where('user_id', $user->getKey())
            ->where('learning_path_id', $path->getKey())
            ->first();

        return response()->json([
            'data' => [
                'id' => $path->id,
                'title' => $path->title,
                'slug' => $path->slug,
                'summary' => $path->summary,
                'cover_image' => $path->cover_image,
                'description' => $path->description,
                'published_at' => $path->published_at?->toIso8601String(),
                'levels' => $levels,
                'viewer' => [
                    'enrolled' => $membership !== null,
                    'completed' => $membership?->status === PathEnrollmentStatus::Completed,
                    'progress' => $user !== null ? $progress->progress($path, $user) : null,
                ],
            ],
        ]);
    }

    public function store(StorePathRequest $request, SlugGenerator $slugs): JsonResponse
    {
        $path = LearningPath::query()->create([
            'title' => $request->validated('title'),
            'slug' => $slugs->forTitle($request->validated('title'), 'learning_paths'),
            'summary' => $request->validated('summary'),
            'description' => $request->validated('description'),
            'published_at' => $request->boolean('published') ? now() : null,
        ]);

        return response()->json(['data' => $path], 201);
    }

    public function update(UpdatePathRequest $request, LearningPath $path): JsonResponse
    {
        $data = $request->safe()->only(['title', 'summary', 'description']);

        if ($request->has('published')) {
            $data['published_at'] = $request->boolean('published')
                ? ($path->published_at ?? now())
                : null;
        }

        $path->update($data);

        return response()->json(['data' => $path->fresh()]);
    }

    public function destroy(Request $request, LearningPath $path): JsonResponse
    {
        abort_unless($request->user()?->can(Permission::ManagePaths->value) ?? false, 403);

        $path->delete();

        return response()->json(status: 204);
    }

    public function uploadCover(Request $request, LearningPath $path, ImageUploader $uploader): JsonResponse
    {
        abort_unless($request->user()?->can(Permission::ManagePaths->value) ?? false, 403);

        $request->validate([
            'image' => ['required', 'file', 'mimes:png,jpg,jpeg,webp', 'max:15360'],
        ]);

        $url = $uploader->store($request->file('image'), 'covers', $path->cover_image);
        $path->update(['cover_image' => $url]);

        return response()->json(['data' => ['cover_image' => $url]]);
    }

    /**
     * Replace the path's course list (course + level + position) in one
     * atomic call.
     */
    public function syncItems(SyncPathItemsRequest $request, LearningPath $path): JsonResponse
    {
        DB::transaction(function () use ($request, $path): void {
            $path->items()->delete();

            foreach ($request->validated('items') as $item) {
                LearningPathItem::query()->create([
                    'learning_path_id' => $path->getKey(),
                    'course_id' => $item['course_id'],
                    'level' => $item['level'],
                    'position' => $item['position'],
                ]);
            }
        });

        return response()->json([
            'data' => $path->items()->with('course:id,title,slug')->get(),
        ]);
    }
}
