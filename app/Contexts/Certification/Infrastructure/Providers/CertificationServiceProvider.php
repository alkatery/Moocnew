<?php

declare(strict_types=1);

namespace App\Contexts\Certification\Infrastructure\Providers;

use App\Contexts\Certification\Application\IssueCertificateOnCompletion;
use App\Contexts\Enrollment\Domain\Events\EnrollmentCompleted;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the Certification context: issue a certificate whenever an
 * enrollment is completed.
 */
final class CertificationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(EnrollmentCompleted::class, IssueCertificateOnCompletion::class);
    }
}
