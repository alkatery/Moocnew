<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Identity\Application\BulkUserImport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportUsersRequest;
use Illuminate\Http\JsonResponse;

/**
 * Bulk student onboarding from a CSV file (PRD §4). Gated by users.manage;
 * returns a per-file summary so the admin can see exactly what happened.
 */
final class UserImportController extends Controller
{
    public function store(ImportUsersRequest $request, BulkUserImport $import): JsonResponse
    {
        $courseId = $request->validated('course_id');
        $course = $courseId !== null ? Course::query()->findOrFail((int) $courseId) : null;

        $summary = $import->handle(
            $request->user(),
            $request->file('file')->getRealPath(),
            $course,
        );

        return response()->json(['data' => $summary]);
    }
}
