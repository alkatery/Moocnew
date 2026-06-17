<?php

declare(strict_types=1);

namespace App\Contexts\Notification\Infrastructure\Notifications;

use App\Contexts\Notification\Domain\NotificationType;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * D2 — إشعار رد جديد في موضوع بالمنتدى (PRD §5.ز).
 *
 * يُرسَل لمتابعي الموضوع (عدا كاتب الرد) عند إضافة رد جديد.
 * القناتان المرشَّحتان: database (وارد التطبيق) + mail (بريد اختياري).
 * احترام opt-out مضمون آلياً عبر via() في PreferenceAwareNotification.
 * مُطابور على طابور notifications (ShouldQueue) — لا إرسال متزامن لمئات.
 */
final class ForumReplyNotification extends PreferenceAwareNotification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $threadId,
        private readonly string $threadTitle,
        private readonly string $courseTitle,
        private readonly string $replierName,
    ) {
        // توجيه المهمة لطابور notifications المخصّص لعزلها عن الطوابير الحسّاسة
        $this->onQueue('notifications');
    }

    public function type(): NotificationType
    {
        return NotificationType::ForumReply;
    }

    /**
     * قنوات ردود المنتدى: داخل التطبيق + بريد اختياري (لا SMS/واتساب — تنبيه تعليمي خفيف).
     *
     * @return list<string>
     */
    protected function candidateChannels(): array
    {
        return ['database', 'mail'];
    }

    /**
     * الرسالة البريدية — نصّ عادي، لا HTML خام (منع XSS).
     */
    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("رد جديد على: {$this->threadTitle}")
            ->line("أضاف {$this->replierName} رداً جديداً في نقاش {$this->courseTitle}.")
            ->line('اطّلع على الموضوع للمشاركة في النقاش.');
    }

    /**
     * بيانات قناة database (وارد التطبيق).
     * thread_id للربط بصفحة الموضوع في الواجهة؛ لا نصّ الرد كاملاً (تقليل البيانات).
     *
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        return [
            'type' => $this->type()->value,
            'thread_id' => $this->threadId,
            'thread_title' => $this->threadTitle,
            'course_title' => $this->courseTitle,
        ];
    }
}
