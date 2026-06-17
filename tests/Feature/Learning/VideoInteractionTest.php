<?php

declare(strict_types=1);

use App\Contexts\Assessment\Infrastructure\Persistence\Question;
use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Enrollment\Domain\EnrollmentStatus;
use App\Contexts\Enrollment\Infrastructure\Persistence\Enrollment;
use App\Contexts\Identity\Domain\Role;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function interactiveLesson(): array
{
    $instructor = userWithRole(Role::Instructor);
    $course = Course::factory()->published()->create(['instructor_id' => $instructor->id]);
    $section = $course->sections()->create(['title' => 'قسم', 'position' => 1]);
    $lesson = $section->lessons()->create([
        'title' => 'فيديو', 'type' => 'video', 'position' => 1,
        'video_provider' => 'youtube', 'video_id' => 'abc123', 'video_status' => 'ready',
    ]);
    $student = userWithRole(Role::Student);
    Enrollment::query()->create([
        'user_id' => $student->id, 'course_id' => $course->id,
        'status' => EnrollmentStatus::Active, 'progress_percent' => 0, 'enrolled_at' => now(),
    ]);

    return [$instructor, $course, $lesson, $student];
}

// ------------------------------------------------------------------- notes

it('lets a learner create, list and delete time-anchored notes', function () {
    [, , $lesson, $student] = interactiveLesson();

    $created = test()->actingAs($student)
        ->postJson("/api/v1/lessons/{$lesson->id}/notes", ['body' => 'نقطة مهمة هنا', 'at_seconds' => 95])
        ->assertCreated();

    test()->actingAs($student)
        ->getJson("/api/v1/lessons/{$lesson->id}/notes")
        ->assertOk()
        ->assertJsonPath('data.0.body', 'نقطة مهمة هنا')
        ->assertJsonPath('data.0.at_seconds', 95);

    test()->actingAs($student)
        ->deleteJson('/api/v1/lesson-notes/'.$created->json('data.id'))
        ->assertNoContent();
});

it('keeps notes private to their owner', function () {
    [, , $lesson, $student] = interactiveLesson();
    $other = userWithRole(Role::Student);
    Enrollment::query()->create([
        'user_id' => $other->id, 'course_id' => $lesson->section->course_id,
        'status' => EnrollmentStatus::Active, 'progress_percent' => 0, 'enrolled_at' => now(),
    ]);

    $note = test()->actingAs($student)
        ->postJson("/api/v1/lessons/{$lesson->id}/notes", ['body' => 'سرّي'])
        ->assertCreated();

    // The other learner neither sees nor deletes it.
    test()->actingAs($other)
        ->getJson("/api/v1/lessons/{$lesson->id}/notes")
        ->assertOk()->assertJsonCount(0, 'data');
    test()->actingAs($other)
        ->deleteJson('/api/v1/lesson-notes/'.$note->json('data.id'))
        ->assertForbidden();
});

// ------------------------------------------------------------- checkpoints

it('serves in-video checkpoints and checks answers formatively', function () {
    [$instructor, $course, $lesson, $student] = interactiveLesson();
    $question = Question::query()->create([
        'course_id' => $course->id, 'type' => 'true_false', 'body' => 'هل بايثون لغة مفسّرة؟',
        'choices' => null, 'correct' => true, 'explanation' => 'بايثون تُنفَّذ سطراً بسطر عبر المفسّر.', 'points' => 1,
    ]);

    // Instructor anchors the checkpoint at 02:00.
    test()->actingAs($instructor)
        ->patchJson("/api/v1/catalog/lessons/{$lesson->id}", [
            'checkpoints' => [['at_seconds' => 120, 'question_id' => $question->id]],
        ])
        ->assertOk();

    // Learner fetches checkpoints — question body without the answer key.
    $list = test()->actingAs($student)
        ->getJson("/api/v1/lessons/{$lesson->id}/checkpoints")
        ->assertOk();
    expect($list->json('data.0.at_seconds'))->toBe(120)
        ->and($list->json('data.0.question'))->not->toHaveKey('correct');

    // Wrong answer → formative feedback with the explanation.
    test()->actingAs($student)
        ->postJson("/api/v1/lessons/{$lesson->id}/checkpoints/{$question->id}/answer", ['answer' => false])
        ->assertOk()
        ->assertJsonPath('correct', false)
        ->assertJsonPath('explanation', 'بايثون تُنفَّذ سطراً بسطر عبر المفسّر.');
});

it('rejects checkpoints whose questions belong to another course', function () {
    [$instructor, , $lesson] = interactiveLesson();
    $foreign = Course::factory()->create(['instructor_id' => $instructor->id]);
    $foreignQuestion = Question::query()->create([
        'course_id' => $foreign->id, 'type' => 'true_false', 'body' => 'غريب',
        'choices' => null, 'correct' => true, 'points' => 1,
    ]);

    test()->actingAs($instructor)
        ->patchJson("/api/v1/catalog/lessons/{$lesson->id}", [
            'checkpoints' => [['at_seconds' => 10, 'question_id' => $foreignQuestion->id]],
        ])
        ->assertStatus(422);
});

it('blocks checkpoint access for non-enrolled users', function () {
    [$instructor, $course, $lesson] = interactiveLesson();
    $question = Question::query()->create([
        'course_id' => $course->id, 'type' => 'true_false', 'body' => 'س',
        'choices' => null, 'correct' => true, 'points' => 1,
    ]);
    test()->actingAs($instructor)
        ->patchJson("/api/v1/catalog/lessons/{$lesson->id}", [
            'checkpoints' => [['at_seconds' => 5, 'question_id' => $question->id]],
        ])->assertOk();

    $outsider = userWithRole(Role::Student);
    test()->actingAs($outsider)
        ->getJson("/api/v1/lessons/{$lesson->id}/checkpoints")
        ->assertForbidden();
});
