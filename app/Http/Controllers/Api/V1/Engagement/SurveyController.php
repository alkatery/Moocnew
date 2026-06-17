<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Engagement;

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Engagement\Application\SurveyService;
use App\Contexts\Enrollment\Application\CourseAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Engagement\StoreSurveyRequest;
use Illuminate\Http\JsonResponse;

/**
 * NELC learner-satisfaction survey: only learners who completed the course
 * may answer; resubmitting updates the existing answer (upsert).
 */
final class SurveyController extends Controller
{
    public function store(
        StoreSurveyRequest $request,
        Course $course,
        SurveyService $surveys,
        CourseAccess $access,
    ): JsonResponse {
        abort_unless(
            $access->hasCompletedEnrollment($request->user(), $course->getKey()),
            403,
            'استبيان الرضا متاح فقط لمن أكمل الدورة.',
        );

        $survey = $surveys->submit($request->user(), $course->getKey(), $request->validated());

        return response()->json([
            'data' => [
                'id' => $survey->id,
                'overall' => $survey->overall,
                'content_quality' => $survey->content_quality,
                'instructor_quality' => $survey->instructor_quality,
                'platform_quality' => $survey->platform_quality,
                'comment' => $survey->comment,
            ],
        ], $survey->wasRecentlyCreated ? 201 : 200);
    }
}
