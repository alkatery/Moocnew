<?php

declare(strict_types=1);

namespace App\Contexts\Notification\Infrastructure\Notifications;

use App\Contexts\Notification\Application\NotificationPreferences;
use App\Contexts\Notification\Domain\NotificationChannel;
use App\Contexts\Notification\Domain\NotificationType;
use Illuminate\Notifications\Notification;

/**
 * Base class for the platform's notifications. Subclasses declare their
 * type; delivery across all channels is then filtered through the user's
 * preference centre (PRD §5.ط), so an opted-out channel is never used. A
 * subclass may narrow the candidate set by overriding {@see candidateChannels()}.
 */
abstract class PreferenceAwareNotification extends Notification
{
    abstract public function type(): NotificationType;

    /**
     * The channels this notification may use before preferences apply.
     * Defaults to every supported channel.
     *
     * @return list<string>
     */
    protected function candidateChannels(): array
    {
        return NotificationChannel::values();
    }

    /**
     * @return list<string>
     */
    public function via(mixed $notifiable): array
    {
        return app(NotificationPreferences::class)
            ->enabledChannels($notifiable, $this->type(), $this->candidateChannels());
    }
}
