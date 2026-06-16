<?php

declare(strict_types=1);

namespace App\Contexts\Notification\Infrastructure\Notifications;

use App\Contexts\Notification\Domain\NotificationType;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * إشعار إعلان المقرر (C3 — PRD §5.ط).
 *
 * يُرسَل للمتعلّمين الملتحقين عند نشر إعلان جديد في مقرّرهم.
 * القناتان المرشَّحتان: database (وارد التطبيق) + mail (بريد اختياري).
 * احترام opt-out مضمون آلياً عبر via() في PreferenceAwareNotification.
 * مُطابور على طابور notifications (ShouldQueue) — لا إرسال متزامن لمئات.
 */
final class CourseAnnouncementNotification extends PreferenceAwareNotification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $courseTitle,
        private readonly int $announcementId,
        private readonly string $title,
        private readonly string $body,
    ) {
        // توجيه المهمة لطابور notifications المخصّص لعزلها عن الطوابير الحسّاسة
        $this->onQueue('notifications');
    }

    public function type(): NotificationType
    {
        return NotificationType::CourseAnnouncement;
    }

    /**
     * قنوات الإعلان: داخل التطبيق + بريد اختياري (لا SMS/واتساب — تنبيه تعليمي خفيف).
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
            ->subject("إعلان جديد في {$this->courseTitle}")
            ->line($this->title)
            ->line($this->body)
            ->line('يمكنك الاطّلاع على الإعلان في صفحة المقرر.');
    }

    /**
     * بيانات قناة database (وارد التطبيق).
     * يُحفَظ announcement_id للربط بصفحة المقرر في الواجهة.
     *
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        return [
            'type' => $this->type()->value,
            'course_title' => $this->courseTitle,
            'announcement_id' => $this->announcementId,
            'title' => $this->title,
        ];
    }
}
