<?php

declare(strict_types=1);

namespace App\Contexts\Catalog\Domain\Course;

/**
 * The kinds of lesson content (PRD §5.ب): a managed-service video, a
 * written article, an image, a downloadable file (e.g. PDF), a scheduled
 * live session, or an activity (a reflect-and-share discussion prompt — #3).
 */
enum LessonType: string
{
    case Video = 'video';
    case Article = 'article';
    case Image = 'image';
    case File = 'file';
    case Live = 'live';
    // نشاط «تفكير ومشاركة»: نصّ توجيهي (سؤال/مهمة) يتأمّله المتعلّم ويناقشه.
    case Activity = 'activity';

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
