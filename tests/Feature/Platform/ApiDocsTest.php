<?php

declare(strict_types=1);

use App\Contexts\Identity\Domain\Role;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

it('serves the OpenAPI document to an authorized admin', function () {
    Sanctum::actingAs(userWithRole(Role::SuperAdmin));

    $this->getJson('/docs/api.json')
        ->assertOk()
        ->assertJsonPath('openapi', '3.1.0')
        ->assertJsonPath('info.title', 'MOOC Platform');
});

it('forbids a student from viewing the API docs', function () {
    Sanctum::actingAs(userWithRole(Role::Student));

    $this->get('/docs/api.json')->assertForbidden();
});
