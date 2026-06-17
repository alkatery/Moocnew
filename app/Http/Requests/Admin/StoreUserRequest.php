<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Contexts\Identity\Domain\Permission;
use App\Contexts\Identity\Domain\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Admin creation of a user account. A Super Admin account can never be
 * created through the API — that role is provisioned out of band.
 */
final class StoreUserRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(8)],
            'role' => ['required', Rule::in([
                Role::Instructor->value,
                Role::Supervisor->value,
                Role::Student->value,
            ])],
        ];
    }
}
