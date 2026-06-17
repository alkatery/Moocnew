<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Enrollment;

use App\Contexts\Catalog\Domain\Course\CourseStatus;
use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Enrollment\Application\CourseAccess;
use App\Contexts\Enrollment\Application\EnrollmentService;
use App\Contexts\Enrollment\Domain\PrerequisitesNotMet;
use App\Contexts\Enrollment\Infrastructure\Persistence\Enrollment;
use App\Http\Controllers\Controller;
use App\Http\Resources\EnrollmentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class EnrollmentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $enrollments = Enrollment::query()
            ->where('user_id', $request->user()->getKey())
            ->with('course')
            ->latest()
            ->paginate(15);

        return EnrollmentResource::collection($enrollments);
    }

    public function store(
        Request $request,
        Course $course,
        EnrollmentService $service,
        CourseAccess $access,
    ): JsonResponse {
        // المقررات غير المنشورة لا يمكن الالتحاق بها.
        abort_unless($course->status === CourseStatus::Published, 404);

        // الطاقم (مالك/مراجع/أدمن) يتجاوز فحص المتطلّبات — لمعاينة مقرراتهم.
        $bypass = $access->isStaffFor($request->user(), $course);

        try {
            $enrollment = $service->enroll($request->user(), $course, $bypass);
        } catch (PrerequisitesNotMet $e) {
            return $this->prerequisitesNotMetResponse($e);
        }

        return (new EnrollmentResource($enrollment))
            ->response()
            ->setStatusCode($enrollment->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * يحوّل استثناء المجال PrerequisitesNotMet إلى استجابة 422 بشكل §1.د من عقد E1.
     * تُحمَّل العناوين هنا في طبقة HTTP — Domain لا يعرف HTTP.
     */
    private function prerequisitesNotMetResponse(PrerequisitesNotMet $e): JsonResponse
    {
        $missing = Course::query()
            ->whereIn('id', $e->getMissingCourseIds())
            ->select(['id', 'title', 'slug'])
            ->get();

        $firstName = $missing->first()?->title ?? '';

        return response()->json([
            'message' => 'يجب إكمال المتطلّبات السابقة قبل الالتحاق بهذا المقرر.',
            'errors' => [
                'prerequisites' => [
                    "أكمل المقرر «{$firstName}» أولاً.",
                ],
            ],
            'prerequisites' => $missing->map(fn (Course $c) => [
                'id' => $c->id,
                'title' => $c->title,
                'slug' => $c->slug,
            ])->values()->all(),
        ], 422);
    }
}
