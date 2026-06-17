<?php

declare(strict_types=1);

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Identity\Domain\Role;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('creates an activity (reflect-and-share) lesson type', function () {
    $owner = userWithRole(Role::Instructor);
    $course = Course::factory()->for($owner, 'instructor')->create();
    $section = $course->sections()->create(['title' => 'وحدة الحديث', 'position' => 1]);

    Sanctum::actingAs($owner);

    $this->postJson("/api/v1/catalog/sections/{$section->id}/lessons", [
        'title' => 'تأمّل في معنى الحديث',
        'type' => 'activity',
        'content' => 'شارك فهمك للحديث مع زملائك في نقاش الدورة.',
        'position' => 1,
    ])->assertCreated()->assertJsonPath('data.type', 'activity');

    $this->assertDatabaseHas('lessons', ['section_id' => $section->id, 'type' => 'activity']);
});
