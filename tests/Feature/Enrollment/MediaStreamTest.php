<?php

declare(strict_types=1);

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Catalog\Infrastructure\Persistence\Lesson;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    config()->set('video.storage.disk', 'media');
    Storage::fake('media');
});

function storageLesson(string $key): Lesson
{
    $course = Course::factory()->published()->create();
    $section = $course->sections()->create(['title' => 'قسم', 'position' => 1]);

    return $section->lessons()->create([
        'title' => 'درس',
        'type' => 'video',
        'video_provider' => 'storage',
        'video_id' => $key,
        'video_status' => 'ready',
        'position' => 1,
    ]);
}

it('streams the stored file through a valid signed URL', function () {
    Storage::disk('media')->put('videos/a.mp4', 'BINARY-VIDEO-BYTES');
    $lesson = storageLesson('videos/a.mp4');

    $url = URL::temporarySignedRoute('api.media.stream', now()->addMinutes(10), ['lesson' => $lesson->id]);

    $response = $this->get($url);
    $response->assertOk();
    expect($response->streamedContent())->toBe('BINARY-VIDEO-BYTES');
});

it('rejects an unsigned (or tampered) media request', function () {
    $lesson = storageLesson('videos/a.mp4');

    $this->get("/api/v1/media/stream/{$lesson->id}")->assertStatus(403);
});
