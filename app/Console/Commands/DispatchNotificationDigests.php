<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contexts\Notification\Infrastructure\Notifications\DigestNotification;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;

/**
 * D3 — يُرسل ملخّصات النشاط الدورية (يومي/أسبوعي) للمستخدمين المشتركين.
 *
 * المبدأ: idempotent عبر last_digest_at — تشغيل مزدوج لا يُكرّر الإرسال.
 * المصدر: جدول notifications القياسي (Laravel database channel).
 * الأداء: chunkById(200) — خفيف الذاكرة لأعداد كبيرة من المستخدمين.
 * الطابور: كل notify() مهمة مُطابورة (ShouldQueue) على 'notifications'.
 */
final class DispatchNotificationDigests extends Command
{
    /**
     * --frequency يُقيّد التشغيل لنوع محدد (للاختبار/التشغيل اليدوي).
     */
    protected $signature = 'notifications:dispatch-digests
                            {--frequency= : daily|weekly (افتراضياً كلاهما حسب الاستحقاق)}';

    protected $description = 'Dispatch daily/weekly activity digests to opted-in users';

    public function handle(): int
    {
        $now = Date::now();
        $forcedFrequency = $this->option('frequency');
        $sent = 0;

        User::query()
            ->where('digest_frequency', '!=', 'off')
            ->whereNull('disabled_at')       // المستخدم المعطّل مستبعَد
            // المحذوف بـ SoftDeletes مستبعَد آلياً عبر النطاق العام
            ->chunkById(200, function ($users) use ($now, $forcedFrequency, &$sent): void {
                foreach ($users as $user) {
                    // تحقّق من الاستحقاق (توقيت + يوم الأحد للأسبوعي)
                    if (! $this->isDue($user, $now, $forcedFrequency)) {
                        continue;
                    }

                    // نقطة البداية: last_digest_at أو نافذة إعادة النظر (أول ملخّص)
                    $since = $user->last_digest_at ?? $this->lookbackStart($user, $now);

                    // جمع الإشعارات الجديدة (جدول notifications القياسي)
                    $total = $user->notifications()
                        ->where('created_at', '>', $since)
                        ->count();

                    // §1: لا ملخّص فارغ — لا تحديث last_digest_at
                    if ($total === 0) {
                        continue;
                    }

                    // سقف 50 عنصراً في البريد — تقليل البيانات (PDPL)
                    $activity = $user->notifications()
                        ->where('created_at', '>', $since)
                        ->orderByDesc('created_at')
                        ->limit(50)
                        ->get(['id', 'data', 'created_at']);

                    // بناء قائمة عناوين مختصرة فقط (لا حمولة data كاملة)
                    $items = $activity->map(function ($notification): array {
                        $data = is_array($notification->data)
                            ? $notification->data
                            : json_decode($notification->data, true) ?? [];

                        return [
                            'type' => $data['type'] ?? null,
                            'title' => $this->digestTitleFor($data),
                        ];
                    })->all();

                    // إرسال الملخّص (مُطابور على طابور notifications)
                    $user->notify(new DigestNotification(
                        frequency: $user->digest_frequency,
                        totalCount: $total,
                        items: $items,
                    ));

                    // ضبط last_digest_at يضمن idempotency (لا إرسال ثانٍ في نفس الجلسة)
                    $user->update(['last_digest_at' => $now]);
                    $sent++;
                }
            });

        $this->info("Sent {$sent} digest(s).");

        return self::SUCCESS;
    }

    /**
     * هل المستخدم مستحقّ لاستقبال ملخّص الآن؟
     *
     * daily:  يومياً (هامش 23 ساعة لمنع الانزلاق التدريجي).
     * weekly: يوم الأحد بتوقيت المستخدم + هامش 6 أيام.
     */
    private function isDue(User $user, Carbon $now, ?string $forcedFrequency): bool
    {
        $freq = $user->digest_frequency;

        // --frequency يُقيّد على نوع محدد
        if ($forcedFrequency !== null && $freq !== $forcedFrequency) {
            return false;
        }

        return match ($freq) {
            'daily' => $user->last_digest_at === null
                || $user->last_digest_at->lte($now->copy()->subHours(23)),

            'weekly' => Date::now($user->timezone ?? 'Asia/Riyadh')->isSunday()
                && (
                    $user->last_digest_at === null
                    || $user->last_digest_at->lte($now->copy()->subDays(6))
                ),

            default => false,
        };
    }

    /**
     * نقطة بداية النافذة عند أول ملخّص (last_digest_at = null).
     * يمنع تجميع تاريخ المستخدم كاملاً في أول بريد.
     */
    private function lookbackStart(User $user, Carbon $now): Carbon
    {
        return match ($user->digest_frequency) {
            'weekly' => $now->copy()->subWeek(),
            default => $now->copy()->subDay(),   // daily وأي قيمة غير متوقّعة
        };
    }

    /**
     * يستخلص عنواناً مختصراً من بيانات الإشعار.
     * يُعيد نصّاً آمناً — لا HTML خام (منع XSS).
     *
     * @param  array<string, mixed>  $data
     */
    private function digestTitleFor(array $data): string
    {
        // محاولة الحقول الأكثر إفادةً بالترتيب
        foreach (['title', 'subject', 'course_title', 'path_title', 'type'] as $key) {
            if (! empty($data[$key]) && is_string($data[$key])) {
                return $data[$key];
            }
        }

        return 'تنبيه جديد';
    }
}
