<?php

declare(strict_types=1);

use App\Contexts\Assessment\Infrastructure\Persistence\Assignment;
use App\Contexts\Assessment\Infrastructure\Persistence\Question;
use App\Contexts\Assessment\Infrastructure\Persistence\Quiz;
use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Catalog\Infrastructure\Persistence\Section;
use App\Contexts\Identity\Domain\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/**
 * @return array{0: User, 1: Course, 2: Section}
 */
function ownedCourseWithUnit(): array
{
    $owner = userWithRole(Role::Instructor);
    $course = Course::factory()->for($owner, 'instructor')->create();
    $section = $course->sections()->create(['title' => 'الوحدة الأولى', 'position' => 1]);

    return [$owner, $course, $section];
}

it('appends a new assignment after existing unit items (unified position)', function () {
    [$owner, $course, $section] = ownedCourseWithUnit();
    $section->lessons()->create(['title' => 'فيديو', 'type' => 'video', 'position' => 1]);
    $section->lessons()->create(['title' => 'مقال', 'type' => 'article', 'position' => 2]);

    Sanctum::actingAs($owner);

    $this->postJson("/api/v1/assessment/courses/{$course->slug}/assignments", [
        'title' => 'واجب الوحدة', 'points' => 100, 'section_id' => $section->id,
    ])->assertCreated()->assertJsonPath('data.position', 3);
});

it('appends a new quiz after existing unit items', function () {
    [$owner, $course, $section] = ownedCourseWithUnit();
    $section->lessons()->create(['title' => 'فيديو', 'type' => 'video', 'position' => 1]);
    $question = Question::query()->create([
        'course_id' => $course->id, 'type' => 'true_false', 'body' => 'صحّ؟',
        'choices' => null, 'correct' => true, 'points' => 1,
    ]);

    Sanctum::actingAs($owner);

    $this->postJson("/api/v1/assessment/courses/{$course->slug}/quizzes", [
        'title' => 'اختبار الوحدة', 'pass_mark' => 50, 'section_id' => $section->id,
        'question_ids' => [$question->id],
    ])->assertCreated()->assertJsonPath('data.position', 2);
});

it('reorders lessons, quizzes and assignments in one unified sequence', function () {
    [$owner, $course, $section] = ownedCourseWithUnit();
    $lesson = $section->lessons()->create(['title' => 'فيديو', 'type' => 'video', 'position' => 1]);
    $quiz = Quiz::query()->create(['course_id' => $course->id, 'section_id' => $section->id, 'title' => 'اختبار', 'position' => 2]);
    $assignment = Assignment::query()->create(['course_id' => $course->id, 'section_id' => $section->id, 'title' => 'واجب', 'position' => 3]);

    Sanctum::actingAs($owner);

    $this->putJson("/api/v1/catalog/sections/{$section->id}/items/order", [
        'items' => [
            ['type' => 'quiz', 'id' => $quiz->id],
            ['type' => 'assignment', 'id' => $assignment->id],
            ['type' => 'lesson', 'id' => $lesson->id],
        ],
    ])->assertOk();

    expect($quiz->fresh()->position)->toBe(1);
    expect($assignment->fresh()->position)->toBe(2);
    expect($lesson->fresh()->position)->toBe(3);
});

it('rejects reordering an item that belongs to another unit (422)', function () {
    [$owner, $course, $section] = ownedCourseWithUnit();
    $other = $course->sections()->create(['title' => 'وحدة أخرى', 'position' => 2]);
    $foreign = $other->lessons()->create(['title' => 'درس غريب', 'type' => 'article', 'position' => 1]);

    Sanctum::actingAs($owner);

    $this->putJson("/api/v1/catalog/sections/{$section->id}/items/order", [
        'items' => [['type' => 'lesson', 'id' => $foreign->id]],
    ])->assertStatus(422);
});

it('forbids a non-staff user from reordering unit items (403)', function () {
    [, , $section] = ownedCourseWithUnit();
    $lesson = $section->lessons()->create(['title' => 'درس', 'type' => 'article', 'position' => 1]);

    Sanctum::actingAs(userWithRole(Role::Student));

    $this->putJson("/api/v1/catalog/sections/{$section->id}/items/order", [
        'items' => [['type' => 'lesson', 'id' => $lesson->id]],
    ])->assertForbidden();
});
