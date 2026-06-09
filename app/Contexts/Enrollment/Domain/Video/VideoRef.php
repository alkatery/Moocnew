<?php

declare(strict_types=1);

namespace App\Contexts\Enrollment\Domain\Video;

use App\Contexts\Catalog\Domain\Course\VideoProvider;

/**
 * A provider-agnostic reference to a lesson's video: the provider, the
 * provider-specific id (Bunny GUID, storage object key, or YouTube id),
 * and the owning lesson id (used to build signed delivery routes).
 */
final readonly class VideoRef
{
    public function __construct(
        public VideoProvider $provider,
        public string $videoId,
        public int $lessonId,
    ) {}
}
