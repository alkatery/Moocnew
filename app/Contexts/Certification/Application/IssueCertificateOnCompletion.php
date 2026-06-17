<?php

declare(strict_types=1);

namespace App\Contexts\Certification\Application;

use App\Contexts\Enrollment\Domain\Events\EnrollmentCompleted;

/**
 * Issues a certificate when a learner completes a course. Listening to the
 * Enrollment event keeps Certification decoupled from Enrollment internals
 * (PRD §5.ح).
 */
final class IssueCertificateOnCompletion
{
    public function __construct(
        private readonly CertificateService $certificates,
    ) {}

    public function handle(EnrollmentCompleted $event): void
    {
        $this->certificates->issueFor($event->userId, $event->courseId, $event->grade);
    }
}
