<?php

declare(strict_types=1);

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;

/**
 * تحقّق طلب استيراد الأسئلة إلى بنك المقرر الهدف (E5).
 */
final class ImportQuestionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $course = $this->route('course');

        return $this->user()?->can('update', $course) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'source_question_ids' => ['required', 'array', 'min:1', 'max:100'],
            'source_question_ids.*' => ['integer', 'distinct', 'exists:question_bank,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'source_question_ids.required' => 'يجب تحديد سؤال واحد على الأقل للاستيراد.',
            'source_question_ids.min' => 'يجب تحديد سؤال واحد على الأقل للاستيراد.',
            'source_question_ids.max' => 'لا يمكن استيراد أكثر من 100 سؤال في طلب واحد.',
            'source_question_ids.*.exists' => 'أحد معرّفات الأسئلة المطلوبة غير موجود.',
            'source_question_ids.*.distinct' => 'معرّفات الأسئلة يجب أن تكون فريدة.',
        ];
    }
}
