<?php

declare(strict_types=1);

namespace App\Contexts\Learning\Infrastructure\Notifications;

use App\Contexts\Notification\Domain\NotificationChannel;
use App\Contexts\Notification\Domain\NotificationType;
use App\Contexts\Notification\Infrastructure\Notifications\PreferenceAwareNotification;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Congratulates the learner when their personal study plan is fully done
 * (this also ends the plan's recurring reminders).
 */
final class StudyPlanCompletedNotification extends PreferenceAwareNotification
{
    public function __construct(
        private readonly string $planTitle,
    ) {}

    public function type(): NotificationType
    {
        return NotificationType::StudyPlanReminder;
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
            ->subject('مبارك! أنجزت خطتك الدراسية')
            ->line("أكملت كل دورات خطتك «{$this->planTitle}». عمل رائع — واصل التعلّم!");
    }

    public function toSms(mixed $notifiable): string
    {
        return "مبارك! أنجزت خطتك الدراسية «{$this->planTitle}» بالكامل.";
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        return [
            'type' => $this->type()->value,
            'plan_title' => $this->planTitle,
            'completed' => true,
        ];
    }
}
