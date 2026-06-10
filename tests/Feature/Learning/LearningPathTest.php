<?php

declare(strict_types=1);

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Certification\Infrastructure\Persistence\Certificate;
use App\Contexts\Identity\Domain\Role;
use App\Contexts\Learning\Infrastructure\Notifications\PathCompletedNotification;
use App\Contexts\Learning\Infrastructure\Persistence\LearningPath;
use App\Contexts\Learning\Infrastructure\Persistence\LearningPathItem;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/**
 * A published free course with a single article lesson; returns
 * [course, lesson].
 */
function pathCourseWithLesson(): array
{
    $course = Course::factory()->published()->create(['pricing_type' => 'free', 'price_minor' => 0]);
    $section = $course->sections()->create(['title' => 'قسم', 'position' => 1]);
    $lesson = $section->lessons()->create([
        'title' => 'درس', 'type' => 'article', 'content' => 'محتوى', 'position' => 1,
    ]);

    return [$course, $lesson];
}

/** Complete the given course for the currently authenticated learner. */
function completePathCourse(Course $course, int $lessonId): void
{
    test()->postJson("/api/v1/catalog/courses/{$course->slug}/enroll");
    test()->postJson("/api/v1/lessons/{$lessonId}/progress", ['completed' => true])->assertOk();
}

it('lets a supervisor create a path, define ordered items and publish it', function () {
    [$c1] = pathCourseWithLesson();
    [$c2] = pathCourseWithLesson();

    Sanctum::actingAs(userWithRole(Role::Supervisor));

    $slug = $this->postJson('/api/v1/learning/paths', [
        'title' => 'مسار تطوير الويب',
        'summary' => 'من الأساسيات حتى الاحتراف.',
        'published' => true,
    ])->assertCreated()->json('data.slug');

    $this->putJson("/api/v1/learning/paths/{$slug}/items", [
        'items' => [
            ['course_id' => $c1->id, 'level' => 1, 'position' => 1],
            ['course_id' => $c2->id, 'level' => 2, 'position' => 1],
        ],
    ])->assertOk()->assertJsonCount(2, 'data');

    // Public listing sees the published path with its counts.
    $this->getJson('/api/v1/learning/paths')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.courses_count', 2)
        ->assertJsonPath('data.0.levels_count', 2);
});

it('hides draft paths from the public and forbids students from authoring', function () {
    LearningPath::factory()->create();

    $this->getJson('/api/v1/learning/paths')->assertOk()->assertJsonCount(0, 'data');

    Sanctum::actingAs(userWithRole(Role::Student));
    $this->postJson('/api/v1/learning/paths', ['title' => 'محاولة'])->assertForbidden();
});

it('unlocks path courses strictly in order', function () {
    [$c1, $l1] = pathCourseWithLesson();
    [$c2] = pathCourseWithLesson();

    $path = LearningPath::factory()->published()->create();
    LearningPathItem::query()->create(['learning_path_id' => $path->id, 'course_id' => $c1->id, 'level' => 1, 'position' => 1]);
    LearningPathItem::query()->create(['learning_path_id' => $path->id, 'course_id' => $c2->id, 'level' => 2, 'position' => 1]);

    Sanctum::actingAs(userWithRole(Role::Student));

    // Initially: first unlocked, second locked.
    $this->getJson("/api/v1/learning/paths/{$path->slug}")
        ->assertOk()
        ->assertJsonPath('data.levels.0.items.0.state', 'unlocked')
        ->assertJsonPath('data.levels.1.items.0.state', 'locked');

    // The second course is rejected while the first is incomplete.
    $this->postJson("/api/v1/learning/paths/{$path->slug}/courses/{$c2->slug}/enroll")
        ->assertUnprocessable();

    // The first enrolls fine; completing it unlocks the second.
    $this->postJson("/api/v1/learning/paths/{$path->slug}/courses/{$c1->slug}/enroll")->assertCreated();
    $this->postJson("/api/v1/lessons/{$l1->id}/progress", ['completed' => true])->assertOk();

    $this->getJson("/api/v1/learning/paths/{$path->slug}")
        ->assertOk()
        ->assertJsonPath('data.levels.0.items.0.state', 'completed')
        ->assertJsonPath('data.levels.1.items.0.state', 'unlocked');

    $this->postJson("/api/v1/learning/paths/{$path->slug}/courses/{$c2->slug}/enroll")->assertCreated();
});

it('completes the path and issues a path certificate when every course is done', function () {
    Notification::fake();

    [$c1, $l1] = pathCourseWithLesson();
    [$c2, $l2] = pathCourseWithLesson();

    $path = LearningPath::factory()->published()->create();
    LearningPathItem::query()->create(['learning_path_id' => $path->id, 'course_id' => $c1->id, 'level' => 1, 'position' => 1]);
    LearningPathItem::query()->create(['learning_path_id' => $path->id, 'course_id' => $c2->id, 'level' => 1, 'position' => 2]);

    $student = userWithRole(Role::Student);
    Sanctum::actingAs($student);

    $this->postJson("/api/v1/learning/paths/{$path->slug}/enroll")->assertCreated();

    completePathCourse($c1, $l1->id);
    completePathCourse($c2, $l2->id);

    $this->assertDatabaseHas('path_enrollments', [
        'user_id' => $student->id,
        'learning_path_id' => $path->id,
        'status' => 'completed',
    ]);

    $this->assertDatabaseHas('certificates', [
        'user_id' => $student->id,
        'learning_path_id' => $path->id,
    ]);

    Notification::assertSentTo($student, PathCompletedNotification::class);

    // The public verify endpoint attests the path certificate.
    $uuid = Certificate::query()
        ->where('learning_path_id', $path->id)->value('verification_uuid');

    $this->getJson("/api/v1/certificates/verify/{$uuid}")
        ->assertOk()
        ->assertJsonPath('valid', true)
        ->assertJsonPath('subject_type', 'learning_path')
        ->assertJsonPath('course_title', $path->title);

    // My-paths reflects 100% progress.
    $this->getJson('/api/v1/learning/my/paths')
        ->assertOk()
        ->assertJsonPath('data.0.status', 'completed')
        ->assertJsonPath('data.0.progress.percent', 100);
});
