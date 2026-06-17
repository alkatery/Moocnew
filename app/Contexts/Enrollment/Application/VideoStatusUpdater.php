<?php

declare(strict_types=1);

namespace App\Contexts\Enrollment\Application;

use App\Contexts\Catalog\Domain\Course\VideoProvider;
use App\Contexts\Catalog\Domain\Course\VideoStatus;
use App\Contexts\Catalog\Infrastructure\Persistence\Lesson;

/**
 * Applies a video-processing status reported by the managed provider
 * (Bunny) to the matching lessons (PRD §5.ب: video_status processing →
 * ready, driven by webhook).
 */
final class VideoStatusUpdater
{
    /**
     * Bunny Stream encoding status codes. 4 (Finished) means the renditions
     * are ready; 5/6 indicate failure.
     */
    public function fromBunny(string $videoGuid, int $bunnyStatus): int
    {
        $status = match (true) {
            $bunnyStatus === 4 => VideoStatus::Ready,
            $bunnyStatus >= 5 => VideoStatus::None,
            default => VideoStatus::Processing,
        };

        return Lesson::query()
            ->where('video_provider', VideoProvider::Bunny->value)
            ->where('video_id', $videoGuid)
            ->update(['video_status' => $status->value]);
    }
}
