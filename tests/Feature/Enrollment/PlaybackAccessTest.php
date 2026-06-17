<?php

declare(strict_types=1);

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Catalog\Infrastructure\Persistence\Lesson;
use App\Contexts\Enrollment\Application\EnrollmentService;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

function lessonOn(Course $course, array $attributes): Lesson
{
    $section = $course->sections()->create(['title' => 'قسم', 'position' => 1]);

    return $section->lessons()->create(array_merge([
        'title' => 'درس',
        'type' => 'video',
        'position' => 1,
    ], $attributes));
}

it('gives an enrolled learner a signed Bunny playback URL', function () {
    config()->set('video.bunny', ['library_id' => '99', 'token_key' => 'k', 'embed_host' => 'iframe.mediadelivery.net']);

    $course = Course::factory()->published()->create();
    $lesson = lessonOn($course, ['video_provider' => 'bunny', 'video_id' => 'guid1', 'video_status' => 'ready']);

    $user = User::factory()->create();
    app(EnrollmentService::class)->enroll($user, $course);
    Sanctum::actingAs($user);

    $this->getJson("/api/v1/lessons/{$lesson->id}/playback")
        ->assertOk()
        ->assertJsonPath('playback.provider', 'bunny')
        ->assertJsonPath('playback.kind', 'signed_url');
});

it('forbids playback for a learner who is not enrolled', function () {
    $course = Course::factory()->published()->create();
    $lesson = lessonOn($course, ['video_provider' => 'youtube', 'video_id' => 'yt', 'video_status' => 'ready']);

    Sanctum::actingAs(User::factory()->create()); // not enrolled

    $this->getJson("/api/v1/lessons/{$lesson->id}/playback")->assertForbidden();
});

it('allows a free-preview lesson without enrollment', function () {
    $course = Course::factory()->published()->create();
    $lesson = lessonOn($course, [
        'video_provider' => 'youtube',
        'video_id' => 'yt',
        'video_status' => 'ready',
        'is_free_preview' => true,
    ]);

    Sanctum::actingAs(User::factory()->create()); // not enrolled, but preview

    $this->getJson("/api/v1/lessons/{$lesson->id}/playback")
        ->assertOk()
        ->assertJsonPath('playback.provider', 'youtube')
        ->assertJsonPath('playback.kind', 'embed');
});

it('returns 409 while a Bunny video is still processing', function () {
    $course = Course::factory()->published()->create();
    $lesson = lessonOn($course, ['video_provider' => 'bunny', 'video_id' => 'guid1', 'video_status' => 'processing']);

    $user = User::factory()->create();
    app(EnrollmentService::class)->enroll($user, $course);
    Sanctum::actingAs($user);

    $this->getJson("/api/v1/lessons/{$lesson->id}/playback")->assertStatus(409);
});

it('gives an enrolled learner a signed storage URL pointing at the media stream route', function () {
    $course = Course::factory()->published()->create();
    $lesson = lessonOn($course, ['video_provider' => 'storage', 'video_id' => 'videos/a.mp4', 'video_status' => 'ready']);

    $user = User::factory()->create();
    app(EnrollmentService::class)->enroll($user, $course);
    Sanctum::actingAs($user);

    $response = $this->getJson("/api/v1/lessons/{$lesson->id}/playback")->assertOk();

    expect($response->json('playback.kind'))->toBe('signed_url');
    expect($response->json('playback.url'))->toContain("/media/stream/{$lesson->id}");
    expect($response->json('playback.url'))->toContain('signature=');
});

it('lets the course instructor preview a lesson without enrolling', function () {
    $instructor = User::factory()->create();
    $course = Course::factory()->for($instructor, 'instructor')->create(); // draft, owned
    $lesson = lessonOn($course, ['video_provider' => 'youtube', 'video_id' => 'yt', 'video_status' => 'ready']);

    Sanctum::actingAs($instructor);

    $this->getJson("/api/v1/lessons/{$lesson->id}/playback")->assertOk();
});
