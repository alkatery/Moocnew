<?php

declare(strict_types=1);

use App\Contexts\Catalog\Domain\Course\PricingType;
use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Enrollment\Domain\EnrollmentStatus;
use App\Contexts\Platform\Domain\Settings\SettingKey;
use App\Contexts\Platform\Domain\Settings\SettingsRepository;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('enrolls a learner in a free course as active immediately', function () {
    $course = Course::factory()->published()->create();
    Sanctum::actingAs(User::factory()->create());

    $this->postJson("/api/v1/catalog/courses/{$course->slug}/enroll")
        ->assertCreated()
        ->assertJsonPath('data.status', EnrollmentStatus::Active->value)
        ->assertJsonPath('data.course_id', $course->id);
});

it('is idempotent — enrolling twice returns the same enrollment', function () {
    $course = Course::factory()->published()->create();
    Sanctum::actingAs($user = User::factory()->create());

    $first = $this->postJson("/api/v1/catalog/courses/{$course->slug}/enroll")->assertCreated()->json('data.id');
    $this->postJson("/api/v1/catalog/courses/{$course->slug}/enroll")
        ->assertOk()
        ->assertJsonPath('data.id', $first);

    expect($user->fresh()->id)->not->toBeNull();
    $this->assertDatabaseCount('enrollments', 1);
});

it('cannot enroll in a course that is not published', function () {
    $course = Course::factory()->create(); // draft
    Sanctum::actingAs(User::factory()->create());

    $this->postJson("/api/v1/catalog/courses/{$course->slug}/enroll")->assertNotFound();
});

it('creates a pending enrollment for a paid course when payments are enabled', function () {
    app(SettingsRepository::class)->set(SettingKey::PaymentsEnabled, true);

    $course = Course::factory()->published()->create([
        'pricing_type' => PricingType::OneTime,
        'price_minor' => 49900,
    ]);
    Sanctum::actingAs(User::factory()->create());

    $this->postJson("/api/v1/catalog/courses/{$course->slug}/enroll")
        ->assertCreated()
        ->assertJsonPath('data.status', EnrollmentStatus::Pending->value);
});

it('still enrolls directly in a paid course while payments are disabled (free mode)', function () {
    // payments.enabled defaults to false → everything behaves as free.
    $course = Course::factory()->published()->create([
        'pricing_type' => PricingType::OneTime,
        'price_minor' => 49900,
    ]);
    Sanctum::actingAs(User::factory()->create());

    $this->postJson("/api/v1/catalog/courses/{$course->slug}/enroll")
        ->assertCreated()
        ->assertJsonPath('data.status', EnrollmentStatus::Active->value);
});

it('lists the learner’s enrollments', function () {
    $course = Course::factory()->published()->create();
    Sanctum::actingAs($user = User::factory()->create());
    $this->postJson("/api/v1/catalog/courses/{$course->slug}/enroll")->assertCreated();

    $this->getJson('/api/v1/enrollments')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.course_id', $course->id);
});

it('requires authentication to enroll', function () {
    $course = Course::factory()->published()->create();

    $this->postJson("/api/v1/catalog/courses/{$course->slug}/enroll")->assertUnauthorized();
});
