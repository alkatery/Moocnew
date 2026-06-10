<?php

use App\Contexts\Assessment\Infrastructure\Providers\AssessmentServiceProvider;
use App\Contexts\Catalog\Infrastructure\Providers\CatalogServiceProvider;
use App\Contexts\Certification\Infrastructure\Providers\CertificationServiceProvider;
use App\Contexts\Commerce\Infrastructure\Providers\CommerceServiceProvider;
use App\Contexts\Engagement\Infrastructure\Providers\EngagementServiceProvider;
use App\Contexts\Enrollment\Infrastructure\Providers\EnrollmentServiceProvider;
use App\Contexts\Identity\Infrastructure\Providers\IdentityServiceProvider;
use App\Contexts\Learning\Infrastructure\Providers\LearningServiceProvider;
use App\Contexts\Notification\Infrastructure\Providers\NotificationServiceProvider;
use App\Contexts\Platform\Infrastructure\Providers\PlatformServiceProvider;
use App\Contexts\Scheduling\Infrastructure\Providers\SchedulingServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    PlatformServiceProvider::class,
    IdentityServiceProvider::class,
    CatalogServiceProvider::class,
    EnrollmentServiceProvider::class,
    AssessmentServiceProvider::class,
    CertificationServiceProvider::class,
    NotificationServiceProvider::class,
    CommerceServiceProvider::class,
    SchedulingServiceProvider::class,
    LearningServiceProvider::class,
    EngagementServiceProvider::class,
];
