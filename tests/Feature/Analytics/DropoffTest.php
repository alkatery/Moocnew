<?php

declare(strict_types=1);

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Enrollment\Application\EnrollmentService;
use App\Contexts\Enrollment\Application\ProgressService;
use App\Contexts\Enrollment\Infrastructure\Persistence\Enrollment;
use App\Contexts\Identity\Domain\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

it('reports the lesson completion funnel showing where learners drop off', function () {
    $instructor = userWithRole(Role::Instructor);
    $course = Course::factory()->published()->for($instructor, 'instructor')->create();
    $section = $course->sections()->create(['title' => 'قسم', 'position' => 1]);
    $l1 = $section->lessons()->create(['title' => 'الدرس 1', 'type' => 'article', 'position' => 1]);
    $l2 = $section->lessons()->create(['title' => 'الدرس 2', 'type' => 'article', 'position' => 2]);

    // Two learners complete lesson 1; only one completes lesson 2.
    $progress = app(ProgressService::class);
    foreach (range(1, 2) as $i) {
        $learner = User::factory()->create();
        app(EnrollmentService::class)->enroll($learner, $course);
        $enrollment = Enrollment::query()->where('user_id', $learner->id)->firstOrFail();
        $progress->record($enrollment, $l1, completed: true);
        if ($i === 1) {
            $progress->record($enrollment, $l2, completed: true);
        }
    }

    Sanctum::actingAs($instructor);
    $response = $this->getJson("/api/v1/analytics/courses/{$course->slug}/dropoff")->assertOk();

    expect($response->json('data.enrollments_total'))->toBe(2);
    expect($response->json('data.funnel.0.completed_count'))->toBe(2); // lesson 1
    expect($response->json('data.funnel.1.completed_count'))->toBe(1); // lesson 2 (drop-off)
});

it('forbids an unrelated user from viewing course dropoff', function () {
    $course = Course::factory()->published()->create();
    Sanctum::actingAs(userWithRole(Role::Student));

    $this->getJson("/api/v1/analytics/courses/{$course->slug}/dropoff")->assertForbidden();
});
