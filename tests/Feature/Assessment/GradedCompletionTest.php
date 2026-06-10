<?php

declare(strict_types=1);

use App\Contexts\Assessment\Infrastructure\Persistence\Assignment;
use App\Contexts\Assessment\Infrastructure\Persistence\AssignmentSubmission;
use App\Contexts\Assessment\Infrastructure\Persistence\Question;
use App\Contexts\Assessment\Infrastructure\Persistence\Quiz;
use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Certification\Infrastructure\Persistence\Certificate;
use App\Contexts\Enrollment\Application\EnrollmentService;
use App\Contexts\Enrollment\Domain\EnrollmentStatus;
use App\Contexts\Enrollment\Infrastructure\Persistence\Enrollment;
use App\Contexts\Identity\Domain\Role;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/**
 * A published free course with one article lesson and a true/false quiz
 * (worth full marks). Returns [course, lesson, quiz].
 */
function gradedCourse(int $passingGrade): array
{
    $instructor = userWithRole(Role::Instructor);
    $course = Course::factory()->for($instructor, 'instructor')->published()->create([
        'pricing_type' => 'free',
        'price_minor' => 0,
        'passing_grade' => $passingGrade,
    ]);

    $section = $course->sections()->create(['title' => 'قسم', 'position' => 1]);
    $lesson = $section->lessons()->create([
        'title' => 'درس', 'type' => 'article', 'content' => 'محتوى', 'position' => 1,
    ]);

    $quiz = Quiz::query()->create([
        'course_id' => $course->id,
        'title' => 'الاختبار النهائي',
        'pass_mark' => 50,
    ]);
    $question = Question::query()->create([
        'course_id' => $course->id,
        'type' => 'true_false',
        'body' => 'السماء زرقاء؟',
        'correct' => true,
        'points' => 10,
    ]);
    $quiz->questions()->attach($question->id, ['position' => 1]);

    return [$course, $lesson, $quiz, $question];
}

function completeLesson(int $lessonId): void
{
    test()->postJson("/api/v1/lessons/{$lessonId}/progress", ['completed' => true])->assertOk();
}

function passQuiz(Quiz $quiz, int $questionId, bool $answer = true): void
{
    $attempt = test()->postJson("/api/v1/assessment/quizzes/{$quiz->id}/attempts")
        ->assertSuccessful()->json('attempt.id');

    test()->postJson("/api/v1/assessment/attempts/{$attempt}/submit", [
        'answers' => [$questionId => $answer],
    ])->assertOk();
}

it('does not complete a graded course on lessons alone — the quiz must be passed', function () {
    [$course, $lesson, $quiz, $q] = gradedCourse(70);
    $student = userWithRole(Role::Student);
    app(EnrollmentService::class)->enroll($student, $course);
    Sanctum::actingAs($student);

    completeLesson($lesson->id);

    // Lessons done (100%) but no passing grade yet → still active, no cert.
    $enrollment = Enrollment::query()
        ->where('user_id', $student->id)->where('course_id', $course->id)->first();
    expect($enrollment->status)->toBe(EnrollmentStatus::Active);
    expect($enrollment->progress_percent)->toBe(100);
    $this->assertDatabaseMissing('certificates', ['user_id' => $student->id, 'course_id' => $course->id]);
});

it('completes the course and issues a graded certificate once the quiz is passed', function () {
    [$course, $lesson, $quiz, $q] = gradedCourse(70);
    $student = userWithRole(Role::Student);
    app(EnrollmentService::class)->enroll($student, $course);
    Sanctum::actingAs($student);

    completeLesson($lesson->id);
    passQuiz($quiz, $q->id, true); // 100% ≥ 70

    $this->assertDatabaseHas('enrollments', [
        'user_id' => $student->id,
        'course_id' => $course->id,
        'status' => EnrollmentStatus::Completed->value,
    ]);

    $this->assertDatabaseHas('certificates', [
        'user_id' => $student->id,
        'course_id' => $course->id,
        'grade' => 100,
    ]);

    $uuid = Certificate::query()
        ->where('user_id', $student->id)->where('course_id', $course->id)->value('verification_uuid');

    $this->getJson("/api/v1/certificates/verify/{$uuid}")
        ->assertOk()
        ->assertJsonPath('valid', true)
        ->assertJsonPath('grade', 100);
});

it('does not complete when the quiz score is below the passing grade', function () {
    [$course, $lesson, $quiz, $q] = gradedCourse(70);
    $student = userWithRole(Role::Student);
    app(EnrollmentService::class)->enroll($student, $course);
    Sanctum::actingAs($student);

    completeLesson($lesson->id);
    passQuiz($quiz, $q->id, false); // wrong → 0% < 70

    $this->assertDatabaseHas('enrollments', [
        'user_id' => $student->id,
        'course_id' => $course->id,
        'status' => EnrollmentStatus::Active->value,
    ]);
    $this->assertDatabaseMissing('certificates', ['user_id' => $student->id, 'course_id' => $course->id]);
});

it('keeps ungraded courses completing on lessons alone', function () {
    // A course with no quizzes/assignments → no grade to gate on.
    $course = Course::factory()->published()->create([
        'pricing_type' => 'free', 'price_minor' => 0, 'passing_grade' => 0,
    ]);
    $section = $course->sections()->create(['title' => 'قسم', 'position' => 1]);
    $lesson = $section->lessons()->create([
        'title' => 'درس', 'type' => 'article', 'content' => 'محتوى', 'position' => 1,
    ]);

    $student = userWithRole(Role::Student);
    app(EnrollmentService::class)->enroll($student, $course);
    Sanctum::actingAs($student);

    completeLesson($lesson->id);

    $this->assertDatabaseHas('enrollments', [
        'user_id' => $student->id,
        'course_id' => $course->id,
        'status' => EnrollmentStatus::Completed->value,
    ]);
    // Ungraded → certificate carries a null grade.
    $this->assertDatabaseHas('certificates', [
        'user_id' => $student->id, 'course_id' => $course->id, 'grade' => null,
    ]);
});

it('completes the course when a passing assignment grade is recorded last', function () {
    [$course, $lesson, $quiz, $q] = gradedCourse(60);
    $assignment = Assignment::query()->create([
        'course_id' => $course->id, 'title' => 'مشروع', 'points' => 100,
    ]);

    $student = userWithRole(Role::Student);
    app(EnrollmentService::class)->enroll($student, $course);
    Sanctum::actingAs($student);

    completeLesson($lesson->id);
    passQuiz($quiz, $q->id, true); // quiz 100, assignment 0 → avg 50 < 60 → not yet

    $this->assertDatabaseHas('enrollments', [
        'user_id' => $student->id, 'course_id' => $course->id, 'status' => EnrollmentStatus::Active->value,
    ]);

    // Learner submits, instructor grades 80 → avg (100+80)/2 = 90 ≥ 60.
    $this->postJson("/api/v1/assessment/assignments/{$assignment->id}/submissions", [
        'content' => 'تسليمي',
    ])->assertSuccessful();
    $submissionId = AssignmentSubmission::query()
        ->where('assignment_id', $assignment->id)->where('user_id', $student->id)->value('id');

    Sanctum::actingAs($course->instructor); // owner grades
    $this->postJson("/api/v1/assessment/submissions/{$submissionId}/grade", ['grade' => 80])->assertOk();

    $this->assertDatabaseHas('enrollments', [
        'user_id' => $student->id, 'course_id' => $course->id, 'status' => EnrollmentStatus::Completed->value,
    ]);
    $this->assertDatabaseHas('certificates', [
        'user_id' => $student->id, 'course_id' => $course->id, 'grade' => 90,
    ]);
});

it('reports the learner gradebook for a course', function () {
    [$course, $lesson, $quiz, $q] = gradedCourse(70);
    $student = userWithRole(Role::Student);
    app(EnrollmentService::class)->enroll($student, $course);
    Sanctum::actingAs($student);

    passQuiz($quiz, $q->id, true);

    $this->getJson("/api/v1/assessment/courses/{$course->slug}/grade")
        ->assertOk()
        ->assertJsonPath('data.passing_grade', 70)
        ->assertJsonPath('data.overall', 100)
        ->assertJsonPath('data.passed', true)
        ->assertJsonPath('data.components.0.type', 'quiz')
        ->assertJsonPath('data.components.0.score', 100);
});
