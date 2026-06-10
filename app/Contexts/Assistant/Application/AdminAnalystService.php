<?php

declare(strict_types=1);

namespace App\Contexts\Assistant\Application;

use App\Contexts\Analytics\Application\AnalyticsService;
use App\Contexts\Assistant\Domain\AssistantMode;
use App\Contexts\Assistant\Domain\AssistantReply;
use App\Contexts\Assistant\Domain\ChatMessage;
use App\Contexts\Catalog\Domain\Course\CourseStatus;
use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Enrollment\Domain\EnrollmentStatus;
use App\Contexts\Enrollment\Infrastructure\Persistence\Enrollment;
use App\Contexts\Platform\Application\FeatureFlags;

/**
 * The admin analyst «بصيرة». Reads only aggregate platform metrics (PDPL),
 * then either reports them deterministically (rules) or has Claude interpret
 * them and recommend improvements.
 */
final class AdminAnalystService
{
    public function __construct(
        private readonly FeatureFlags $flags,
        private readonly AnalyticsService $analytics,
        private readonly AssistantEngine $engine,
    ) {}

    public function mode(): AssistantMode
    {
        return AssistantMode::tryFrom($this->flags->assistantMode()) ?? AssistantMode::Off;
    }

    /**
     * @param  list<ChatMessage>  $history
     */
    public function answer(string $question, array $history = []): AssistantReply
    {
        $metrics = $this->metrics();
        $context = $this->contextBlock($metrics);

        if ($this->mode() === AssistantMode::Rules) {
            return $this->rulesAnswer($metrics);
        }

        $system = <<<SYS
            أنت «بصيرة»، محلّل أداء لمنصة تعليمية، تساعد الإدارة على مراقبة النظام والطلاب
            واقتراح التحسينات. التزم بالآتي:
            - أجب بالعربية باختصار ودقة، مستنداً إلى المؤشرات المرفقة فقط (بيانات تجميعية).
            - عند الطلب، قدّم توصيات عملية قابلة للتنفيذ مبنية على الأرقام.
            - لا تختلق أرقاماً غير موجودة في السياق.
            <context>
            {$context}
            </context>
            SYS;

        $messages = [...$history, ChatMessage::user($question)];
        $text = $this->engine->reply($system, $messages, (string) config('ai.models.analyst'));

        return new AssistantReply($text);
    }

    /**
     * @return array<string, int|bool>
     */
    private function metrics(): array
    {
        $overview = $this->analytics->overview();

        $atRisk = Enrollment::query()
            ->where('status', EnrollmentStatus::Active->value)
            ->where('progress_percent', '<', 20)
            ->count();

        $unrated = Course::query()
            ->where('status', CourseStatus::Published->value)
            ->whereDoesntHave('reviews')
            ->count();

        return [
            ...$overview,
            'at_risk_learners' => $atRisk,
            'unrated_published_courses' => $unrated,
        ];
    }

    /**
     * @param  array<string, int|bool>  $m
     */
    private function contextBlock(array $m): string
    {
        return collect($m)
            ->map(fn ($v, string $k): string => "{$k}: ".(is_bool($v) ? ($v ? 'نعم' : 'لا') : (string) $v))
            ->implode("\n");
    }

    /**
     * @param  array<string, int|bool>  $m
     */
    private function rulesAnswer(array $m): AssistantReply
    {
        $completionRate = ($m['enrollments_total'] ?? 0) > 0
            ? (int) round(((int) $m['enrollments_completed'] / (int) $m['enrollments_total']) * 100)
            : 0;

        $lines = [
            'ملخّص أداء المنصة:',
            "• المستخدمون: {$m['users_total']} — متصلون الآن: {$m['online_now']}",
            "• الدورات المنشورة: {$m['courses_published']}",
            "• الالتحاقات: {$m['enrollments_total']} (نشطة: {$m['enrollments_active']}، مكتملة: {$m['enrollments_completed']})",
            "• نسبة الإكمال: {$completionRate}%",
            "• الشهادات الصادرة: {$m['certificates_issued']}",
        ];

        $recommendations = ['توصيات مقترحة:'];
        if (($m['at_risk_learners'] ?? 0) > 0) {
            $recommendations[] = "• {$m['at_risk_learners']} متعلّماً نشطاً تحت 20% تقدّماً — أرسل لهم تذكيراً أو خطة دراسية.";
        }
        if (($m['unrated_published_courses'] ?? 0) > 0) {
            $recommendations[] = "• {$m['unrated_published_courses']} دورة منشورة بلا تقييمات — شجّع المتعلّمين على التقييم.";
        }
        if ($completionRate < 50 && ($m['enrollments_total'] ?? 0) > 0) {
            $recommendations[] = '• نسبة الإكمال منخفضة — راجع نقاط تسرّب الطلاب في تحليل مسار كل دورة.';
        }
        if (count($recommendations) === 1) {
            $recommendations[] = '• المؤشرات ضمن المعدّل الجيد — واصل المتابعة.';
        }

        return new AssistantReply(implode("\n", [...$lines, '', ...$recommendations]));
    }
}
