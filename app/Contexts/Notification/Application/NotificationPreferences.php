<?php

declare(strict_types=1);

namespace App\Contexts\Notification\Application;

use App\Contexts\Notification\Domain\NotificationChannel;
use App\Contexts\Notification\Domain\NotificationType;
use App\Contexts\Notification\Infrastructure\Persistence\NotificationPreference;
use App\Models\User;

/**
 * The preference centre (PRD §5.ط). Channels are opt-out: a (type, channel)
 * pair is enabled unless the user has stored a row disabling it.
 */
final class NotificationPreferences
{
    public function isEnabled(User $user, NotificationType $type, NotificationChannel $channel): bool
    {
        $preference = NotificationPreference::query()
            ->where('user_id', $user->getKey())
            ->where('type', $type->value)
            ->where('channel', $channel->value)
            ->first();

        return $preference === null ? true : (bool) $preference->enabled;
    }

    /**
     * Filter a notification's candidate channels down to those the user has
     * left enabled.
     *
     * @param  list<string>  $candidates
     * @return list<string>
     */
    public function enabledChannels(User $user, NotificationType $type, array $candidates): array
    {
        return array_values(array_filter($candidates, function (string $candidate) use ($user, $type): bool {
            $channel = NotificationChannel::tryFrom($candidate);

            return $channel === null ? true : $this->isEnabled($user, $type, $channel);
        }));
    }

    public function set(User $user, NotificationType $type, NotificationChannel $channel, bool $enabled): void
    {
        NotificationPreference::query()->updateOrCreate(
            ['user_id' => $user->getKey(), 'type' => $type->value, 'channel' => $channel->value],
            ['enabled' => $enabled],
        );
    }

    /**
     * The full matrix of types × channels with their current enabled state.
     *
     * @return list<array{type: string, channel: string, enabled: bool}>
     */
    public function matrix(User $user): array
    {
        $rows = [];

        foreach (NotificationType::cases() as $type) {
            foreach (NotificationChannel::cases() as $channel) {
                $rows[] = [
                    'type' => $type->value,
                    'channel' => $channel->value,
                    'enabled' => $this->isEnabled($user, $type, $channel),
                ];
            }
        }

        return $rows;
    }
}
