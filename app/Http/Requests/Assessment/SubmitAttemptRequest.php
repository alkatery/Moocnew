<?php

declare(strict_types=1);

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;

final class SubmitAttemptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Ownership is checked in the controller.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // map of question_id => answer (shape depends on question type)
            'answers' => ['present', 'array'],
        ];
    }
}
