<?php

declare(strict_types=1);

namespace App\Contexts\Scheduling\Infrastructure\Notifications;

use App\Contexts\Notification\Domain\NotificationChannel;
use App\Contexts\Notification\Domain\NotificationType;
use App\Contexts\Notification\Infrastructure\Notifications\PreferenceAwareNotification;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Reminds a learner that a live session starts soon (PRD §5.و).
 */
final class LiveSessionReminderNotification extends PreferenceAwareNotification
{
    public function __construct(
        private readonly string $title,
        private readonly string $startsAtLocal,
        private readonly ?string $joinUrl,
    ) {}

    public function type(): NotificationType
    {
        return NotificationType::SessionReminder;
    }

    /** @return list<string> */
    protected function candidateChannels(): array
    {
        return [
            NotificationChannel::Database->value,
            NotificationChannel::Mail->value,
            NotificationChannel::Sms->value,
        ];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('تذكير: حصة مباشرة قريباً')
            ->line("تبدأ حصة «{$this->title}» في {$this->startsAtLocal}.");

        return $this->joinUrl ? $mail->action('الانضمام', $this->joinUrl) : $mail;
    }

    public function toSms(mixed $notifiable): string
    {
        return "تذكير: حصة «{$this->title}» تبدأ {$this->startsAtLocal}.";
    }

    /** @return array<string, mixed> */
    public function toArray(mixed $notifiable): array
    {
        return [
            'type' => $this->type()->value,
            'title' => $this->title,
            'starts_at_local' => $this->startsAtLocal,
            'join_url' => $this->joinUrl,
        ];
    }
}
