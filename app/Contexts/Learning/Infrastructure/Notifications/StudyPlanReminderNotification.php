<?php

declare(strict_types=1);

namespace App\Contexts\Learning\Infrastructure\Notifications;

use App\Contexts\Notification\Domain\NotificationChannel;
use App\Contexts\Notification\Domain\NotificationType;
use App\Contexts\Notification\Infrastructure\Notifications\PreferenceAwareNotification;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The recurring nudge for a personal study plan: keeps reminding the
 * learner (at their chosen cadence) until the plan is completed.
 */
final class StudyPlanReminderNotification extends PreferenceAwareNotification
{
    public function __construct(
        private readonly string $planTitle,
        private readonly int $percent,
        private readonly int $remaining,
        private readonly ?string $nextCourseTitle,
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
        $mail = (new MailMessage)
            ->subject("تذكير بخطتك الدراسية: {$this->planTitle}")
            ->line("أنجزت {$this->percent}% من خطتك «{$this->planTitle}» وتبقّى {$this->remaining} دورة.");

        if ($this->nextCourseTitle !== null) {
            $mail->line("خطوتك التالية: {$this->nextCourseTitle}. خصص وقتاً اليوم وواصل تقدمك!");
        }

        return $mail;
    }

    public function toSms(mixed $notifiable): string
    {
        $next = $this->nextCourseTitle !== null ? " التالي: {$this->nextCourseTitle}." : '';

        return "تذكير: خطتك «{$this->planTitle}» عند {$this->percent}%.{$next}";
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        return [
            'type' => $this->type()->value,
            'plan_title' => $this->planTitle,
            'percent' => $this->percent,
            'remaining' => $this->remaining,
            'next_course_title' => $this->nextCourseTitle,
        ];
    }
}
