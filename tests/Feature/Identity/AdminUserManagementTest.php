<?php

declare(strict_types=1);

use App\Contexts\Identity\Domain\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('lets an admin onboard an instructor from the dashboard', function () {
    Sanctum::actingAs(userWithRole(Role::SuperAdmin));

    $this->postJson('/api/v1/admin/users', [
        'name' => 'د. ليلى الحربي',
        'email' => 'laila@demo.test',
        'password' => 'secret-pass-12',
        'role' => Role::Instructor->value,
    ])->assertCreated()
        ->assertJsonPath('data.email', 'laila@demo.test')
        ->assertJsonPath('data.roles', [Role::Instructor->value]);

    $user = User::query()->where('email', 'laila@demo.test')->firstOrFail();
    expect($user->hasRole(Role::Instructor->value))->toBeTrue();
});

it('forbids non-admins from managing users', function () {
    $this->getJson('/api/v1/admin/users')->assertUnauthorized();

    Sanctum::actingAs(userWithRole(Role::Supervisor));
    $this->getJson('/api/v1/admin/users')->assertForbidden();
    $this->postJson('/api/v1/admin/users', [
        'name' => 'x', 'email' => 'x@y.test', 'password' => 'secret-pass-12', 'role' => 'instructor',
    ])->assertForbidden();
});

it('rejects a duplicate email and a super_admin role', function () {
    Sanctum::actingAs(userWithRole(Role::SuperAdmin));
    User::factory()->create(['email' => 'taken@demo.test']);

    $this->postJson('/api/v1/admin/users', [
        'name' => 'x', 'email' => 'taken@demo.test', 'password' => 'secret-pass-12', 'role' => 'instructor',
    ])->assertJsonValidationErrors(['email']);

    $this->postJson('/api/v1/admin/users', [
        'name' => 'x', 'email' => 'new@demo.test', 'password' => 'secret-pass-12', 'role' => 'super_admin',
    ])->assertJsonValidationErrors(['role']);
});

it('lists and filters users by role and search term', function () {
    $instructor = userWithRole(Role::Instructor);
    $instructor->update(['name' => 'مدرّس بحثي']);
    userWithRole(Role::Student);

    Sanctum::actingAs(userWithRole(Role::SuperAdmin));

    $this->getJson('/api/v1/admin/users?role=instructor')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.email', $instructor->email);

    $this->getJson('/api/v1/admin/users?q=بحثي')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('changes a user role but never the super admin role', function () {
    $student = userWithRole(Role::Student);
    $admin = userWithRole(Role::SuperAdmin);
    Sanctum::actingAs($admin);

    $this->patchJson("/api/v1/admin/users/{$student->id}/role", ['role' => Role::Instructor->value])
        ->assertOk()
        ->assertJsonPath('data.roles', [Role::Instructor->value]);

    expect($student->fresh()->hasRole(Role::Instructor->value))->toBeTrue();

    $other = userWithRole(Role::SuperAdmin);
    $this->patchJson("/api/v1/admin/users/{$other->id}/role", ['role' => Role::Student->value])
        ->assertForbidden();
});

it('issues a working impersonation token for a learner', function () {
    $student = userWithRole(Role::Student);
    $admin = userWithRole(Role::SuperAdmin);

    // Use a real bearer token (not actingAs) so token auth is exercised end
    // to end and the impersonation token can authenticate independently.
    $adminToken = $admin->createToken('web')->plainTextToken;

    $token = $this->withToken($adminToken)
        ->postJson("/api/v1/admin/users/{$student->id}/impersonate")
        ->assertOk()
        ->assertJsonPath('user.id', $student->id)
        ->json('token');

    expect($token)->toBeString()->not->toBe('');

    // The issued token belongs to the impersonated learner and is marked as
    // an impersonation token created by the admin.
    [$id] = explode('|', $token, 2);
    $accessToken = PersonalAccessToken::query()->findOrFail($id);

    expect($accessToken->tokenable_id)->toBe($student->id);
    expect($accessToken->name)->toBe("impersonation:by:{$admin->id}");
});

it('refuses to impersonate a super admin or oneself', function () {
    $admin = userWithRole(Role::SuperAdmin);
    $otherAdmin = userWithRole(Role::SuperAdmin);
    Sanctum::actingAs($admin);

    $this->postJson("/api/v1/admin/users/{$otherAdmin->id}/impersonate")->assertForbidden();
    $this->postJson("/api/v1/admin/users/{$admin->id}/impersonate")->assertStatus(422);
});

it('forbids non-admins from impersonating', function () {
    $target = userWithRole(Role::Student);
    Sanctum::actingAs(userWithRole(Role::Instructor));

    $this->postJson("/api/v1/admin/users/{$target->id}/impersonate")->assertForbidden();
});
