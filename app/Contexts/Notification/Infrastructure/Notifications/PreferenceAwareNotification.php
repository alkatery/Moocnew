<?php

declare(strict_types=1);

namespace App\Contexts\Notification\Infrastructure\Notifications;

use App\Contexts\Notification\Application\NotificationPreferences;
use App\Contexts\Notification\Domain\NotificationType;
use Illuminate\Notifications\Notification;

/**
 * Base class for the platform's notifications. Subclasses declare their
 * type and candidate channels; delivery is then filtered through the
 * user's preference centre (PRD §5.ط), so an opted-out channel is never
 * used.
 */
abstract class PreferenceAwareNotification extends Notification
{
    abstract public function type(): NotificationType;

    /**
     * The channels this notification would use before preferences apply.
     *
     * @return list<string>
     */
    abstract protected function candidateChannels(): array;

    /**
     * @return list<string>
     */
    public function via(mixed $notifiable): array
    {
        return app(NotificationPreferences::class)
            ->enabledChannels($notifiable, $this->type(), $this->candidateChannels());
    }
}
