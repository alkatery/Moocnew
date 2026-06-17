<?php

declare(strict_types=1);

namespace App\Contexts\Enrollment\Domain\Video;

use App\Contexts\Catalog\Domain\Course\VideoProvider;
use Illuminate\Support\Carbon;

/**
 * The resolved, ready-to-play reference for a lesson's video: either a
 * short-lived signed URL (Bunny / storage) or an embed URL (YouTube).
 */
final readonly class PlaybackTarget
{
    public function __construct(
        public VideoProvider $provider,
        public string $kind,        // 'signed_url' | 'embed'
        public string $url,
        public ?Carbon $expiresAt = null,
    ) {}

    public const KIND_SIGNED_URL = 'signed_url';

    public const KIND_EMBED = 'embed';

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider->value,
            'kind' => $this->kind,
            'url' => $this->url,
            'expires_at' => $this->expiresAt?->toIso8601String(),
        ];
    }
}
