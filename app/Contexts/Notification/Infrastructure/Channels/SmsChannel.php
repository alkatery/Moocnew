<?php

declare(strict_types=1);

namespace App\Contexts\Notification\Infrastructure\Channels;

use App\Contexts\Notification\Domain\SmsSender;
use Illuminate\Notifications\Notification;

/**
 * Laravel notification channel that delivers via the configured
 * {@see SmsSender}. A notification opts in by implementing `toSms()`.
 */
final class SmsChannel
{
    public function __construct(
        private readonly SmsSender $sender,
    ) {}

    public function send(mixed $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toSms')) {
            return;
        }

        $to = $notifiable->routeNotificationFor('sms', $notification)
            ?? ($notifiable->phone ?? null);

        if (empty($to)) {
            return;
        }

        $message = (string) $notification->toSms($notifiable);

        if ($message !== '') {
            $this->sender->send((string) $to, $message);
        }
    }
}
