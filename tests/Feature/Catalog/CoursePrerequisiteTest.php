<?php

declare(strict_types=1);

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Enrollment\Domain\EnrollmentStatus;
use App\Contexts\Enrollment\Infrastructure\Persistence\Enrollment;
use App\Contexts\Identity\Domain\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/** Mark a course as completed for a user (mirrors CourseCompletionService end state). */
function complete(User $user, Course $course): void
{
    Enrollment::query()->create([
        'user_id' => $user->id,
        'course_id' => $course->id,
        'status' => EnrollmentStatus::Completed,
        'progress_percent' => 100,
    ]);
}

// ---- enrolment gating (the heart of E1) ----

it('blocks enrolment with 422 when a prerequisite is not completed', function () {
    $prereq = Course::factory()->published()->create(['title' => 'الأساسيات']);
    $course = Course::factory()->published()->create();
    $course->prerequisites()->attach($prereq->id);

    Sanctum::actingAs(User::factory()->create());

    $this->postJson("/api/v1/catalog/courses/{$course->slug}/enroll")
        ->assertStatus(422)
        ->assertJsonPath('prerequisites.0.slug', $prereq->slug)
        ->assertJsonPath('prerequisites.0.title', 'الأساسيات');

    $this->assertDatabaseMissing('enrollments', ['course_id' => $course->id, 'status' => 'active']);
});

it('allows enrolment once the prerequisite is completed', function () {
    $prereq = Course::factory()->published()->create();
    $course = Course::factory()->published()->create();
    $course->prerequisites()->attach($prereq->id);

    $user = User::factory()->create();
    complete($user, $prereq);
    Sanctum::actingAs($user);

    $this->postJson("/api/v1/catalog/courses/{$course->slug}/enroll")
        ->assertCreated()
        ->assertJsonPath('data.status', EnrollmentStatus::Active->value);
});

it('allows enrolment when the course has no prerequisites', function () {
    $course = Course::factory()->published()->create();
    Sanctum::actingAs(User::factory()->create());

    $this->postJson("/api/v1/catalog/courses/{$course->slug}/enroll")->assertCreated();
});

it('lets course staff bypass prerequisites', function () {
    $prereq = Course::factory()->published()->create();
    $instructor = User::factory()->create();
    $instructor->assignRole(Role::Instructor->value);
    $course = Course::factory()->published()->create(['instructor_id' => $instructor->id]);
    $course->prerequisites()->attach($prereq->id);

    Sanctum::actingAs($instructor); // the owner — has not completed the prerequisite

    $this->postJson("/api/v1/catalog/courses/{$course->slug}/enroll")->assertCreated();
});

it('only counts a Completed (not Active) prerequisite enrolment', function () {
    $prereq = Course::factory()->published()->create();
    $course = Course::factory()->published()->create();
    $course->prerequisites()->attach($prereq->id);

    $user = User::factory()->create();
    Enrollment::query()->create([
        'user_id' => $user->id, 'course_id' => $prereq->id,
        'status' => EnrollmentStatus::Active, 'progress_percent' => 40,
    ]);
    Sanctum::actingAs($user);

    $this->postJson("/api/v1/catalog/courses/{$course->slug}/enroll")->assertStatus(422);
});

// ---- authoring (add / remove) ----

it('lets the owner add a published prerequisite', function () {
    $instructor = User::factory()->create();
    $instructor->assignRole(Role::Instructor->value);
    $course = Course::factory()->create(['instructor_id' => $instructor->id]);
    $prereq = Course::factory()->published()->create();

    Sanctum::actingAs($instructor);
    $this->postJson("/api/v1/catalog/courses/{$course->slug}/prerequisites", [
        'prerequisite_course_id' => $prereq->id,
    ])->assertCreated()->assertJsonPath('data.0.id', $prereq->id);

    $this->assertDatabaseHas('course_prerequisites', [
        'course_id' => $course->id, 'prerequisite_course_id' => $prereq->id,
    ]);
});

it('rejects a self-referential prerequisite with 422', function () {
    $instructor = User::factory()->create();
    $instructor->assignRole(Role::Instructor->value);
    $course = Course::factory()->published()->create(['instructor_id' => $instructor->id]);

    Sanctum::actingAs($instructor);
    $this->postJson("/api/v1/catalog/courses/{$course->slug}/prerequisites", [
        'prerequisite_course_id' => $course->id,
    ])->assertStatus(422)->assertJsonValidationErrors('prerequisite_course_id');
});

it('rejects an unpublished prerequisite with 422', function () {
    $instructor = User::factory()->create();
    $instructor->assignRole(Role::Instructor->value);
    $course = Course::factory()->create(['instructor_id' => $instructor->id]);
    $draft = Course::factory()->create(); // draft

    Sanctum::actingAs($instructor);
    $this->postJson("/api/v1/catalog/courses/{$course->slug}/prerequisites", [
        'prerequisite_course_id' => $draft->id,
    ])->assertStatus(422);
});

it('forbids a non-owner from adding a prerequisite', function () {
    $course = Course::factory()->create(['instructor_id' => User::factory()->create()->id]);
    $prereq = Course::factory()->published()->create();

    $other = User::factory()->create();
    $other->assignRole(Role::Instructor->value);
    Sanctum::actingAs($other);

    $this->postJson("/api/v1/catalog/courses/{$course->slug}/prerequisites", [
        'prerequisite_course_id' => $prereq->id,
    ])->assertForbidden();
});

it('removes a prerequisite (idempotent 204)', function () {
    $instructor = User::factory()->create();
    $instructor->assignRole(Role::Instructor->value);
    $course = Course::factory()->create(['instructor_id' => $instructor->id]);
    $prereq = Course::factory()->published()->create();
    $course->prerequisites()->attach($prereq->id);

    Sanctum::actingAs($instructor);
    $this->deleteJson("/api/v1/catalog/courses/{$course->slug}/prerequisites/{$prereq->id}")->assertNoContent();
    $this->assertDatabaseMissing('course_prerequisites', [
        'course_id' => $course->id, 'prerequisite_course_id' => $prereq->id,
    ]);
    // idempotent — second delete still 204
    $this->deleteJson("/api/v1/catalog/courses/{$course->slug}/prerequisites/{$prereq->id}")->assertNoContent();
});

// ---- public display ----

it('exposes published prerequisites on the public course page', function () {
    $prereq = Course::factory()->published()->create(['title' => 'مقدّمة']);
    $course = Course::factory()->published()->create();
    $course->prerequisites()->attach($prereq->id);

    $this->getJson("/api/v1/catalog/courses/{$course->slug}")
        ->assertOk()
        ->assertJsonPath('data.prerequisites.0.title', 'مقدّمة')
        ->assertJsonPath('data.prerequisites.0.slug', $prereq->slug);
});
