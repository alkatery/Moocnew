<?php

declare(strict_types=1);

namespace App\Contexts\Catalog\Domain\Course;

/**
 * Processing state of a lesson's video on the managed video service
 * (PRD §5.ب). Driven by the provider's webhook: `none` until a video is
 * attached, `processing` while transcoding to ABR renditions, then
 * `ready` for signed playback.
 */
enum VideoStatus: string
{
    case None = 'none';
    case Processing = 'processing';
    case Ready = 'ready';

    public function isPlayable(): bool
    {
        return $this === self::Ready;
    }
}
