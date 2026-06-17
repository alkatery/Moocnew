<?php

declare(strict_types=1);

namespace App\Contexts\Notification\Infrastructure\Notifications;

use App\Contexts\Notification\Domain\NotificationChannel;
use App\Contexts\Notification\Domain\NotificationType;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Sent when a learner is enrolled and granted access to a course
 * (PRD §5.ج, §5.ط).
 */
final class EnrollmentConfirmedNotification extends PreferenceAwareNotification
{
    public function __construct(
        private readonly string $courseTitle,
    ) {}

    public function type(): NotificationType
    {
        return NotificationType::EnrollmentConfirmed;
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
            ->subject('تم تأكيد التحاقك')
            ->line("تم تسجيلك بنجاح في دورة: {$this->courseTitle}.")
            ->line('نتمنى لك رحلة تعلّم موفّقة.');
    }

    public function toSms(mixed $notifiable): string
    {
        return "تم تأكيد التحاقك في دورة: {$this->courseTitle}.";
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
