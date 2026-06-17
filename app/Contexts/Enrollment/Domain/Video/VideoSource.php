<?php

declare(strict_types=1);

namespace App\Contexts\Enrollment\Domain\Video;

use App\Contexts\Catalog\Domain\Course\VideoProvider;

/**
 * Strategy for turning a stored video identifier into a playable target.
 * Each provider (Bunny, storage, YouTube) implements this; the application
 * depends only on the interface so providers are interchangeable per lesson
 * (PRD §5.ب).
 */
interface VideoSource
{
    public function provider(): VideoProvider;

    /**
     * Build a playable target for the given video reference.
     */
    public function playback(VideoRef $ref): PlaybackTarget;
}
