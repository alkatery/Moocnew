<?php

declare(strict_types=1);

namespace App\Contexts\Assessment\Domain;

/**
 * Pure, framework-free grading of a single objective answer (PRD §5.هـ).
 * Kept in the Domain layer so the scoring rules are unit-testable in
 * isolation and shared by both live grading and any re-grading.
 */
final class AnswerGrader
{
    /**
     * @param  mixed  $correct  the question's answer key
     * @param  mixed  $answer  the learner's submitted answer
     * @param  array  $config  معاملات التقييم (tolerance/flags) — تستخدمها الأنواع الجديدة فقط
     */
    public function isCorrect(QuestionType $type, mixed $correct, mixed $answer, array $config = []): bool
    {
        return match ($type) {
            QuestionType::Mcq => $this->gradeMcq($correct, $answer),
            QuestionType::TrueFalse => $this->gradeTrueFalse($correct, $answer),
            QuestionType::ShortAnswer => $this->gradeShortAnswer($correct, $answer),
            // E4 — أنواع إضافية
            QuestionType::Dropdown => $this->gradeMcq($correct, $answer),
            QuestionType::MultiSelect => $this->gradeMcq($correct, $answer),
            QuestionType::Numerical => $this->gradeNumerical($correct, $answer, $config),
            QuestionType::Regex => $this->gradeRegex($correct, $answer, $config),
        };
    }

    /**
     * MCQ: the submitted set of choice ids must equal the correct set
     * (order-independent), supporting both single- and multi-select.
     */
    private function gradeMcq(mixed $correct, mixed $answer): bool
    {
        if (! is_array($correct) || ! is_array($answer)) {
            return false;
        }

        $expected = array_values(array_unique(array_map('strval', $correct)));
        $given = array_values(array_unique(array_map('strval', $answer)));

        sort($expected);
        sort($given);

        return $expected === $given;
    }

    private function gradeTrueFalse(mixed $correct, mixed $answer): bool
    {
        return is_bool($answer) ? ((bool) $correct === $answer) : false;
    }

    /**
     * Short answer: case-insensitive, whitespace- and Arabic-diacritic
     * normalised match against any accepted answer.
     */
    private function gradeShortAnswer(mixed $correct, mixed $answer): bool
    {
        if (! is_string($answer)) {
            return false;
        }

        $accepted = is_array($correct) ? $correct : [$correct];
        $normalisedAnswer = $this->normalise($answer);

        foreach ($accepted as $candidate) {
            if (is_string($candidate) && $this->normalise($candidate) === $normalisedAnswer) {
                return true;
            }
        }

        return false;
    }

    private function normalise(string $value): string
    {
        // Strip Arabic diacritics (tashkeel) and tatweel, collapse spaces.
        $value = preg_replace('/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return mb_strtolower(trim($value));
    }

    // ------------------------------------------------------------------ E4

    /**
     * Numerical: إجابة رقمية ضمن هامش خطأ (±tolerance) حول القيمة الصحيحة.
     * tolerance = 0 ⇒ مطابقة تامة؛ المقارنة <= شاملة للحد.
     *
     * @param  array  $config  ['tolerance' => float≥0]
     */
    private function gradeNumerical(mixed $correct, mixed $answer, array $config): bool
    {
        if (! is_numeric($answer)) {
            return false;
        }

        if (! is_array($correct) || $correct === []) {
            return false;
        }

        $target = (float) $correct[0];
        $tolerance = (float) ($config['tolerance'] ?? 0);
        $given = (float) $answer;

        return abs($given - $target) <= $tolerance;
    }

    /**
     * Regex: مطابقة نصّية آمنة — النمط مُولَّد محلياً، الأعلام مقصورة على i/u.
     * الحد الأقصى لطول الإجابة: 2000 محرف (حماية ReDoS من جهة الإدخال).
     * أي فشل في preg_match يُعاد false بأمان (لا استثناء، لا 500).
     *
     * @param  array  $config  ['flags' => 'i'|'']
     */
    private function gradeRegex(mixed $correct, mixed $answer, array $config): bool
    {
        if (! is_string($answer)) {
            return false;
        }

        // حماية ReDoS: ردّ الإجابة الطويلة جداً قبل تشغيل PCRE
        if (mb_strlen($answer) > 2000) {
            return false;
        }

        if (! is_array($correct) || $correct === [] || ! is_string($correct[0])) {
            return false;
        }

        $pattern = $correct[0];

        // المحدِّد يُضاف هنا، لا يُخزَّن — هروب الشرطة المائلة فقط
        $safePattern = str_replace('/', '\/', $pattern);

        // العلم الوحيد المسموح: i (تجاهل الحالة)؛ u (Unicode) مفروض دائماً
        $rawFlags = $config['flags'] ?? '';
        $allowedFlags = in_array($rawFlags, ['', 'i'], true) ? $rawFlags : '';
        $delimited = '/'.$safePattern.'/u'.$allowedFlags;

        // كاتم الأخطاء + فحص صريح — لا 500، لا توقّف عند نمط تالف
        $result = @preg_match($delimited, $answer);

        if ($result === false) {
            return false;
        }

        return $result === 1;
    }
}
