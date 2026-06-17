<?php

declare(strict_types=1);

namespace App\Contexts\Notification\Infrastructure\Notifications;

use App\Contexts\Notification\Domain\NotificationType;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * إشعار البريد الجماعي للمقرر (C3 — PRD §5.ط).
 *
 * يُرسَل لكل متعلّم نشط فردياً (لا BCC — عدم كشف العناوين).
 * القناة الوحيدة: mail (البريد الجماعي قناته البريد بحكم تعريفه).
 * النصّ لا يُخزَّن في قاعدة البيانات — تقليل البيانات (PDPL).
 * مُطابور على طابور notifications (ShouldQueue).
 */
final class CourseBulkEmailNotification extends PreferenceAwareNotification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $courseTitle,
        private readonly string $subject,
        private readonly string $body,
    ) {
        // توجيه المهمة لطابور notifications المخصّص
        $this->onQueue('notifications');
    }

    public function type(): NotificationType
    {
        return NotificationType::CourseBulkEmail;
    }

    /**
     * قناة البريد الجماعي: mail فقط (لا database/SMS/واتساب).
     *
     * @return list<string>
     */
    protected function candidateChannels(): array
    {
        return ['mail'];
    }

    /**
     * الرسالة البريدية — نصّ عادي لكل مستلم على حدة (لا HTML خام، لا BCC).
     */
    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->subject)
            ->line($this->body)
            ->line("أُرسلت من فريق دورة: {$this->courseTitle}");
    }
}
