<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Engagement;

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Engagement\Application\ReviewService;
use App\Contexts\Engagement\Infrastructure\Persistence\CourseReview;
use App\Contexts\Enrollment\Application\CourseAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Engagement\StoreReviewRequest;
use Illuminate\Http\JsonResponse;

/**
 * Course ratings & reviews (PRD-MV2 extension). Public read; only enrolled
 * learners may write, one review per course.
 */
final class ReviewController extends Controller
{
    public function index(Course $course, ReviewService $reviews): JsonResponse
    {
        $list = CourseReview::query()
            ->where('course_id', $course->id)
            ->with('user:id,name')
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (CourseReview $r): array => [
                'id' => $r->id,
                'rating' => $r->rating,
                'comment' => $r->comment,
                'user' => $r->user?->name,
                'created_at' => $r->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'data' => $list,
            'summary' => $reviews->summary($course->id),
        ]);
    }

    public function store(
        StoreReviewRequest $request,
        Course $course,
        ReviewService $reviews,
        CourseAccess $access,
    ): JsonResponse {
        abort_unless($access->hasActiveEnrollment($request->user(), $course->id), 403, 'يجب الالتحاق بالدورة لتقييمها.');

        $review = $reviews->submit(
            $request->user(),
            $course->id,
            (int) $request->validated('rating'),
            $request->validated('comment'),
        );

        return response()->json([
            'data' => [
                'id' => $review->id,
                'rating' => $review->rating,
                'comment' => $review->comment,
            ],
        ], 201);
    }
}
