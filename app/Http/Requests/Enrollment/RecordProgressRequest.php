<?php

declare(strict_types=1);

namespace App\Http\Requests\Enrollment;

use Illuminate\Foundation\Http\FormRequest;

final class RecordProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Access is enforced in the controller via LessonAccess.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'video_position' => ['nullable', 'integer', 'min:0'],
            'completed' => ['nullable', 'boolean'],
        ];
    }
}
