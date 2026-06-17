<?php

declare(strict_types=1);

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Catalog\Infrastructure\Persistence\Lesson;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    config()->set('video.bunny.webhook_secret', 'whsec');
});

function bunnyLesson(string $guid, string $status = 'processing'): Lesson
{
    $course = Course::factory()->published()->create();
    $section = $course->sections()->create(['title' => 'قسم', 'position' => 1]);

    return $section->lessons()->create([
        'title' => 'درس',
        'type' => 'video',
        'video_provider' => 'bunny',
        'video_id' => $guid,
        'video_status' => $status,
        'position' => 1,
    ]);
}

/**
 * POST a Bunny webhook with a correctly computed HMAC signature.
 */
function postBunnyWebhook(array $payload, ?string $signature = null): TestResponse
{
    $body = json_encode($payload);
    $signature ??= hash_hmac('sha256', $body, 'whsec');

    return test()->call(
        'POST',
        '/api/v1/webhooks/video/bunny',
        [], [], [],
        [
            'HTTP_X_BUNNY_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ],
        $body,
    );
}

it('marks a lesson video ready on a valid finished webhook', function () {
    $lesson = bunnyLesson('guid-1');

    postBunnyWebhook(['VideoGuid' => 'guid-1', 'Status' => 4])->assertOk();

    expect($lesson->fresh()->video_status->value)->toBe('ready');
});

it('rejects a webhook with an invalid signature', function () {
    bunnyLesson('guid-1');

    postBunnyWebhook(['VideoGuid' => 'guid-1', 'Status' => 4], signature: 'wrong')
        ->assertStatus(401);
});

it('processes a duplicate delivery at most once (idempotency)', function () {
    $lesson = bunnyLesson('guid-1');

    postBunnyWebhook(['VideoGuid' => 'guid-1', 'Status' => 4])->assertOk();
    postBunnyWebhook(['VideoGuid' => 'guid-1', 'Status' => 4])
        ->assertOk()
        ->assertJsonPath('status', 'duplicate');

    expect($lesson->fresh()->video_status->value)->toBe('ready');
    $this->assertDatabaseCount('webhook_events', 1);
});

it('rejects a malformed payload', function () {
    postBunnyWebhook(['nope' => true])->assertStatus(422);
});
