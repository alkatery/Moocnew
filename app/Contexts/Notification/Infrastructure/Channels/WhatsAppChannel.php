<?php

declare(strict_types=1);

namespace App\Contexts\Notification\Infrastructure\Channels;

use App\Contexts\Notification\Domain\WhatsAppSender;
use Illuminate\Notifications\Notification;

/**
 * Delivers via WhatsApp. A notification provides text through `toWhatsApp()`
 * or, failing that, its `toSms()` message.
 */
final class WhatsAppChannel
{
    public function __construct(private readonly WhatsAppSender $sender) {}

    public function send(mixed $notifiable, Notification $notification): void
    {
        $to = $notifiable->routeNotificationFor('whatsapp', $notification) ?? ($notifiable->phone ?? null);
        if (empty($to)) {
            return;
        }

        $message = match (true) {
            method_exists($notification, 'toWhatsApp') => (string) $notification->toWhatsApp($notifiable),
            method_exists($notification, 'toSms') => (string) $notification->toSms($notifiable),
            default => '',
        };

        if ($message !== '') {
            $this->sender->send((string) $to, $message);
        }
    }
}
