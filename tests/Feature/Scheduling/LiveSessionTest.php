<?php

declare(strict_types=1);

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Enrollment\Application\EnrollmentService;
use App\Contexts\Identity\Domain\Role;
use App\Contexts\Scheduling\Infrastructure\Notifications\LiveSessionReminderNotification;
use App\Contexts\Scheduling\Infrastructure\Persistence\LiveSession;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->instructor = userWithRole(Role::Instructor);
    $this->course = Course::factory()->published()->for($this->instructor, 'instructor')->create();
});

afterEach(fn () => Carbon::setTestNow());

function enrolledLearner(Course $course): User
{
    $user = User::factory()->create();
    app(EnrollmentService::class)->enroll($user, $course);

    return $user;
}

it('schedules a session with a pasted link (manual provider)', function () {
    Sanctum::actingAs($this->instructor);

    $this->postJson("/api/v1/scheduling/courses/{$this->course->slug}/sessions", [
        'title' => 'الحصة الأولى',
        'provider' => 'manual',
        'starts_at' => now()->addDay()->toIso8601String(),
        'join_url' => 'https://meet.example.com/room-1',
    ])->assertCreated()
        ->assertJsonPath('data.provider', 'manual')
        ->assertJsonPath('data.join_url', 'https://meet.example.com/room-1');
});

it('requires a link for the manual provider', function () {
    Sanctum::actingAs($this->instructor);

    $this->postJson("/api/v1/scheduling/courses/{$this->course->slug}/sessions", [
        'title' => 'حصة', 'provider' => 'manual', 'starts_at' => now()->addDay()->toIso8601String(),
    ])->assertStatus(422)->assertJsonValidationErrors('join_url');
});

it('provisions a Zoom meeting via the API', function () {
    config()->set('scheduling.zoom.account_token', 'test-token');
    Http::fake(['api.zoom.us/*' => Http::response(['join_url' => 'https://zoom.us/j/999', 'id' => '999'], 201)]);

    Sanctum::actingAs($this->instructor);

    $this->postJson("/api/v1/scheduling/courses/{$this->course->slug}/sessions", [
        'title' => 'حصة زووم',
        'provider' => 'zoom',
        'starts_at' => now()->addDay()->toIso8601String(),
    ])->assertCreated()
        ->assertJsonPath('data.provider', 'zoom')
        ->assertJsonPath('data.join_url', 'https://zoom.us/j/999');
});

it('forbids a student from scheduling', function () {
    Sanctum::actingAs(userWithRole(Role::Student));

    $this->postJson("/api/v1/scheduling/courses/{$this->course->slug}/sessions", [
        'title' => 'حصة', 'provider' => 'manual', 'starts_at' => now()->addDay()->toIso8601String(),
        'join_url' => 'https://x.example.com',
    ])->assertForbidden();
});

it('books seats concurrency-safely up to capacity', function () {
    $session = LiveSession::query()->create([
        'course_id' => $this->course->id, 'title' => 'حصة', 'provider' => 'manual',
        'join_url' => 'https://x', 'starts_at' => now()->addDay(), 'capacity' => 1,
    ]);

    Sanctum::actingAs(enrolledLearner($this->course));
    $this->postJson("/api/v1/scheduling/sessions/{$session->id}/register")->assertOk()
        ->assertJsonPath('data.seats_remaining', 0);

    // Second learner exceeds the single seat.
    Sanctum::actingAs(enrolledLearner($this->course));
    $this->postJson("/api/v1/scheduling/sessions/{$session->id}/register")->assertStatus(422);
});

it('forbids a non-enrolled user from registering', function () {
    $session = LiveSession::query()->create([
        'course_id' => $this->course->id, 'title' => 'حصة', 'provider' => 'manual',
        'join_url' => 'https://x', 'starts_at' => now()->addDay(),
    ]);

    Sanctum::actingAs(User::factory()->create());
    $this->postJson("/api/v1/scheduling/sessions/{$session->id}/register")->assertForbidden();
});

it('builds a unified calendar with Hijri dates', function () {
    $learner = enrolledLearner($this->course);
    LiveSession::query()->create([
        'course_id' => $this->course->id, 'title' => 'حصة', 'provider' => 'manual',
        'join_url' => 'https://x', 'starts_at' => now()->addDays(2),
    ]);

    Sanctum::actingAs($learner);
    $response = $this->getJson('/api/v1/scheduling/calendar')->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.type'))->toBe('live_session');
    expect($response->json('data.0.hijri'))->not->toBeEmpty();
});

it('dispatches reminders once per session for registered learners', function () {
    Carbon::setTestNow('2026-06-10 09:00:00');
    $session = LiveSession::query()->create([
        'course_id' => $this->course->id, 'title' => 'حصة', 'provider' => 'manual',
        'join_url' => 'https://x', 'starts_at' => now()->addMinutes(20),
    ]);
    $learner = enrolledLearner($this->course);
    $session->registrations()->create(['user_id' => $learner->id]);

    Notification::fake();

    $this->artisan('scheduling:dispatch-reminders')->assertExitCode(0);
    Notification::assertSentTo($learner, LiveSessionReminderNotification::class);
    expect($session->fresh()->reminded_at)->not->toBeNull();

    // Running again does not re-notify.
    Notification::fake();
    $this->artisan('scheduling:dispatch-reminders')->assertExitCode(0);
    Notification::assertNothingSent();
});
