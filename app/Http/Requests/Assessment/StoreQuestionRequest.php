<?php

declare(strict_types=1);

namespace App\Http\Requests\Assessment;

use App\Contexts\Assessment\Domain\QuestionType;
use App\Contexts\Assessment\Infrastructure\Persistence\Question;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates a question for the bank (used for both create and update). The
 * shape of `correct` depends on the question type, so cross-field checks
 * run in {@see withValidator()}.
 * E4: إضافة قواعد config وفروع التحقّق للأنواع الأربعة الجديدة.
 */
final class StoreQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->course()) ?? false;
    }

    public function course(): mixed
    {
        $question = $this->route('question');

        return $question instanceof Question ? $question->course : $this->route('course');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(QuestionType::class)],
            'body' => ['required', 'string'],
            'points' => ['nullable', 'integer', 'min:1'],
            'explanation' => ['nullable', 'string'],
            'choices' => ['nullable', 'array'],
            'choices.*.id' => ['required_with:choices', 'string'],
            'choices.*.text' => ['required_with:choices', 'string'],
            'correct' => ['required'],
            // E4 — معاملات تقييم الأنواع الجديدة
            'config' => ['nullable', 'array'],
            'config.tolerance' => ['nullable', 'numeric', 'min:0'],
            'config.flags' => ['nullable', 'string', 'in:,i'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $type = QuestionType::tryFrom((string) $this->input('type'));
            $correct = $this->input('correct');

            match ($type) {
                QuestionType::Mcq => $this->validateMcq($validator, $correct),
                QuestionType::TrueFalse => $this->validateTrueFalse($validator, $correct),
                QuestionType::ShortAnswer => $this->validateShortAnswer($validator, $correct),
                // E4 — أنواع إضافية
                QuestionType::Dropdown => $this->validateDropdown($validator, $correct),
                QuestionType::MultiSelect => $this->validateMultiSelect($validator, $correct),
                QuestionType::Numerical => $this->validateNumerical($validator, $correct),
                QuestionType::Regex => $this->validateRegex($validator, $correct),
                default => null,
            };
        });
    }

    private function validateMcq(Validator $validator, mixed $correct): void
    {
        $choices = (array) $this->input('choices', []);

        if ($choices === []) {
            $validator->errors()->add('choices', 'أسئلة الاختيار من متعدد تتطلب خيارات.');

            return;
        }

        $choiceIds = array_map(static fn ($c) => (string) ($c['id'] ?? ''), $choices);

        if (! is_array($correct) || $correct === [] || array_diff(array_map('strval', $correct), $choiceIds) !== []) {
            $validator->errors()->add('correct', 'الإجابة الصحيحة يجب أن تكون من معرّفات الخيارات.');
        }
    }

    private function validateTrueFalse(Validator $validator, mixed $correct): void
    {
        if (! is_bool($correct)) {
            $validator->errors()->add('correct', 'سؤال الصح/الخطأ يتطلب إجابة منطقية (true/false).');
        }
    }

    private function validateShortAnswer(Validator $validator, mixed $correct): void
    {
        $accepted = is_array($correct) ? $correct : [$correct];
        $accepted = array_filter($accepted, static fn ($v) => is_string($v) && trim($v) !== '');

        if ($accepted === []) {
            $validator->errors()->add('correct', 'الإجابة القصيرة تتطلب إجابة مقبولة واحدة على الأقل.');
        }
    }

    // ------------------------------------------------------------------ E4

    /**
     * dropdown: قائمة ≥2 خيارات، إجابة واحدة بالضبط من معرّفات الخيارات.
     */
    private function validateDropdown(Validator $validator, mixed $correct): void
    {
        $choices = (array) $this->input('choices', []);

        if (count($choices) < 2) {
            $validator->errors()->add('choices', 'القائمة المنسدلة تتطلب خيارين على الأقل.');

            return;
        }

        $choiceIds = array_map(static fn ($c) => (string) ($c['id'] ?? ''), $choices);

        if (! is_array($correct) || count($correct) !== 1) {
            $validator->errors()->add('correct', 'القائمة المنسدلة تتطلب إجابة صحيحة واحدة فقط.');

            return;
        }

        if (array_diff(array_map('strval', $correct), $choiceIds) !== []) {
            $validator->errors()->add('correct', 'الإجابة الصحيحة يجب أن تكون من معرّفات الخيارات.');
        }
    }

    /**
     * multi_select: قائمة ≥2 خيارات، ≥1 إجابة صحيحة من معرّفات الخيارات.
     */
    private function validateMultiSelect(Validator $validator, mixed $correct): void
    {
        $choices = (array) $this->input('choices', []);

        if (count($choices) < 2) {
            $validator->errors()->add('choices', 'أسئلة متعدّدة الإجابات تتطلب خيارين على الأقل.');

            return;
        }

        $choiceIds = array_map(static fn ($c) => (string) ($c['id'] ?? ''), $choices);

        if (! is_array($correct) || $correct === []) {
            $validator->errors()->add('correct', 'أسئلة متعدّدة الإجابات تتطلب إجابة صحيحة واحدة على الأقل من الخيارات.');

            return;
        }

        if (array_diff(array_map('strval', $correct), $choiceIds) !== []) {
            $validator->errors()->add('correct', 'أسئلة متعدّدة الإجابات تتطلب إجابة صحيحة واحدة على الأقل من الخيارات.');
        }
    }

    /**
     * numerical: قيمة رقمية واحدة + هامش خطأ ≥0.
     */
    private function validateNumerical(Validator $validator, mixed $correct): void
    {
        if (! is_array($correct) || count($correct) !== 1 || ! is_numeric($correct[0])) {
            $validator->errors()->add('correct', 'السؤال الرقمي يتطلب قيمة عددية وهامش خطأ ≥ 0.');

            return;
        }

        $tolerance = $this->input('config.tolerance');
        if ($tolerance !== null && (! is_numeric($tolerance) || (float) $tolerance < 0)) {
            $validator->errors()->add('config.tolerance', 'السؤال الرقمي يتطلب قيمة عددية وهامش خطأ ≥ 0.');
        }
    }

    /**
     * regex: نمط نصّي واحد ≤200 محرف، صالح نحوياً، أعلام مقصورة على ''/'i'.
     * النمط يُختبر بـ @preg_match تجريبي لمنع حفظ نمط يكسر التصحيح.
     */
    private function validateRegex(Validator $validator, mixed $correct): void
    {
        if (! is_array($correct) || count($correct) !== 1 || ! is_string($correct[0]) || trim($correct[0]) === '') {
            $validator->errors()->add('correct', 'نمط Regex غير صالح أو طويل جداً.');

            return;
        }

        $pattern = $correct[0];

        if (mb_strlen($pattern) > 200) {
            $validator->errors()->add('correct', 'نمط Regex غير صالح أو طويل جداً.');

            return;
        }

        // العلَم المسموح به فقط: '' أو 'i'
        $flags = (string) $this->input('config.flags', '');
        $allowedFlags = in_array($flags, ['', 'i'], true) ? $flags : '';

        // فحص صحّة النمط بتجربة تجريبية — لا تخزين نمط يفشل preg_match
        $safePattern = str_replace('/', '\/', $pattern);
        $delimited = '/'.$safePattern.'/u'.$allowedFlags;
        $probe = @preg_match($delimited, '');

        if ($probe === false) {
            $validator->errors()->add('correct', 'نمط Regex غير صالح أو طويل جداً.');
        }
    }
}
