<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Learning;

use App\Contexts\Catalog\Infrastructure\Persistence\Lesson;
use App\Contexts\Enrollment\Application\LessonAccess;
use App\Contexts\Learning\Infrastructure\Persistence\Bookmark;
use App\Http\Controllers\Controller;
use App\Http\Resources\BookmarkResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * علامات المتعلّم المرجعية على الدروس — مرآة LessonNoteController بحقلَي
 * user_id و lesson_id فقط. كل المسارات محمية بـ auth:sanctum (D1).
 */
final class BookmarkController extends Controller
{
    /**
     * GET /api/v1/bookmarks — قائمة علامات المستخدم المصادَق (الأحدث أولاً).
     *
     * لا فحص وصول هنا — الفلترة بالملكية كافية (§1.أ).
     * eager-load lesson.section.course لمنع N+1 إلزامياً.
     */
    public function index(Request $request): JsonResponse
    {
        $bookmarks = Bookmark::query()
            ->where('user_id', $request->user()->getKey())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->with('lesson.section.course')
            ->get();

        return response()->json(['data' => BookmarkResource::collection($bookmarks)]);
    }

    /**
     * POST /api/v1/bookmarks — حفظ علامة مرجعية (idempotent).
     *
     * التخويل: LessonAccess::canAccess (نفس قاعدة LessonNoteController::store).
     * علامة جديدة → 201، علامة قائمة → 200 (لا 409).
     */
    public function store(Request $request, LessonAccess $access): JsonResponse
    {
        $validated = $request->validate([
            'lesson_id' => ['required', 'integer', 'exists:lessons,id'],
        ]);

        /** @var Lesson $lesson */
        $lesson = Lesson::query()->findOrFail($validated['lesson_id']);

        abort_unless($access->canAccess($request->user(), $lesson), 403);

        [$bookmark, $created] = [
            Bookmark::query()->firstOrCreate([
                'user_id' => $request->user()->getKey(),
                'lesson_id' => $lesson->getKey(),
            ]),
            false,
        ];

        // نحدّد ما إذا كانت العلامة منشأة للتوّ عبر wasRecentlyCreated
        $statusCode = $bookmark->wasRecentlyCreated ? 201 : 200;

        // eager-load للـ Resource بعد الإنشاء/الاسترداد
        $bookmark->load('lesson.section.course');

        return response()->json(['data' => new BookmarkResource($bookmark)], $statusCode);
    }

    /**
     * DELETE /api/v1/bookmarks/{bookmark} — حذف فعلي للعلامة (204).
     *
     * ملكية: user_id === المستخدم المصادَق (مرآة LessonNoteController::destroy:55).
     */
    public function destroy(Request $request, Bookmark $bookmark): JsonResponse
    {
        abort_unless($bookmark->user_id === $request->user()->getKey(), 403);

        $bookmark->delete();

        return response()->json(status: 204);
    }
}
