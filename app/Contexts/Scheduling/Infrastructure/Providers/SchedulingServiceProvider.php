<?php

declare(strict_types=1);

namespace App\Contexts\Scheduling\Infrastructure\Providers;

use App\Contexts\Scheduling\Domain\Meeting\MeetingProviderType;
use App\Contexts\Scheduling\Infrastructure\Meeting\GoogleMeetProvider;
use App\Contexts\Scheduling\Infrastructure\Meeting\ManualMeetingProvider;
use App\Contexts\Scheduling\Infrastructure\Meeting\MeetingProviderResolver;
use App\Contexts\Scheduling\Infrastructure\Meeting\ZoomMeetingProvider;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the Scheduling context: registers the live-session meeting
 * providers (manual link, Zoom, Google Meet) behind a single resolver.
 */
final class SchedulingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MeetingProviderResolver::class, function (): MeetingProviderResolver {
            return new MeetingProviderResolver([
                MeetingProviderType::Manual->value => new ManualMeetingProvider,
                MeetingProviderType::Zoom->value => new ZoomMeetingProvider(config('scheduling.zoom.account_token')),
                MeetingProviderType::GoogleMeet->value => new GoogleMeetProvider(config('scheduling.google_meet.access_token')),
            ]);
        });
    }
}
