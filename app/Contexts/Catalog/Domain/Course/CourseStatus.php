<?php

declare(strict_types=1);

namespace App\Contexts\Catalog\Domain\Course;

/**
 * Publishing lifecycle of a course (PRD §4, §6). An instructor drafts a
 * course and submits it for review; a supervisor publishes it or sends it
 * back. The allowed transitions are encoded here so the workflow has one
 * authoritative definition.
 */
enum CourseStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Published = 'published';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::PendingReview],
            self::PendingReview => [self::Published, self::Draft],
            self::Published => [self::Draft],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isPublished(): bool
    {
        return $this === self::Published;
    }
}
