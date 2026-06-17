<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Enrollment;

use App\Contexts\Enrollment\Application\EnrollmentCodeService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Learner-facing redemption of a self-enrollment code: a valid code enrolls
 * the caller as an active student in the code's course.
 */
final class EnrollmentCodeRedeemController extends Controller
{
    public function __invoke(Request $request, EnrollmentCodeService $codes): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:16'],
        ], [
            'code.required' => 'يرجى إدخال كود الالتحاق.',
        ]);

        $course = $codes->redeem($request->user(), $validated['code']);

        return response()->json([
            'data' => [
                'slug' => $course->slug,
                'title' => $course->title,
            ],
        ]);
    }
}
