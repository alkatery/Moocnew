<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Analytics;

use App\Contexts\Analytics\Application\AnalyticsService;
use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Identity\Domain\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AnalyticsController extends Controller
{
    public function overview(Request $request, AnalyticsService $analytics): JsonResponse
    {
        abort_unless($request->user()->can(Permission::ViewAnalytics->value), 403);

        return response()->json(['data' => $analytics->overview()]);
    }

    /**
     * Per-course dropout funnel — for the course owner or an analytics viewer.
     */
    public function courseDropoff(Request $request, Course $course, AnalyticsService $analytics): JsonResponse
    {
        $allowed = $request->user()->can(Permission::ViewAnalytics->value)
            || $request->user()->can('update', $course);
        abort_unless($allowed, 403);

        return response()->json(['data' => $analytics->courseDropoff($course->getKey())]);
    }
}
