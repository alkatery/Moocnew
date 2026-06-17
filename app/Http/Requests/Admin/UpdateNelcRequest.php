<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Contexts\Identity\Domain\Permission;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Authorizes and validates setting the NELC licence number. Only holders
 * of the `settings.manage` permission (Super Admin) may do so.
 */
final class UpdateNelcRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::ManageSettings->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'license_number' => ['nullable', 'string', 'max:100'],
        ];
    }
}
