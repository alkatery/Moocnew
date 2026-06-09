<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Infrastructure\Providers;

use App\Contexts\Platform\Application\FeatureFlags;
use App\Contexts\Platform\Domain\Settings\SettingsRepository;
use App\Contexts\Platform\Infrastructure\Persistence\CachedSettingsRepository;
use App\Contexts\Platform\Infrastructure\Persistence\DatabaseSettingsRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the Platform bounded context into the framework: binds the
 * settings repository contract to its cached-over-database implementation
 * and registers the FeatureFlags service as a singleton.
 */
final class PlatformServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(config_path('platform.php'), 'platform');

        $this->app->singleton(SettingsRepository::class, function (Application $app): SettingsRepository {
            return new CachedSettingsRepository(
                new DatabaseSettingsRepository,
                $app->make('cache.store'),
            );
        });

        $this->app->singleton(FeatureFlags::class, function (Application $app): FeatureFlags {
            return new FeatureFlags($app->make(SettingsRepository::class));
        });
    }
}
