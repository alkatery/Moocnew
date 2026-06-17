<?php

declare(strict_types=1);

namespace App\Http\Requests\Learning;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreStudyPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'cadence_days' => ['required', 'integer', Rule::in([1, 2, 3, 7, 14])],
            'target_date' => ['nullable', 'date', 'after_or_equal:today'],
            'course_ids' => ['required', 'array', 'min:1', 'max:50'],
            'course_ids.*' => ['integer', 'distinct', 'exists:courses,id'],
        ];
    }
}
