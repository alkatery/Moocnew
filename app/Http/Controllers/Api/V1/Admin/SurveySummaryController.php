<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Contexts\Engagement\Application\SurveyService;
use App\Contexts\Identity\Domain\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin view over the NELC satisfaction surveys (analytics.view): with a
 * `course_id` returns that course's axis averages, response count and the
 * latest 20 comments (anonymous — PDPL); without it, returns the per-course
 * averages for every published course.
 */
final class SurveySummaryController extends Controller
{
    public function __invoke(Request $request, SurveyService $surveys): JsonResponse
    {
        abort_unless($request->user()->can(Permission::ViewAnalytics->value), 403);

        $courseId = $request->query('course_id');

        if ($courseId !== null && $courseId !== '') {
            return response()->json(['data' => $surveys->summaryFor((int) $courseId)]);
        }

        return response()->json(['data' => $surveys->perCourseSummaries()]);
    }
}
