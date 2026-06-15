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

        // §2.أ — eager-load user:id,name لتفادي N+1 (الاسم فقط، PDPL)
        return AssignmentSubmissionResource::collection(
            $assignment->submissions()->with('user:id,name')->latest('submitted_at')->paginate(20),
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
        $assignment = $submission->assignment;
        $rubricScores = $request->validated('rubric_scores');

        // Rubric grading: clamp every criterion to its max and sum into the
        // final grade (capped at the assignment's points).
        if ($rubricScores !== null && is_array($assignment->rubric)) {
            $clamped = [];
            $total = 0;
            foreach ($assignment->rubric as $criterion) {
                $id = (string) ($criterion['id'] ?? '');
                $maxPoints = (int) ($criterion['max_points'] ?? 0);
                $awarded = max(0, min($maxPoints, (int) ($rubricScores[$id] ?? 0)));
                $clamped[$id] = $awarded;
                $total += $awarded;
            }
            $grade = min($total, (int) $assignment->points);
            $rubricScores = $clamped;
        } else {
            $grade = (int) $request->validated('grade');
            $rubricScores = null;
        }

        $submission->update([
            'grade' => $grade,
            'rubric_scores' => $rubricScores,
            'feedback' => $request->validated('feedback'),
            'graded_by' => $request->user()->getKey(),
            'graded_at' => Date::now(),
        ]);

        $activity->log('assignment.graded', $request->user(), $submission, [
            'grade' => $submission->grade,
        ]);

        // A passing grade can complete a course whose lessons are already done.
        $completion->evaluateFor($submission->user_id, $submission->assignment->course_id);

        // §2.أ — eager-load الاسم لإدراجه في الاستجابة (PDPL: الاسم فقط)
        $submission->loadMissing('user:id,name');

        return new AssignmentSubmissionResource($submission);
    }
}
