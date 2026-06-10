<?php

declare(strict_types=1);

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Identity\Domain\Role;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('exposes aggregate platform counters publicly', function () {
    $instructor = userWithRole(Role::Instructor);
    $student = userWithRole(Role::Student);

    Course::factory()->for($instructor, 'instructor')->published()->create(['pricing_type' => 'free', 'price_minor' => 0]);
    Course::factory()->for($instructor, 'instructor')->create(); // draft — not counted

    // One real enrollment through the API (free course → active immediately).
    $course = Course::query()->where('status', 'published')->firstOrFail();
    Sanctum::actingAs($student);
    $this->postJson("/api/v1/catalog/courses/{$course->slug}/enroll")->assertCreated();

    $this->getJson('/api/v1/platform/stats')
        ->assertOk()
        ->assertJsonPath('data.courses', 1)
        ->assertJsonPath('data.learners', 1)
        ->assertJsonPath('data.instructors', 1)
        ->assertJsonPath('data.enrollments', 1);
});
