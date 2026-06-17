<?php

declare(strict_types=1);

namespace App\Contexts\Notification\Infrastructure\Providers;

use App\Contexts\Enrollment\Domain\Events\EnrollmentActivated;
use App\Contexts\Enrollment\Domain\Events\EnrollmentCompleted;
use App\Contexts\Notification\Application\SendCourseCompletion;
use App\Contexts\Notification\Application\SendEnrollmentConfirmation;
use App\Contexts\Notification\Domain\PushSender;
use App\Contexts\Notification\Domain\SmsSender;
use App\Contexts\Notification\Domain\WhatsAppSender;
use App\Contexts\Notification\Infrastructure\Channels\PushChannel;
use App\Contexts\Notification\Infrastructure\Channels\SmsChannel;
use App\Contexts\Notification\Infrastructure\Channels\WhatsAppChannel;
use App\Contexts\Notification\Infrastructure\Sms\LogPushSender;
use App\Contexts\Notification\Infrastructure\Sms\LogSmsSender;
use App\Contexts\Notification\Infrastructure\Sms\LogWhatsAppSender;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

/**
 * Wires the Notification context: the SMS/WhatsApp/Push gateways, their
 * custom notification channels, and the listeners that turn enrollment
 * events into learner notifications (PRD §5.ط).
 */
final class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SmsSender::class, fn ($app): SmsSender => new LogSmsSender(
            $app->make(LoggerInterface::class),
        ));
        $this->app->bind(WhatsAppSender::class, fn ($app): WhatsAppSender => new LogWhatsAppSender(
            $app->make(LoggerInterface::class),
        ));
        $this->app->bind(PushSender::class, fn ($app): PushSender => new LogPushSender(
            $app->make(LoggerInterface::class),
        ));
    }

    public function boot(): void
    {
        Notification::extend('sms', fn ($app): SmsChannel => $app->make(SmsChannel::class));
        Notification::extend('whatsapp', fn ($app): WhatsAppChannel => $app->make(WhatsAppChannel::class));
        Notification::extend('push', fn ($app): PushChannel => $app->make(PushChannel::class));

        Event::listen(EnrollmentActivated::class, SendEnrollmentConfirmation::class);
        Event::listen(EnrollmentCompleted::class, SendCourseCompletion::class);
    }
}
