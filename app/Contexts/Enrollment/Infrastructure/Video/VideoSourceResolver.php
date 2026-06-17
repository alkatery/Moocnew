<?php

declare(strict_types=1);

namespace App\Contexts\Enrollment\Infrastructure\Video;

use App\Contexts\Catalog\Domain\Course\VideoProvider;
use App\Contexts\Enrollment\Domain\Video\VideoSource;
use InvalidArgumentException;

/**
 * Resolves the {@see VideoSource} for a given provider. The map is injected
 * so the set of supported providers is configured in one place (the service
 * provider) and easily extended.
 */
final class VideoSourceResolver
{
    /**
     * @param  array<string, VideoSource>  $sources  keyed by VideoProvider value
     */
    public function __construct(
        private readonly array $sources,
    ) {}

    public function for(VideoProvider $provider): VideoSource
    {
        return $this->sources[$provider->value]
            ?? throw new InvalidArgumentException("No video source registered for provider [{$provider->value}].");
    }
}
