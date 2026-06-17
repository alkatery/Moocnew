<?php

declare(strict_types=1);

use App\Contexts\Analytics\Infrastructure\PresenceTracker;
use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Enrollment\Application\EnrollmentService;
use App\Contexts\Identity\Domain\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Redis;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Redis::connection()->del('presence:online'); // isolate presence between tests
});

it('returns the aggregate overview to a supervisor', function () {
    $course = Course::factory()->published()->create();
    $learner = User::factory()->create();
    app(EnrollmentService::class)->enroll($learner, $course);

    Sanctum::actingAs(userWithRole(Role::Supervisor));

    $this->getJson('/api/v1/analytics/overview')
        ->assertOk()
        ->assertJsonPath('data.courses_published', 1)
        ->assertJsonPath('data.enrollments_total', 1)
        ->assertJsonPath('data.enrollments_active', 1)
        ->assertJsonPath('data.commerce_enabled', false);
});

it('forbids a student from viewing analytics', function () {
    Sanctum::actingAs(userWithRole(Role::Student));

    $this->getJson('/api/v1/analytics/overview')->assertForbidden();
});

it('counts online users via heartbeats (aggregate only)', function () {
    Sanctum::actingAs($user = User::factory()->create());

    $this->postJson('/api/v1/presence/heartbeat')
        ->assertOk()
        ->assertJsonPath('online_now', 1);

    // A second distinct user heartbeat raises the count to 2.
    Sanctum::actingAs(User::factory()->create());
    $this->postJson('/api/v1/presence/heartbeat')->assertJsonPath('online_now', 2);
});

it('drops users from the online count once their heartbeat goes stale', function () {
    $tracker = new PresenceTracker(app('redis'), windowSeconds: 0);

    $tracker->heartbeat(1);
    // With a zero-second window the previous heartbeat is already stale.
    expect($tracker->onlineCount())->toBe(0);
});
