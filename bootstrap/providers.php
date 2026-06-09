<?php

use App\Contexts\Catalog\Infrastructure\Providers\CatalogServiceProvider;
use App\Contexts\Certification\Infrastructure\Providers\CertificationServiceProvider;
use App\Contexts\Commerce\Infrastructure\Providers\CommerceServiceProvider;
use App\Contexts\Enrollment\Infrastructure\Providers\EnrollmentServiceProvider;
use App\Contexts\Identity\Infrastructure\Providers\IdentityServiceProvider;
use App\Contexts\Notification\Infrastructure\Providers\NotificationServiceProvider;
use App\Contexts\Platform\Infrastructure\Providers\PlatformServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    PlatformServiceProvider::class,
    IdentityServiceProvider::class,
    CatalogServiceProvider::class,
    EnrollmentServiceProvider::class,
    CertificationServiceProvider::class,
    NotificationServiceProvider::class,
    CommerceServiceProvider::class,
];
