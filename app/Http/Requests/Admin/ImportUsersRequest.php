<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Contexts\Identity\Domain\Permission;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Bulk CSV import of student accounts (users.manage). The file carries
 * name,email[,phone] columns; an optional course_id enrolls every created
 * account into that course immediately.
 */
final class ImportUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::ManageUsers->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
        ];
    }
}
