<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Contexts\Identity\Domain\Permission;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Issue a self-enrollment code for a course (courses.review).
 */
final class StoreEnrollmentCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::ReviewCourses->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'max_uses' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
