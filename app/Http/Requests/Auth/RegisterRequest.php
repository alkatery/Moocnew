<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Contexts\Identity\Domain\Consent\ConsentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

/**
 * Validates a self-service registration. PDPL requires explicit consent,
 * so the required consent types must all be present in the payload.
 */
final class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],

            'phone' => ['nullable', 'string', 'max:32'],
            'country' => ['nullable', 'string', 'size:2'],
            'education_level' => ['nullable', 'string', 'max:100'],
            'interests' => ['nullable', 'array'],
            'interests.*' => ['string', 'max:100'],
            'locale' => ['nullable', 'string', 'in:ar,en'],
            'timezone' => ['nullable', 'string', 'timezone'],

            'consents' => ['required', 'array'],
            'consents.*' => ['string', Rule::enum(ConsentType::class)],
        ];
    }

    /**
     * Enforce that every required consent was granted.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $granted = (array) $this->input('consents', []);

            foreach (ConsentType::required() as $required) {
                if (! in_array($required->value, $granted, true)) {
                    $validator->errors()->add(
                        'consents',
                        "الموافقة على «{$required->label()}» مطلوبة.",
                    );
                }
            }
        });
    }
}
