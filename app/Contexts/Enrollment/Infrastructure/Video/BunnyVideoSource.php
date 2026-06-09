<?php

declare(strict_types=1);

namespace App\Contexts\Enrollment\Infrastructure\Video;

use App\Contexts\Catalog\Domain\Course\VideoProvider;
use App\Contexts\Enrollment\Domain\Video\PlaybackTarget;
use App\Contexts\Enrollment\Domain\Video\VideoRef;
use App\Contexts\Enrollment\Domain\Video\VideoSource;
use Illuminate\Support\Facades\Date;
use RuntimeException;

/**
 * Bunny Stream playback via token authentication. The embed token is the
 * SHA-256 of (authentication key + video id + expiry), matching Bunny's
 * documented scheme, producing a short-lived signed embed URL.
 */
final class BunnyVideoSource implements VideoSource
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly array $config,
        private readonly int $ttl,
    ) {}

    public function provider(): VideoProvider
    {
        return VideoProvider::Bunny;
    }

    public function playback(VideoRef $ref): PlaybackTarget
    {
        $libraryId = $this->config['library_id'] ?? null;
        $tokenKey = $this->config['token_key'] ?? null;
        $host = $this->config['embed_host'] ?? 'iframe.mediadelivery.net';

        if (empty($libraryId) || empty($tokenKey)) {
            throw new RuntimeException('Bunny Stream is not configured (library id / token key).');
        }

        $expires = Date::now()->addSeconds($this->ttl);
        $expiry = $expires->getTimestamp();

        $token = hash('sha256', $tokenKey.$ref->videoId.$expiry);

        $url = sprintf(
            'https://%s/embed/%s/%s?token=%s&expires=%d',
            $host,
            $libraryId,
            $ref->videoId,
            $token,
            $expiry,
        );

        return new PlaybackTarget(VideoProvider::Bunny, PlaybackTarget::KIND_SIGNED_URL, $url, $expires);
    }
}
