<?php

declare(strict_types=1);

use App\Contexts\Catalog\Infrastructure\Persistence\Category;
use App\Contexts\Identity\Domain\Role;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('exposes the category list publicly', function () {
    Category::query()->create(['name' => 'برمجة', 'slug' => 'programming']);

    $this->getJson('/api/v1/catalog/categories')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'برمجة');
});

it('lets a super admin create a category with a generated slug', function () {
    Sanctum::actingAs(userWithRole(Role::SuperAdmin));

    $this->postJson('/api/v1/catalog/categories', ['name' => 'الذكاء الاصطناعي'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'الذكاء الاصطناعي');

    expect(Category::query()->count())->toBe(1);
    expect(Category::query()->first()->slug)->not->toBe('');
});

it('forbids a student from creating a category', function () {
    Sanctum::actingAs(userWithRole(Role::Student));

    $this->postJson('/api/v1/catalog/categories', ['name' => 'فئة غير مصرّح بها'])
        ->assertForbidden();
});
