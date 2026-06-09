<?php

declare(strict_types=1);

namespace App\Contexts\Catalog\Domain\Course;

/**
 * The kinds of lesson content (PRD §5.ب): a managed-service video, a
 * written article, a downloadable file, or a scheduled live session.
 */
enum LessonType: string
{
    case Video = 'video';
    case Article = 'article';
    case File = 'file';
    case Live = 'live';

    public function requiresVideo(): bool
    {
        return $this === self::Video;
    }
}
