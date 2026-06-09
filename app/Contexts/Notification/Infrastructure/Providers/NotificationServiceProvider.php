<?php

declare(strict_types=1);

namespace App\Contexts\Notification\Infrastructure\Providers;

use App\Contexts\Enrollment\Domain\Events\EnrollmentActivated;
use App\Contexts\Enrollment\Domain\Events\EnrollmentCompleted;
use App\Contexts\Notification\Application\SendCourseCompletion;
use App\Contexts\Notification\Application\SendEnrollmentConfirmation;
use App\Contexts\Notification\Domain\SmsSender;
use App\Contexts\Notification\Infrastructure\Channels\SmsChannel;
use App\Contexts\Notification\Infrastructure\Sms\LogSmsSender;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

/**
 * Wires the Notification context: the SMS gateway, the custom `sms`
 * notification channel, and the listeners that turn enrollment events into
 * learner notifications.
 */
final class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SmsSender::class, fn ($app): SmsSender => new LogSmsSender(
            $app->make(LoggerInterface::class),
        ));
    }

    public function boot(): void
    {
        Notification::extend('sms', fn ($app): SmsChannel => $app->make(SmsChannel::class));

        Event::listen(EnrollmentActivated::class, SendEnrollmentConfirmation::class);
        Event::listen(EnrollmentCompleted::class, SendCourseCompletion::class);
    }
}
