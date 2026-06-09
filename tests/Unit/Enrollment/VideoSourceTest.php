<?php

declare(strict_types=1);

use App\Contexts\Catalog\Domain\Course\VideoProvider;
use App\Contexts\Enrollment\Domain\Video\PlaybackTarget;
use App\Contexts\Enrollment\Domain\Video\VideoRef;
use App\Contexts\Enrollment\Infrastructure\Video\BunnyVideoSource;
use App\Contexts\Enrollment\Infrastructure\Video\YouTubeVideoSource;
use Illuminate\Support\Carbon;

afterEach(fn () => Carbon::setTestNow());

it('builds a Bunny embed URL with a SHA-256 token matching the documented scheme', function () {
    Carbon::setTestNow('2026-06-01 00:00:00');

    $source = new BunnyVideoSource([
        'library_id' => '12345',
        'token_key' => 'secret-key',
        'embed_host' => 'iframe.mediadelivery.net',
    ], ttl: 3600);

    $target = $source->playback(new VideoRef(VideoProvider::Bunny, 'guid-abc', lessonId: 7));

    $expiry = Carbon::now()->addSeconds(3600)->getTimestamp();
    $expectedToken = hash('sha256', 'secret-key'.'guid-abc'.$expiry);

    expect($target->kind)->toBe(PlaybackTarget::KIND_SIGNED_URL);
    expect($target->url)->toContain('/embed/12345/guid-abc');
    expect($target->url)->toContain("token={$expectedToken}");
    expect($target->url)->toContain("expires={$expiry}");
    expect($target->expiresAt?->getTimestamp())->toBe($expiry);
});

it('throws when Bunny is not configured', function () {
    $source = new BunnyVideoSource([], ttl: 3600);

    $source->playback(new VideoRef(VideoProvider::Bunny, 'guid', 1));
})->throws(RuntimeException::class);

it('builds a YouTube embed URL', function () {
    $source = new YouTubeVideoSource('www.youtube.com');

    $target = $source->playback(new VideoRef(VideoProvider::YouTube, 'yt-xyz', 1));

    expect($target->kind)->toBe(PlaybackTarget::KIND_EMBED);
    expect($target->url)->toBe('https://www.youtube.com/embed/yt-xyz');
    expect($target->expiresAt)->toBeNull();
});
