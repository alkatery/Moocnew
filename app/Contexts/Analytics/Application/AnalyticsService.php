<?php

declare(strict_types=1);

namespace App\Contexts\Analytics\Application;

use App\Contexts\Analytics\Infrastructure\PresenceTracker;
use App\Contexts\Catalog\Domain\Course\CourseStatus;
use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Certification\Infrastructure\Persistence\Certificate;
use App\Contexts\Enrollment\Domain\EnrollmentStatus;
use App\Contexts\Enrollment\Infrastructure\Persistence\Enrollment;
use App\Contexts\Platform\Application\FeatureFlags;
use App\Models\User;

/**
 * Produces the aggregate dashboard figures (PRD §5.ي). Strictly aggregate:
 * counts and totals, never individual behavioural tracking, in line with
 * the PDPL stance taken in the PRD. Commerce metrics surface only when the
 * payments flag is on.
 */
final class AnalyticsService
{
    public function __construct(
        private readonly PresenceTracker $presence,
        private readonly FeatureFlags $features,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        $overview = [
            'users_total' => User::query()->count(),
            'courses_published' => Course::query()->where('status', CourseStatus::Published->value)->count(),
            'enrollments_total' => Enrollment::query()->count(),
            'enrollments_active' => Enrollment::query()->where('status', EnrollmentStatus::Active->value)->count(),
            'enrollments_completed' => Enrollment::query()->where('status', EnrollmentStatus::Completed->value)->count(),
            'certificates_issued' => Certificate::query()->count(),
            'online_now' => $this->presence->onlineCount(),
            'commerce_enabled' => $this->features->paymentsEnabled(),
        ];

        return $overview;
    }
}
