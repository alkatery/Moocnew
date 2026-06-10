<?php

declare(strict_types=1);

namespace App\Contexts\Enrollment\Domain\Events;

/**
 * Raised when a learner satisfies a course's completion requirements (all
 * lessons done and, when the course is graded, the passing grade met) and
 * the enrollment transitions to completed. The Certification context listens
 * for this to issue a certificate, keeping the two contexts decoupled
 * (PRD §5.ح). `grade` is the learner's final course grade, or null when the
 * course has no graded assessments.
 */
final readonly class EnrollmentCompleted
{
    public function __construct(
        public int $enrollmentId,
        public int $userId,
        public int $courseId,
        public ?int $grade = null,
    ) {}
}
