<?php

declare(strict_types=1);

namespace App\Http\Requests\Commerce;

use App\Contexts\Identity\Domain\Permission;
use Illuminate\Foundation\Http\FormRequest;

final class RequestPayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Instructors (course managers) may request their own payouts.
        return $this->user()?->can(Permission::ManageCourses->value) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'amount_minor' => ['required', 'integer', 'min:1'],
        ];
    }
}
