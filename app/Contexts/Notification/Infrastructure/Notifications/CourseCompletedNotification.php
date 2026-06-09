<?php

declare(strict_types=1);

namespace App\Contexts\Notification\Infrastructure\Notifications;

use App\Contexts\Notification\Domain\NotificationChannel;
use App\Contexts\Notification\Domain\NotificationType;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Sent when a learner completes a course (PRD §5.ح, §5.ط).
 */
final class CourseCompletedNotification extends PreferenceAwareNotification
{
    public function __construct(
        private readonly string $courseTitle,
    ) {}

    public function type(): NotificationType
    {
        return NotificationType::CourseCompleted;
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
            ->subject('مبارك إتمام الدورة')
            ->line("لقد أتممت دورة: {$this->courseTitle}.")
            ->line('أصبحت شهادتك جاهزة في حسابك.');
    }

    public function toSms(mixed $notifiable): string
    {
        return "مبارك! أتممت دورة: {$this->courseTitle}. شهادتك جاهزة.";
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        return [
            'type' => $this->type()->value,
            'course_title' => $this->courseTitle,
        ];
    }
}
