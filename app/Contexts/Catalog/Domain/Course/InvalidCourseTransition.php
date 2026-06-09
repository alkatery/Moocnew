<?php

declare(strict_types=1);

namespace App\Contexts\Catalog\Domain\Course;

use DomainException;

/**
 * Raised when a course is asked to move between two statuses that the
 * lifecycle does not allow (e.g. publishing a draft without review).
 */
final class InvalidCourseTransition extends DomainException
{
    public static function between(CourseStatus $from, CourseStatus $to): self
    {
        return new self("لا يمكن نقل الدورة من حالة «{$from->value}» إلى «{$to->value}».");
    }
}
