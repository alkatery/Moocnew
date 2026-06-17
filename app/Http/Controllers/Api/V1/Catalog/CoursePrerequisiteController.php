<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Contexts\Catalog\Domain\Course\CourseStatus;
use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * إدارة المتطلّبات السابقة للمقرر (E1 — §1.ب/§1.ج).
 * التخويل: CoursePolicy::update — المالك أو المراجع فقط.
 * الموديل: علاقة BelongsToMany ذاتية على Course::prerequisites().
 */
final class CoursePrerequisiteController extends Controller
{
    /**
     * إضافة متطلّب سابق للمقرر.
     *
     * POST /api/v1/catalog/courses/{course}/prerequisites
     * body: { prerequisite_course_id: int }
     *
     * 422 — ذاتي / غير منشور / تحقّق قياسي.
     * 200 — زوج مكرّر (idempotent).
     * 201 — مضاف ناجح.
     */
    public function store(Request $request, Course $course): JsonResponse
    {
        abort_unless($request->user()->can('update', $course), 403);

        $data = $request->validate([
            'prerequisite_course_id' => ['required', 'integer', 'exists:courses,id'],
        ]);

        $prerequisiteId = (int) $data['prerequisite_course_id'];

        // منع التطابق الذاتي — المقرر لا يشترط نفسه.
        if ($prerequisiteId === $course->getKey()) {
            return response()->json([
                'message' => 'لا يمكن أن يكون المقرر متطلّباً لنفسه.',
                'errors' => ['prerequisite_course_id' => ['لا يمكن أن يكون المقرر متطلّباً لنفسه.']],
            ], 422);
        }

        /** @var Course $prerequisite */
        $prerequisite = Course::query()->findOrFail($prerequisiteId);

        // المتطلّب يجب أن يكون منشوراً — مسودّة لا معنى لها للطالب.
        if ($prerequisite->status !== CourseStatus::Published) {
            return response()->json([
                'message' => 'لا يمكن اشتراط مقرر غير منشور.',
                'errors' => ['prerequisite_course_id' => ['لا يمكن اشتراط مقرر غير منشور.']],
            ], 422);
        }

        // فحص التكرار — idempotent: إن كان موجوداً يُعاد 200 بالقائمة الحالية.
        $alreadyExists = $course->prerequisites()->where('prerequisite_course_id', $prerequisiteId)->exists();

        if (! $alreadyExists) {
            $course->prerequisites()->attach($prerequisiteId);
        }

        $list = $course
            ->prerequisites()
            ->where('status', CourseStatus::Published->value)
            ->select(['courses.id', 'courses.title', 'courses.slug'])
            ->get()
            ->map(fn (Course $c) => ['id' => $c->id, 'title' => $c->title, 'slug' => $c->slug])
            ->values()
            ->all();

        return response()->json(['data' => $list], $alreadyExists ? 200 : 201);
    }

    /**
     * إزالة متطلّب سابق من المقرر.
     *
     * DELETE /api/v1/catalog/courses/{course}/prerequisites/{prerequisite}
     * {prerequisite} = id المقرر-المتطلّب.
     *
     * 204 — تمّت الإزالة أو لم يكن موجوداً (idempotent).
     */
    public function destroy(Request $request, Course $course, int $prerequisite): JsonResponse
    {
        abort_unless($request->user()->can('update', $course), 403);

        // detach بلا شرط — idempotent: لا 404 إن لم يكن موجوداً.
        $course->prerequisites()->detach($prerequisite);

        return response()->json(status: 204);
    }
}
