<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * Validates a self-service password change. `current_password` is verified
 * directly against the authenticated user's hash (guard-agnostic, works
 * with the Sanctum token flow); the new password reuses the registration
 * strength policy and must differ from the current one.
 */
final class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'current_password' => [
                'required',
                'string',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! Hash::check((string) $value, (string) $this->user()->password)) {
                        $fail('كلمة المرور الحالية غير صحيحة.');
                    }
                },
            ],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(8)],
        ];
    }
}
