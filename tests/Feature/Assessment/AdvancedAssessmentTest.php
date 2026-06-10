<?php

declare(strict_types=1);

use App\Contexts\Assessment\Infrastructure\Persistence\Assignment;
use App\Contexts\Assessment\Infrastructure\Persistence\AssignmentSubmission;
use App\Contexts\Assessment\Infrastructure\Persistence\Question;
use App\Contexts\Assessment\Infrastructure\Persistence\Quiz;
use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Enrollment\Domain\EnrollmentStatus;
use App\Contexts\Enrollment\Infrastructure\Persistence\Enrollment;
use App\Contexts\Identity\Domain\Role;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function assessmentCourse(): array
{
    $instructor = userWithRole(Role::Instructor);
    $course = Course::factory()->published()->create(['instructor_id' => $instructor->id]);
    $student = userWithRole(Role::Student);
    Enrollment::query()->create([
        'user_id' => $student->id, 'course_id' => $course->id,
        'status' => EnrollmentStatus::Active, 'progress_percent' => 0, 'enrolled_at' => now(),
    ]);

    return [$instructor, $course, $student];
}

function tfQuestion(Course $course, bool $answer = true, ?string $explanation = null): Question
{
    return Question::query()->create([
        'course_id' => $course->id, 'type' => 'true_false', 'body' => 'سؤال '.uniqid(),
        'choices' => null, 'correct' => $answer, 'explanation' => $explanation, 'points' => 1,
    ]);
}

// ------------------------------------------------------------- random draw

it('freezes a random subset of questions on draw-count quizzes', function () {
    [, $course, $student] = assessmentCourse();
    $questions = collect(range(1, 6))->map(fn () => tfQuestion($course));
    $quiz = Quiz::query()->create(['course_id' => $course->id, 'title' => 'سحب عشوائي', 'pass_mark' => 50, 'draw_count' => 2, 'shuffle' => false]);
    $quiz->questions()->sync($questions->mapWithKeys(fn ($q, $i) => [$q->id => ['position' => $i + 1]])->all());

    $start = test()->actingAs($student)
        ->postJson("/api/v1/assessment/quizzes/{$quiz->id}/attempts")
        ->assertCreated();

    expect($start->json('questions'))->toHaveCount(2);

    // Resuming the attempt serves the SAME frozen subset.
    $resume = test()->actingAs($student)
        ->postJson("/api/v1/assessment/quizzes/{$quiz->id}/attempts")
        ->assertOk();
    expect(collect($resume->json('questions'))->pluck('id')->sort()->values()->all())
        ->toBe(collect($start->json('questions'))->pluck('id')->sort()->values()->all());
});

it('grades a drawn attempt against its frozen subset only', function () {
    [, $course, $student] = assessmentCourse();
    $questions = collect(range(1, 5))->map(fn () => tfQuestion($course, true));
    $quiz = Quiz::query()->create(['course_id' => $course->id, 'title' => 'سحب', 'pass_mark' => 50, 'draw_count' => 2]);
    $quiz->questions()->sync($questions->mapWithKeys(fn ($q, $i) => [$q->id => ['position' => $i + 1]])->all());

    $start = test()->actingAs($student)->postJson("/api/v1/assessment/quizzes/{$quiz->id}/attempts");
    $drawn = collect($start->json('questions'))->pluck('id');
    $attemptId = $start->json('attempt.id');

    // Answer both drawn questions correctly → 100% even though 3 others unanswered.
    $submit = test()->actingAs($student)
        ->postJson("/api/v1/assessment/attempts/{$attemptId}/submit", [
            'answers' => $drawn->mapWithKeys(fn ($id) => [$id => true])->all(),
        ])
        ->assertOk();

    expect($submit->json('data.score'))->toBe(100);
});

// ----------------------------------------------------- instant feedback

it('returns per-question feedback with explanations after submission', function () {
    [, $course, $student] = assessmentCourse();
    $q = tfQuestion($course, true, 'لأن المتغيرات في بايثون لا تحتاج تعريف نوع مسبق.');
    $quiz = Quiz::query()->create(['course_id' => $course->id, 'title' => 'تغذية راجعة', 'pass_mark' => 50]);
    $quiz->questions()->sync([$q->id => ['position' => 1]]);

    $start = test()->actingAs($student)->postJson("/api/v1/assessment/quizzes/{$quiz->id}/attempts");
    $attemptId = $start->json('attempt.id');

    // The pre-submission payload never leaks the key or the explanation.
    expect($start->json('questions.0'))->not->toHaveKeys(['correct', 'explanation']);

    $submit = test()->actingAs($student)
        ->postJson("/api/v1/assessment/attempts/{$attemptId}/submit", ['answers' => [$q->id => false]])
        ->assertOk();

    $feedback = $submit->json('feedback.0');
    expect($feedback['is_correct'])->toBeFalse()
        ->and($feedback['correct'])->toBe(true)
        ->and($feedback['explanation'])->toContain('بايثون');
});

// ---------------------------------------------------------------- rubrics

it('grades an assignment with a rubric, clamping each criterion to its max', function () {
    [$instructor, $course, $student] = assessmentCourse();

    $create = test()->actingAs($instructor)
        ->postJson("/api/v1/assessment/courses/{$course->slug}/assignments", [
            'title' => 'مشروع', 'points' => 100,
            'rubric' => [
                ['id' => 'clarity', 'title' => 'وضوح الفكرة', 'max_points' => 40],
                ['id' => 'code', 'title' => 'جودة الكود', 'max_points' => 60],
            ],
        ])
        ->assertCreated();
    $assignmentId = $create->json('data.id');
    expect($create->json('data.rubric.0.title'))->toBe('وضوح الفكرة');

    test()->actingAs($student)
        ->postJson("/api/v1/assessment/assignments/{$assignmentId}/submissions", ['content' => 'الحل'])
        ->assertCreated();
    $submission = AssignmentSubmission::query()->where('assignment_id', $assignmentId)->firstOrFail();

    // 50 over the 40-point criterion clamps to 40 → grade 40+30=70.
    $graded = test()->actingAs($instructor)
        ->postJson("/api/v1/assessment/submissions/{$submission->id}/grade", [
            'rubric_scores' => ['clarity' => 50, 'code' => 30],
            'feedback' => 'عمل جيد',
        ])
        ->assertOk();

    expect($graded->json('data.grade'))->toBe(70)
        ->and($graded->json('data.rubric_scores.clarity'))->toBe(40);
});

it('still grades with a plain grade when no rubric scores are sent', function () {
    [$instructor, $course, $student] = assessmentCourse();
    $assignment = Assignment::query()->create(['course_id' => $course->id, 'title' => 'واجب', 'points' => 100]);
    test()->actingAs($student)
        ->postJson("/api/v1/assessment/assignments/{$assignment->id}/submissions", ['content' => 'الحل'])
        ->assertCreated();
    $submission = AssignmentSubmission::query()->where('assignment_id', $assignment->id)->firstOrFail();

    test()->actingAs($instructor)
        ->postJson("/api/v1/assessment/submissions/{$submission->id}/grade", ['grade' => 85])
        ->assertOk()
        ->assertJsonPath('data.grade', 85);
});
