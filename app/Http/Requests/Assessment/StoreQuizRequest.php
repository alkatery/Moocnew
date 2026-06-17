<?php

declare(strict_types=1);

namespace App\Http\Requests\Assessment;

use App\Contexts\Assessment\Infrastructure\Persistence\Question;
use App\Contexts\Assessment\Infrastructure\Persistence\Quiz;
use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates a quiz and its selected bank questions. The questions must all
 * belong to the same course as the quiz.
 */
final class StoreQuizRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->course()) ?? false;
    }

    public function course(): ?Course
    {
        $quiz = $this->route('quiz');

        if ($quiz instanceof Quiz) {
            return $quiz->course;
        }

        $course = $this->route('course');

        return $course instanceof Course ? $course : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'time_limit_minutes' => ['nullable', 'integer', 'min:1'],
            'shuffle' => ['nullable', 'boolean'],
            'draw_count' => ['nullable', 'integer', 'min:1'],
            'max_attempts' => ['nullable', 'integer', 'min:1'],
            'pass_mark' => ['nullable', 'integer', 'min:0', 'max:100'],
            'weight' => ['nullable', 'integer', 'min:1', 'max:100'],
            'is_gate' => ['nullable', 'boolean'],
            'section_id' => [
                'nullable', 'integer',
                Rule::exists('sections', 'id')->where('course_id', $this->course()?->getKey()),
            ],
            'question_ids' => ['required', 'array', 'min:1'],
            'question_ids.*' => ['integer', 'distinct'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $course = $this->course();
            $ids = (array) $this->input('question_ids', []);

            if ($course === null || $ids === []) {
                return;
            }

            $valid = Question::query()
                ->where('course_id', $course->getKey())
                ->whereIn('id', $ids)
                ->count();

            if ($valid !== count(array_unique($ids))) {
                $validator->errors()->add('question_ids', 'كل الأسئلة يجب أن تنتمي إلى الدورة نفسها.');
            }
        });
    }
}
