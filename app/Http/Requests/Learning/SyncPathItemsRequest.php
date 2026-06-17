<?php

declare(strict_types=1);

namespace App\Http\Requests\Learning;

use App\Contexts\Identity\Domain\Permission;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Replaces a path's course list in one call: each entry carries the course
 * plus its level and position (which together define the learning order).
 */
final class SyncPathItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::ManagePaths->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'items' => ['present', 'array', 'max:100'],
            'items.*.course_id' => ['required', 'integer', 'distinct', 'exists:courses,id'],
            'items.*.level' => ['required', 'integer', 'min:1', 'max:50'],
            'items.*.position' => ['required', 'integer', 'min:1', 'max:200'],
        ];
    }
}
