<?php

declare(strict_types=1);

namespace App\Contexts\Notification\Domain;

/**
 * Delivery channels the preference centre can toggle (PRD §5.ط). WhatsApp
 * and push are deferred to a later phase; the strings map onto Laravel
 * notification channels.
 */
enum NotificationChannel: string
{
    case Database = 'database';
    case Mail = 'mail';
    case Sms = 'sms';
    case WhatsApp = 'whatsapp';
    case Push = 'push';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
