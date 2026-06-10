<?php

declare(strict_types=1);

namespace App\Http\Requests\Content;

use App\Contexts\Identity\Domain\Permission;
use Illuminate\Foundation\Http\FormRequest;

final class StoreNewsPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::ManageContent->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string', 'max:50000'],
            'published' => ['sometimes', 'boolean'],
        ];
    }
}
