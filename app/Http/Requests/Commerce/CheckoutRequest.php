<?php

declare(strict_types=1);

namespace App\Http\Requests\Commerce;

use Illuminate\Foundation\Http\FormRequest;

final class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // payments.enabled middleware + auth guard the route
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'course_slug' => ['required', 'string', 'exists:courses,slug'],
            'coupon' => ['nullable', 'string', 'max:64'],
        ];
    }
}
