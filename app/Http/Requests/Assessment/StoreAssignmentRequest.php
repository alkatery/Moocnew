<?php

declare(strict_types=1);

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('course')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_at' => ['nullable', 'date'],
            'points' => ['nullable', 'integer', 'min:1'],
            'weight' => ['nullable', 'integer', 'min:1', 'max:100'],
            'section_id' => [
                'nullable', 'integer',
                Rule::exists('sections', 'id')->where('course_id', $this->route('course')?->getKey()),
            ],
        ];
    }
}
