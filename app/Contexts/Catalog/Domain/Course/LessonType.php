<?php

declare(strict_types=1);

namespace App\Contexts\Catalog\Domain\Course;

/**
 * The kinds of lesson content (PRD §5.ب): a managed-service video, a
 * written article, an image, a downloadable file (e.g. PDF), or a
 * scheduled live session.
 */
enum LessonType: string
{
    case Video = 'video';
    case Article = 'article';
    case Image = 'image';
    case File = 'file';
    case Live = 'live';

    public function requiresVideo(): bool
    {
        return $this === self::Video;
    }

    /**
     * Types whose content is an uploaded asset (image/document).
     */
    public function usesAsset(): bool
    {
        return $this === self::Image || $this === self::File;
    }
}
