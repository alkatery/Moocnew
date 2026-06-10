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
}
