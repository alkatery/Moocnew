<?php

declare(strict_types=1);

namespace App\Contexts\Catalog\Domain\Course;

/**
 * Where a lesson's video lives (PRD §5.ب). Chosen by the instructor when
 * authoring the lesson; the Enrollment context maps each value to a
 * concrete playback strategy.
 */
enum VideoProvider: string
{
    /** Managed service: transcoding, ABR, signed playback, CDN. */
    case Bunny = 'bunny';

    /** Our own S3-compatible storage, delivered via a short-lived signed URL. */
    case Storage = 'storage';

    /** Embedded from YouTube (unlisted); no cryptographic protection. */
    case YouTube = 'youtube';

    /**
     * Whether the provider transcodes asynchronously and therefore reports
     * processing state via a webhook. Storage and YouTube are ready at once.
     */
    public function hasAsyncProcessing(): bool
    {
        return $this === self::Bunny;
    }

    /**
     * The initial video status for a freshly attached video on this provider.
     */
    public function initialStatus(): VideoStatus
    {
        return $this->hasAsyncProcessing() ? VideoStatus::Processing : VideoStatus::Ready;
    }
}
