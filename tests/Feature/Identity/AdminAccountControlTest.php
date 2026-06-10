<?php

declare(strict_types=1);

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Identity\Domain\Role;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('disables an account, blocks its login, and re-enables it', function () {
    $student = userWithRole(Role::Student);
    $student->update(['email' => 'blocked@demo.test', 'password' => 'secret-pass-12']);
    Sanctum::actingAs(userWithRole(Role::SuperAdmin));

    $this->patchJson("/api/v1/admin/users/{$student->id}/status", ['disabled' => true])->assertOk();
    expect($student->fresh()->isDisabled())->toBeTrue();

    // Disabled users cannot authenticate.
    $this->postJson('/api/v1/auth/login', ['email' => 'blocked@demo.test', 'password' => 'secret-pass-12'])
        ->assertStatus(422)->assertJsonValidationErrors(['email']);

    $this->patchJson("/api/v1/admin/users/{$student->id}/status", ['disabled' => false])->assertOk();
    expect($student->fresh()->isDisabled())->toBeFalse();

    $this->postJson('/api/v1/auth/login', ['email' => 'blocked@demo.test', 'password' => 'secret-pass-12'])
        ->assertOk()->assertJsonStructure(['token']);
});

it('never disables a super admin', function () {
    $other = userWithRole(Role::SuperAdmin);
    Sanctum::actingAs(userWithRole(Role::SuperAdmin));

    $this->patchJson("/api/v1/admin/users/{$other->id}/status", ['disabled' => true])->assertForbidden();
});

it('resets a password and returns it once', function () {
    $student = userWithRole(Role::Student);
    $original = $student->password;
    Sanctum::actingAs(userWithRole(Role::SuperAdmin));

    $password = $this->postJson("/api/v1/admin/users/{$student->id}/reset-password")
        ->assertOk()->json('data.password');

    expect($password)->toBeString();
    expect(Hash::check($password, $student->fresh()->password))->toBeTrue();
    expect($student->fresh()->password)->not->toBe($original);
});

it('soft-deletes a user but not oneself or a super admin', function () {
    $student = userWithRole(Role::Student);
    $admin = userWithRole(Role::SuperAdmin);
    Sanctum::actingAs($admin);

    $this->deleteJson("/api/v1/admin/users/{$admin->id}")->assertStatus(422);
    $this->deleteJson("/api/v1/admin/users/{$student->id}")->assertNoContent();
    $this->assertSoftDeleted('users', ['id' => $student->id]);
});

it('shows an instructor courses and stats to staff', function () {
    $instructor = userWithRole(Role::Instructor);
    Course::factory()->for($instructor, 'instructor')->published()->create();
    Course::factory()->for($instructor, 'instructor')->create(); // draft

    Sanctum::actingAs(userWithRole(Role::SuperAdmin));

    $this->getJson("/api/v1/admin/users/{$instructor->id}/courses")
        ->assertOk()
        ->assertJsonPath('data.stats.courses', 2)
        ->assertJsonPath('data.stats.published', 1);
});

it('transfers a course to another instructor', function () {
    $owner = userWithRole(Role::Instructor);
    $newOwner = userWithRole(Role::Instructor);
    $student = userWithRole(Role::Student);
    $course = Course::factory()->for($owner, 'instructor')->create();

    Sanctum::actingAs(userWithRole(Role::SuperAdmin));

    // Target must be an instructor.
    $this->patchJson("/api/v1/admin/courses/{$course->slug}/instructor", ['instructor_id' => $student->id])
        ->assertStatus(422);

    $this->patchJson("/api/v1/admin/courses/{$course->slug}/instructor", ['instructor_id' => $newOwner->id])
        ->assertOk();

    expect($course->fresh()->instructor_id)->toBe($newOwner->id);
});

it('exposes the audit trail to staff with event filtering', function () {
    $admin = userWithRole(Role::SuperAdmin);
    $student = userWithRole(Role::Student);
    Sanctum::actingAs($admin);

    // Generate an auditable action.
    $this->postJson("/api/v1/admin/users/{$student->id}/reset-password")->assertOk();

    $this->getJson('/api/v1/admin/activity-logs?event=user.password_reset')
        ->assertOk()
        ->assertJsonPath('data.0.event', 'user.password_reset');
});

it('keeps account control away from non-admins', function () {
    $target = userWithRole(Role::Student);
    Sanctum::actingAs(userWithRole(Role::Instructor));

    $this->patchJson("/api/v1/admin/users/{$target->id}/status", ['disabled' => true])->assertForbidden();
    $this->getJson('/api/v1/admin/activity-logs')->assertForbidden();
});
