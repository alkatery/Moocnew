<?php

declare(strict_types=1);

namespace App\Contexts\Enrollment\Domain\Events;

/**
 * Raised when an enrollment becomes active (immediate free enrollment).
 * The Notification context listens to confirm the enrollment to the
 * learner, keeping the contexts decoupled (PRD §5.ج, §5.ط).
 */
final readonly class EnrollmentActivated
{
    public function __construct(
        public int $enrollmentId,
        public int $userId,
        public int $courseId,
    ) {}
}
