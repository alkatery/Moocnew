<?php

declare(strict_types=1);

namespace App\Http\Requests\Learning;

use App\Contexts\Identity\Domain\Permission;
use Illuminate\Foundation\Http\FormRequest;

final class UpdatePathRequest extends FormRequest
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
            'title' => ['sometimes', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:20000'],
            'published' => ['sometimes', 'boolean'],
        ];
    }
}
