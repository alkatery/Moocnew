<?php

declare(strict_types=1);

use App\Contexts\Identity\Domain\Role;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('lets a super admin view the settings panel', function () {
    Sanctum::actingAs(userWithRole(Role::SuperAdmin));

    $this->getJson('/api/v1/admin/settings')
        ->assertOk()
        ->assertJsonPath('payments_enabled', false);
});

it('forbids a student from viewing the settings panel', function () {
    Sanctum::actingAs(userWithRole(Role::Student));

    $this->getJson('/api/v1/admin/settings')->assertForbidden();
});

it('lets a super admin toggle the payments flag, which the health endpoint reflects', function () {
    Sanctum::actingAs(userWithRole(Role::SuperAdmin));

    $this->patchJson('/api/v1/admin/settings/payments', ['enabled' => true])
        ->assertOk()
        ->assertJsonPath('payments_enabled', true);

    $this->assertDatabaseHas('activity_logs', [
        'event' => 'settings.payments_toggled',
    ]);

    // The flag change is visible to the public feature-discovery endpoint.
    $this->getJson('/api/v1/health')->assertJsonPath('features.payments_enabled', true);
});

it('forbids a student from toggling the payments flag', function () {
    Sanctum::actingAs(userWithRole(Role::Student));

    $this->patchJson('/api/v1/admin/settings/payments', ['enabled' => true])
        ->assertForbidden();
});
