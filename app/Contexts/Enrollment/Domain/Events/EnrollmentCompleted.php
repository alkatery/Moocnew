<?php

declare(strict_types=1);

namespace App\Contexts\Enrollment\Domain\Events;

/**
 * Raised when a learner finishes every lesson in a course and the
 * enrollment transitions to completed. The Certification context listens
 * for this to issue a certificate, keeping the two contexts decoupled
 * (PRD §5.ح).
 */
final readonly class EnrollmentCompleted
{
    public function __construct(
        public int $enrollmentId,
        public int $userId,
        public int $courseId,
    ) {}
}
