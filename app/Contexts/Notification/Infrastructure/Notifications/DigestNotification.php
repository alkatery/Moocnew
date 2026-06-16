<?php

declare(strict_types=1);

namespace App\Contexts\Notification\Infrastructure\Notifications;

use App\Contexts\Notification\Domain\NotificationType;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * D3 — إشعار الملخّص الدوري (يومي/أسبوعي).
 *
 * قناة mail فقط — الملخّص بريدي بطبعه (لا وارد database لتجنّب الإغراق).
 * مُطابور على طابور notifications — لا إرسال متزامن لآلاف المستخدمين.
 * يحترم opt-out آلياً عبر via() في PreferenceAwareNotification.
 * لا HTML خام في البريد — منع XSS (نصّ عبر ->line()).
 * يحمل عناوين مختصرة + عدّ فقط — تقليل البيانات (PDPL).
 */
final class DigestNotification extends PreferenceAwareNotification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  string  $frequency  تردّد الملخّص: daily|weekly
     * @param  int  $totalCount  إجمالي التنبيهات الجديدة منذ آخر ملخّص
     * @param  list<array{type: string|null, title: string}>  $items  أوّل 50 عنصراً
     */
    public function __construct(
        private readonly string $frequency,
        private readonly int $totalCount,
        private readonly array $items,
    ) {
        // توجيه المهمة لطابور notifications المخصّص
        $this->onQueue('notifications');
    }

    public function type(): NotificationType
    {
        return NotificationType::Digest;
    }

    /**
     * الملخّص بريدي فقط — لا قناة database (لا وارد للملخّص نفسه).
     *
     * @return list<string>
     */
    protected function candidateChannels(): array
    {
        return ['mail'];
    }

    /**
     * الرسالة البريدية — نصّ عادي عبر ->line() (منع XSS).
     */
    public function toMail(mixed $notifiable): MailMessage
    {
        $subject = $this->frequency === 'weekly'
            ? 'ملخّصك الأسبوعي'
            : 'ملخّصك اليومي';

        $message = (new MailMessage)
            ->subject($subject)
            ->line("إليك ملخّص نشاطك: {$this->totalCount} تنبيهاً جديداً.");

        // عرض كل عنصر كسطر نصّي — لا حمولة data كاملة (تقليل بيانات)
        foreach ($this->items as $item) {
            if (! empty($item['title'])) {
                $message->line("- {$item['title']}");
            }
        }

        // إشارة للتنبيهات الفائضة عن السقف (50)
        $remaining = $this->totalCount - count($this->items);
        if ($remaining > 0) {
            $message->line("و{$remaining} تنبيهات أخرى.");
        }

        return $message
            ->line('يمكنك الاطّلاع على جميع إشعاراتك في حسابك.')
            ->action('اطّلع على إشعاراتك', url('/notifications'));
    }

    /**
     * بيانات الملخّص للسجلّ (لا تُرسَل عبر قناة database).
     * يُحتفَظ به للاتّساق مع بقية الإشعارات — بيانات دنيا فقط.
     *
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        return [
            'type' => $this->type()->value,
            'frequency' => $this->frequency,
            'count' => $this->totalCount,
        ];
    }
}
