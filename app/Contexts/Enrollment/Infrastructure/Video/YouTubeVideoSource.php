<?php

declare(strict_types=1);

namespace App\Contexts\Enrollment\Infrastructure\Video;

use App\Contexts\Catalog\Domain\Course\VideoProvider;
use App\Contexts\Enrollment\Domain\Video\PlaybackTarget;
use App\Contexts\Enrollment\Domain\Video\VideoRef;
use App\Contexts\Enrollment\Domain\Video\VideoSource;

/**
 * YouTube playback: returns an embed URL for the video id. There is no
 * cryptographic protection here — access is gated only by the enrollment
 * check before this URL is revealed (see PRD §5.ب security note).
 */
final class YouTubeVideoSource implements VideoSource
{
    public function __construct(
        private readonly string $embedHost,
    ) {}

    public function provider(): VideoProvider
    {
        return VideoProvider::YouTube;
    }

    public function playback(VideoRef $ref): PlaybackTarget
    {
        $url = sprintf('https://%s/embed/%s', $this->embedHost, $ref->videoId);

        return new PlaybackTarget(VideoProvider::YouTube, PlaybackTarget::KIND_EMBED, $url);
    }
}
