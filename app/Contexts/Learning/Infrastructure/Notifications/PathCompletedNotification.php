<?php

declare(strict_types=1);

namespace App\Contexts\Learning\Infrastructure\Notifications;

use App\Contexts\Notification\Domain\NotificationChannel;
use App\Contexts\Notification\Domain\NotificationType;
use App\Contexts\Notification\Infrastructure\Notifications\PreferenceAwareNotification;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Sent when a learner finishes every course in a learning path; their path
 * certificate has just been issued.
 */
final class PathCompletedNotification extends PreferenceAwareNotification
{
    public function __construct(
        private readonly string $pathTitle,
    ) {}

    public function type(): NotificationType
    {
        return NotificationType::PathCompleted;
    }

    /**
     * @return list<string>
     */
    protected function candidateChannels(): array
    {
        return NotificationChannel::values();
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('مبارك إتمام المسار التخصصي')
            ->line("أتممت المسار التخصصي: {$this->pathTitle} بالكامل.")
            ->line('صدرت شهادة المسار وأصبحت متاحة في حسابك.');
    }

    public function toSms(mixed $notifiable): string
    {
        return "مبارك! أتممت المسار التخصصي: {$this->pathTitle}. شهادة المسار جاهزة.";
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        return [
            'type' => $this->type()->value,
            'path_title' => $this->pathTitle,
        ];
    }
}
