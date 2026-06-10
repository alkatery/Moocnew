<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalog;

use App\Contexts\Catalog\Domain\Course\PricingType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateCourseRequest extends FormRequest
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
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'summary' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'pricing_type' => ['sometimes', 'required', Rule::enum(PricingType::class)],
            'price_minor' => ['nullable', 'integer', 'min:0'],
            'passing_grade' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
        ];
    }
}
