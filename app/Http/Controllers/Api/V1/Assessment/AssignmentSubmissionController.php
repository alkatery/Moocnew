<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Assessment;

use App\Contexts\Assessment\Infrastructure\Persistence\Assignment;
use App\Contexts\Assessment\Infrastructure\Persistence\AssignmentSubmission;
use App\Contexts\Enrollment\Application\CourseAccess;
use App\Contexts\Enrollment\Application\CourseCompletionService;
use App\Contexts\Identity\Application\ActivityLogger;
use App\Http\Controllers\Controller;
use App\Http\Requests\Assessment\GradeSubmissionRequest;
use App\Http\Requests\Assessment\SubmitAssignmentRequest;
use App\Http\Resources\AssignmentSubmissionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Date;

final class AssignmentSubmissionController extends Controller
{
    /**
     * Instructor view of all submissions for an assignment.
     */
    public function index(Request $request, Assignment $assignment): AnonymousResourceCollection
    {
        abort_unless($request->user()->can('update', $assignment->course), 403);

        return AssignmentSubmissionResource::collection(
            $assignment->submissions()->latest('submitted_at')->paginate(20),
        );
    }

    /**
     * Learner submits (or re-submits) their work.
     */
    public function store(
        SubmitAssignmentRequest $request,
        Assignment $assignment,
        CourseAccess $access,
    ): JsonResponse {
        abort_unless($access->hasActiveEnrollment($request->user(), $assignment->course_id), 403);

        $filePath = $request->hasFile('file')
            ? $request->file('file')->store("assignments/{$assignment->id}", 'media')
            : null;

        $submission = AssignmentSubmission::query()->updateOrCreate(
            ['assignment_id' => $assignment->getKey(), 'user_id' => $request->user()->getKey()],
            [
                'content' => $request->validated('content'),
                'file_path' => $filePath,
                'submitted_at' => Date::now(),
                // Re-submission clears any previous grade.
                'grade' => null,
                'feedback' => null,
                'graded_by' => null,
                'graded_at' => null,
            ],
        );

        return (new AssignmentSubmissionResource($submission))
            ->response()
            ->setStatusCode($submission->wasRecentlyCreated ? 201 : 200);
    }

    public function grade(
        GradeSubmissionRequest $request,
        AssignmentSubmission $submission,
        ActivityLogger $activity,
        CourseCompletionService $completion,
    ): AssignmentSubmissionResource {
        $submission->update([
            'grade' => (int) $request->validated('grade'),
            'feedback' => $request->validated('feedback'),
            'graded_by' => $request->user()->getKey(),
            'graded_at' => Date::now(),
        ]);

        $activity->log('assignment.graded', $request->user(), $submission, [
            'grade' => $submission->grade,
        ]);

        // A passing grade can complete a course whose lessons are already done.
        $completion->evaluateFor($submission->user_id, $submission->assignment->course_id);

        return new AssignmentSubmissionResource($submission);
    }
}
