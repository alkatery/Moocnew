<?php

declare(strict_types=1);

use App\Contexts\Catalog\Domain\Course\CourseStatus;
use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Identity\Domain\Role;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('lets an instructor submit a draft for review', function () {
    $owner = userWithRole(Role::Instructor);
    $course = Course::factory()->for($owner, 'instructor')->create();
    Sanctum::actingAs($owner);

    $this->postJson("/api/v1/catalog/courses/{$course->slug}/submit")
        ->assertOk()
        ->assertJsonPath('data.status', CourseStatus::PendingReview->value);
});

it('lets a supervisor publish a course under review and records it', function () {
    $course = Course::factory()->pendingReview()->create();
    Sanctum::actingAs(userWithRole(Role::Supervisor));

    $this->postJson("/api/v1/catalog/courses/{$course->slug}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', CourseStatus::Published->value);

    expect($course->fresh()->published_at)->not->toBeNull();
    $this->assertDatabaseHas('activity_logs', ['event' => 'course.published']);
});

it('lets a supervisor reject a course back to draft', function () {
    $course = Course::factory()->pendingReview()->create();
    Sanctum::actingAs(userWithRole(Role::Supervisor));

    $this->postJson("/api/v1/catalog/courses/{$course->slug}/reject")
        ->assertOk()
        ->assertJsonPath('data.status', CourseStatus::Draft->value);
});

it('rejects an invalid transition (publishing a draft directly) with 422', function () {
    $course = Course::factory()->create(); // draft
    Sanctum::actingAs(userWithRole(Role::Supervisor));

    $this->postJson("/api/v1/catalog/courses/{$course->slug}/approve")
        ->assertStatus(422);

    expect($course->fresh()->status)->toBe(CourseStatus::Draft);
});

it('forbids an instructor from approving courses', function () {
    $course = Course::factory()->pendingReview()->create();
    Sanctum::actingAs(userWithRole(Role::Instructor));

    $this->postJson("/api/v1/catalog/courses/{$course->slug}/approve")
        ->assertForbidden();
});
