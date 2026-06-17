<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalog;

use App\Contexts\Catalog\Domain\Course\PricingType;
use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Course::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            // Staff (course reviewers/admins) may create on behalf of an
            // instructor; the controller ignores this for non-staff callers.
            'instructor_id' => ['nullable', 'integer', 'exists:users,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'summary' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'pricing_type' => ['required', Rule::enum(PricingType::class)],
            'price_minor' => ['nullable', 'integer', 'min:0'],
            'passing_grade' => ['nullable', 'integer', 'min:0', 'max:100'],
        ];
    }
}
