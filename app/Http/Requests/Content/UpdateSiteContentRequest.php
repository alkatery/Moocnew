<?php

declare(strict_types=1);

namespace App\Http\Requests\Content;

use App\Contexts\Identity\Domain\Permission;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Bulk update of text/textarea/color/url site-content values. Image fields
 * are handled by a dedicated upload endpoint, not here.
 */
final class UpdateSiteContentRequest extends FormRequest
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
            'values' => ['required', 'array'],
            'values.*' => ['nullable', 'string', 'max:20000'],
        ];
    }
}
