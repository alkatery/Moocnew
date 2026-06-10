<?php

declare(strict_types=1);

namespace App\Http\Requests\Engagement;

use Illuminate\Foundation\Http\FormRequest;

/**
 * NELC learner satisfaction survey: four 1–5 axes + optional comment.
 */
final class StoreSurveyRequest extends FormRequest
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
            'overall' => ['required', 'integer', 'between:1,5'],
            'content_quality' => ['required', 'integer', 'between:1,5'],
            'instructor_quality' => ['required', 'integer', 'between:1,5'],
            'platform_quality' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
