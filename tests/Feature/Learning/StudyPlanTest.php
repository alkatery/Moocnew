<?php

declare(strict_types=1);

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Identity\Domain\Role;
use App\Contexts\Learning\Infrastructure\Notifications\StudyPlanCompletedNotification;
use App\Contexts\Learning\Infrastructure\Notifications\StudyPlanReminderNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/**
 * A published free course with one article lesson; returns [course, lesson].
 */
function planCourseWithLesson(): array
{
    $course = Course::factory()->published()->create(['pricing_type' => 'free', 'price_minor' => 0]);
    $section = $course->sections()->create(['title' => 'قسم', 'position' => 1]);
    $lesson = $section->lessons()->create([
        'title' => 'درس', 'type' => 'article', 'content' => 'محتوى', 'position' => 1,
    ]);

    return [$course, $lesson];
}

it('creates a personal plan from a list of courses with a reminder cadence', function () {
    [$c1] = planCourseWithLesson();
    [$c2] = planCourseWithLesson();

    Sanctum::actingAs(userWithRole(Role::Student));

    $this->postJson('/api/v1/learning/study-plans', [
        'title' => 'خطة الصيف',
        'cadence_days' => 3,
        'course_ids' => [$c1->id, $c2->id],
    ])->assertCreated()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.progress.total', 2)
        ->assertJsonPath('data.progress.completed', 0)
        ->assertJsonPath('data.next_course.id', $c1->id);
});

it('validates the plan input', function () {
    Sanctum::actingAs(userWithRole(Role::Student));

    $this->postJson('/api/v1/learning/study-plans', [
        'title' => 'بدون دورات',
        'cadence_days' => 5, // not an allowed cadence
        'course_ids' => [],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['cadence_days', 'course_ids']);
});

it('keeps plans private to their owner', function () {
    [$c1] = planCourseWithLesson();

    $owner = userWithRole(Role::Student);
    Sanctum::actingAs($owner);
    $planId = $this->postJson('/api/v1/learning/study-plans', [
        'title' => 'خطتي', 'cadence_days' => 1, 'course_ids' => [$c1->id],
    ])->json('data.id');

    Sanctum::actingAs(userWithRole(Role::Student));
    $this->getJson('/api/v1/learning/study-plans')->assertOk()->assertJsonCount(0, 'data');
    $this->patchJson("/api/v1/learning/study-plans/{$planId}", ['title' => 'اختراق'])->assertNotFound();
    $this->deleteJson("/api/v1/learning/study-plans/{$planId}")->assertNotFound();
});

it('tracks progress and closes the plan when every course is completed', function () {
    Notification::fake();

    [$c1, $l1] = planCourseWithLesson();
    [$c2, $l2] = planCourseWithLesson();

    $student = userWithRole(Role::Student);
    Sanctum::actingAs($student);

    $this->postJson('/api/v1/learning/study-plans', [
        'title' => 'خطة الإنجاز', 'cadence_days' => 1, 'course_ids' => [$c1->id, $c2->id],
    ])->assertCreated();

    // Complete the first course → 50%.
    $this->postJson("/api/v1/catalog/courses/{$c1->slug}/enroll");
    $this->postJson("/api/v1/lessons/{$l1->id}/progress", ['completed' => true])->assertOk();

    $this->getJson('/api/v1/learning/study-plans')
        ->assertOk()
        ->assertJsonPath('data.0.progress.percent', 50)
        ->assertJsonPath('data.0.next_course.id', $c2->id);

    // Complete the second → plan auto-closes and congratulates the owner.
    $this->postJson("/api/v1/catalog/courses/{$c2->slug}/enroll");
    $this->postJson("/api/v1/lessons/{$l2->id}/progress", ['completed' => true])->assertOk();

    $this->getJson('/api/v1/learning/study-plans')
        ->assertOk()
        ->assertJsonPath('data.0.status', 'completed')
        ->assertJsonPath('data.0.progress.percent', 100);

    Notification::assertSentTo($student, StudyPlanCompletedNotification::class);
});

it('reminds plan owners on their cadence until the plan is done', function () {
    Notification::fake();

    [$c1] = planCourseWithLesson();
    $student = userWithRole(Role::Student);
    Sanctum::actingAs($student);

    $this->postJson('/api/v1/learning/study-plans', [
        'title' => 'خطة المثابرة', 'cadence_days' => 3, 'course_ids' => [$c1->id],
    ])->assertCreated();

    // Immediately after creation nothing is due.
    $this->artisan('learning:dispatch-plan-reminders')->assertSuccessful();
    Notification::assertNotSentTo($student, StudyPlanReminderNotification::class);

    // After the cadence elapses the nudge goes out — exactly once.
    $this->travel(3)->days();
    $this->artisan('learning:dispatch-plan-reminders')->assertSuccessful();
    $this->artisan('learning:dispatch-plan-reminders')->assertSuccessful();

    Notification::assertSentToTimes($student, StudyPlanReminderNotification::class, 1);
});
