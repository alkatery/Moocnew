<?php

use App\Contexts\Identity\Infrastructure\Providers\IdentityServiceProvider;
use App\Contexts\Platform\Infrastructure\Providers\PlatformServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    PlatformServiceProvider::class,
    IdentityServiceProvider::class,
];
