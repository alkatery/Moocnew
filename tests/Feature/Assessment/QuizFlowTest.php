<?php

declare(strict_types=1);

use App\Contexts\Assessment\Infrastructure\Persistence\Question;
use App\Contexts\Assessment\Infrastructure\Persistence\Quiz;
use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Enrollment\Application\EnrollmentService;
use App\Contexts\Identity\Domain\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->instructor = userWithRole(Role::Instructor);
    $this->course = Course::factory()->published()->for($this->instructor, 'instructor')->create();
});

afterEach(fn () => Carbon::setTestNow());

function makeQuestion(Course $course, array $overrides = []): Question
{
    return Question::query()->create(array_merge([
        'course_id' => $course->id,
        'type' => 'mcq',
        'body' => 'سؤال؟',
        'choices' => [['id' => 'a', 'text' => 'أ'], ['id' => 'b', 'text' => 'ب']],
        'correct' => ['a'],
        'points' => 1,
    ], $overrides));
}

it('lets the instructor add a question to the bank', function () {
    Sanctum::actingAs($this->instructor);

    $this->postJson("/api/v1/assessment/courses/{$this->course->slug}/questions", [
        'type' => 'mcq',
        'body' => 'ما عاصمة السعودية؟',
        'choices' => [['id' => 'a', 'text' => 'الرياض'], ['id' => 'b', 'text' => 'جدة']],
        'correct' => ['a'],
        'points' => 2,
    ])->assertCreated()->assertJsonPath('data.correct', ['a']);
});

it('rejects an MCQ whose correct answer is not among the choices', function () {
    Sanctum::actingAs($this->instructor);

    $this->postJson("/api/v1/assessment/courses/{$this->course->slug}/questions", [
        'type' => 'mcq',
        'body' => 'سؤال',
        'choices' => [['id' => 'a', 'text' => 'أ']],
        'correct' => ['z'],
    ])->assertStatus(422)->assertJsonValidationErrors('correct');
});

it('forbids a student from authoring questions', function () {
    Sanctum::actingAs(userWithRole(Role::Student));

    $this->postJson("/api/v1/assessment/courses/{$this->course->slug}/questions", [
        'type' => 'true_false', 'body' => 'س', 'correct' => true,
    ])->assertForbidden();
});

it('creates a quiz from bank questions of the same course', function () {
    Sanctum::actingAs($this->instructor);
    $q1 = makeQuestion($this->course);
    $q2 = makeQuestion($this->course, ['type' => 'true_false', 'choices' => null, 'correct' => true]);

    $this->postJson("/api/v1/assessment/courses/{$this->course->slug}/quizzes", [
        'title' => 'اختبار الوحدة',
        'pass_mark' => 50,
        'question_ids' => [$q1->id, $q2->id],
    ])->assertCreated()->assertJsonPath('data.questions_count', 2);
});

it('rejects quiz questions from a different course', function () {
    Sanctum::actingAs($this->instructor);
    $foreign = makeQuestion(Course::factory()->create());

    $this->postJson("/api/v1/assessment/courses/{$this->course->slug}/quizzes", [
        'title' => 'اختبار',
        'question_ids' => [$foreign->id],
    ])->assertStatus(422)->assertJsonValidationErrors('question_ids');
});

it('runs the full attempt flow and auto-grades the score', function () {
    $q1 = makeQuestion($this->course, ['correct' => ['a'], 'points' => 1]);
    $q2 = makeQuestion($this->course, ['correct' => ['b'], 'points' => 1]);
    $quiz = Quiz::query()->create(['course_id' => $this->course->id, 'title' => 'ت', 'pass_mark' => 50, 'shuffle' => false]);
    $quiz->questions()->sync([$q1->id => ['position' => 1], $q2->id => ['position' => 2]]);

    $learner = User::factory()->create();
    app(EnrollmentService::class)->enroll($learner, $this->course);
    Sanctum::actingAs($learner);

    // Start: questions returned must not leak the answer key.
    $start = $this->postJson("/api/v1/assessment/quizzes/{$quiz->id}/attempts")->assertCreated();
    expect($start->getContent())->not->toContain('"correct"');
    $attemptId = $start->json('attempt.id');

    // Answer one of two correctly → 50%, which meets the pass mark.
    $this->postJson("/api/v1/assessment/attempts/{$attemptId}/submit", [
        'answers' => [$q1->id => ['a'], $q2->id => ['a']],
    ])->assertOk()
        ->assertJsonPath('data.score', 50)
        ->assertJsonPath('data.passed', true);
});

it('enforces the maximum number of attempts', function () {
    $q = makeQuestion($this->course);
    $quiz = Quiz::query()->create(['course_id' => $this->course->id, 'title' => 'ت', 'max_attempts' => 1]);
    $quiz->questions()->sync([$q->id => ['position' => 1]]);

    $learner = User::factory()->create();
    app(EnrollmentService::class)->enroll($learner, $this->course);
    Sanctum::actingAs($learner);

    $attemptId = $this->postJson("/api/v1/assessment/quizzes/{$quiz->id}/attempts")->json('attempt.id');
    $this->postJson("/api/v1/assessment/attempts/{$attemptId}/submit", ['answers' => [$q->id => ['a']]])->assertOk();

    // Second start exceeds the cap.
    $this->postJson("/api/v1/assessment/quizzes/{$quiz->id}/attempts")->assertStatus(422);
});

it('scores a late submission as zero', function () {
    $q = makeQuestion($this->course, ['correct' => ['a']]);
    $quiz = Quiz::query()->create(['course_id' => $this->course->id, 'title' => 'ت', 'time_limit_minutes' => 10, 'pass_mark' => 50]);
    $quiz->questions()->sync([$q->id => ['position' => 1]]);

    $learner = User::factory()->create();
    app(EnrollmentService::class)->enroll($learner, $this->course);
    Sanctum::actingAs($learner);

    Carbon::setTestNow('2026-06-01 10:00:00');
    $attemptId = $this->postJson("/api/v1/assessment/quizzes/{$quiz->id}/attempts")->json('attempt.id');

    // Submit 11 minutes later — past the 10-minute limit.
    Carbon::setTestNow('2026-06-01 10:11:00');
    $this->postJson("/api/v1/assessment/attempts/{$attemptId}/submit", ['answers' => [$q->id => ['a']]])
        ->assertOk()
        ->assertJsonPath('data.score', 0)
        ->assertJsonPath('data.passed', false);
});

it('forbids a non-enrolled user from starting an attempt', function () {
    $q = makeQuestion($this->course);
    $quiz = Quiz::query()->create(['course_id' => $this->course->id, 'title' => 'ت']);
    $quiz->questions()->sync([$q->id => ['position' => 1]]);

    Sanctum::actingAs(User::factory()->create()); // not enrolled

    $this->postJson("/api/v1/assessment/quizzes/{$quiz->id}/attempts")->assertForbidden();
});
