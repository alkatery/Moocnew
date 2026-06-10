<?php

declare(strict_types=1);

use App\Contexts\Catalog\Domain\Course\CourseStatus;
use App\Contexts\Catalog\Domain\Course\PricingType;
use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Identity\Domain\Role;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('lets an instructor create a draft course they own', function () {
    $instructor = userWithRole(Role::Instructor);
    Sanctum::actingAs($instructor);

    $response = $this->postJson('/api/v1/catalog/courses', [
        'title' => 'مدخل إلى علوم الحاسب',
        'pricing_type' => PricingType::Free->value,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', CourseStatus::Draft->value)
        ->assertJsonPath('data.pricing_type', PricingType::Free->value);

    $course = Course::query()->firstOrFail();
    expect($course->instructor_id)->toBe($instructor->id);
    expect($course->slug)->not->toBe('');
});

it('forces a free course to have a zero price', function () {
    Sanctum::actingAs(userWithRole(Role::Instructor));

    $this->postJson('/api/v1/catalog/courses', [
        'title' => 'دورة مجانية',
        'pricing_type' => PricingType::Free->value,
        'price_minor' => 5000,
    ])->assertCreated()->assertJsonPath('data.price_minor', 0);
});

it('stores the price in minor units for a paid course', function () {
    Sanctum::actingAs(userWithRole(Role::Instructor));

    $this->postJson('/api/v1/catalog/courses', [
        'title' => 'دورة مدفوعة',
        'pricing_type' => PricingType::OneTime->value,
        'price_minor' => 29900,
    ])->assertCreated()->assertJsonPath('data.price_minor', 29900);
});

it('forbids a student from creating a course', function () {
    Sanctum::actingAs(userWithRole(Role::Student));

    $this->postJson('/api/v1/catalog/courses', [
        'title' => 'محاولة غير مصرّح بها',
        'pricing_type' => PricingType::Free->value,
    ])->assertForbidden();
});

it('lets an instructor update their own course but not another instructor’s', function () {
    $owner = userWithRole(Role::Instructor);
    $other = userWithRole(Role::Instructor);

    $course = Course::factory()->for($owner, 'instructor')->create();

    Sanctum::actingAs($other);
    $this->patchJson("/api/v1/catalog/courses/{$course->slug}", ['title' => 'عنوان مسروق'])
        ->assertForbidden();

    Sanctum::actingAs($owner);
    $this->patchJson("/api/v1/catalog/courses/{$course->slug}", ['title' => 'عنوان محدّث'])
        ->assertOk()
        ->assertJsonPath('data.title', 'عنوان محدّث');
});

it('lets the owner build sections and lessons', function () {
    $owner = userWithRole(Role::Instructor);
    $course = Course::factory()->for($owner, 'instructor')->create();
    Sanctum::actingAs($owner);

    $section = $this->postJson("/api/v1/catalog/courses/{$course->slug}/sections", [
        'title' => 'القسم الأول',
    ])->assertCreated()->json('data.id');

    $this->postJson("/api/v1/catalog/sections/{$section}/lessons", [
        'title' => 'الدرس الأول',
        'type' => 'video',
        'video_provider' => 'bunny',
        'video_id' => 'vid_123',
    ])->assertCreated()
        ->assertJsonPath('data.type', 'video')
        ->assertJsonPath('data.video_status', 'processing');
});

it('forbids a non-owner from adding sections', function () {
    $owner = userWithRole(Role::Instructor);
    $other = userWithRole(Role::Instructor);
    $course = Course::factory()->for($owner, 'instructor')->create();

    Sanctum::actingAs($other);
    $this->postJson("/api/v1/catalog/courses/{$course->slug}/sections", [
        'title' => 'قسم دخيل',
    ])->assertForbidden();
});

it('lets the owner soft-delete their course', function () {
    $owner = userWithRole(Role::Instructor);
    $course = Course::factory()->for($owner, 'instructor')->create();
    Sanctum::actingAs($owner);

    $this->deleteJson("/api/v1/catalog/courses/{$course->slug}")->assertNoContent();
    $this->assertSoftDeleted('courses', ['id' => $course->id]);
});

it('lists only the current instructor’s own courses in the studio', function () {
    $me = userWithRole(Role::Instructor);
    $other = userWithRole(Role::Instructor);

    Course::factory()->for($me, 'instructor')->create();          // draft, mine
    Course::factory()->for($me, 'instructor')->published()->create();
    Course::factory()->for($other, 'instructor')->create();       // not mine

    Sanctum::actingAs($me);

    $this->getJson('/api/v1/catalog/mine')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('serves a draft course to its authenticated owner but 404s for guests (studio contract)', function () {
    $owner = userWithRole(Role::Instructor);
    $course = Course::factory()->for($owner, 'instructor')->create(['status' => CourseStatus::Draft]);

    // Guest (the bug): a draft must not leak.
    $this->getJson("/api/v1/catalog/courses/{$course->slug}")->assertNotFound();

    // Authenticated owner: the studio page must be able to load its draft.
    Sanctum::actingAs($owner);
    $this->getJson("/api/v1/catalog/courses/{$course->slug}")
        ->assertOk()
        ->assertJsonPath('data.slug', $course->slug);
});
