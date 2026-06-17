<?php

declare(strict_types=1);

namespace App\Contexts\Enrollment\Infrastructure\Video;

use App\Contexts\Catalog\Domain\Course\VideoProvider;
use App\Contexts\Enrollment\Domain\Video\PlaybackTarget;
use App\Contexts\Enrollment\Domain\Video\VideoRef;
use App\Contexts\Enrollment\Domain\Video\VideoSource;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\URL;

/**
 * Delivers a video stored on our own S3-compatible disk (e.g. Cloudflare
 * R2) through a short-lived Laravel signed route. This keeps the object key
 * private and prevents direct download, while working with any disk — no
 * managed video service required.
 */
final class StorageVideoSource implements VideoSource
{
    public function __construct(
        private readonly int $ttl,
    ) {}

    public function provider(): VideoProvider
    {
        return VideoProvider::Storage;
    }

    public function playback(VideoRef $ref): PlaybackTarget
    {
        $expires = Date::now()->addSeconds($this->ttl);

        $url = URL::temporarySignedRoute(
            'api.media.stream',
            $expires,
            ['lesson' => $ref->lessonId],
        );

        return new PlaybackTarget(VideoProvider::Storage, PlaybackTarget::KIND_SIGNED_URL, $url, $expires);
    }
}
