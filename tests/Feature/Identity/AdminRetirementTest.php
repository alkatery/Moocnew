<?php

declare(strict_types=1);

use App\Contexts\Identity\Domain\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function actingAsAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole(Role::SuperAdmin->value);
    Sanctum::actingAs($admin);

    return $admin;
}

it('anonymises a user on admin retirement and preserves the (now anonymous) record', function () {
    actingAsAdmin();
    $target = User::factory()->create(['name' => 'فاطمة', 'email' => 'fatima@example.com']);
    $target->createToken('web');

    $this->postJson("/api/v1/admin/users/{$target->id}/retire")->assertNoContent();

    $fresh = User::withTrashed()->find($target->id);
    expect($fresh->trashed())->toBeTrue()
        ->and($fresh->name)->toBe('مستخدم محذوف')
        ->and($fresh->email)->not->toBe('fatima@example.com')
        ->and($fresh->phone)->toBeNull()
        ->and($fresh->tokens()->count())->toBe(0);

    // Audit trail records the retirement with the admin as causer.
    $this->assertDatabaseHas('activity_logs', [
        'event' => 'user.retired',
        'subject_id' => $target->id,
    ]);
});

it('forbids retiring a super admin', function () {
    actingAsAdmin();
    $other = User::factory()->create();
    $other->assignRole(Role::SuperAdmin->value);

    $this->postJson("/api/v1/admin/users/{$other->id}/retire")->assertForbidden();

    expect(User::find($other->id))->not->toBeNull();
});

it('forbids retiring yourself', function () {
    $admin = actingAsAdmin();

    $this->postJson("/api/v1/admin/users/{$admin->id}/retire")->assertStatus(422);
});

it('forbids a non-admin from retiring anyone', function () {
    $student = User::factory()->create();
    $student->assignRole(Role::Student->value);
    Sanctum::actingAs($student);

    $target = User::factory()->create();

    $this->postJson("/api/v1/admin/users/{$target->id}/retire")->assertForbidden();
});
