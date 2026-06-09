<?php

declare(strict_types=1);

namespace App\Contexts\Notification\Infrastructure\Channels;

use App\Contexts\Notification\Domain\PushSender;
use Illuminate\Notifications\Notification;

/**
 * Delivers a push notification. A notification provides a payload through
 * `toPush()` or, failing that, its database `toArray()` data.
 */
final class PushChannel
{
    public function __construct(private readonly PushSender $sender) {}

    public function send(mixed $notifiable, Notification $notification): void
    {
        $to = $notifiable->routeNotificationFor('push', $notification) ?? (string) $notifiable->getKey();
        if (empty($to)) {
            return;
        }

        $payload = match (true) {
            method_exists($notification, 'toPush') => (array) $notification->toPush($notifiable),
            method_exists($notification, 'toArray') => (array) $notification->toArray($notifiable),
            default => [],
        };

        $title = (string) ($payload['title'] ?? config('app.name', 'MOOC'));

        $this->sender->send((string) $to, $title, $payload);
    }
}
