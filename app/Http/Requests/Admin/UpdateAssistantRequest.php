<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Contexts\Assistant\Domain\AssistantMode;
use App\Contexts\Identity\Domain\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Choosing the assistant engine (off / rules / claude). Super Admin only.
 */
final class UpdateAssistantRequest extends FormRequest
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
            'mode' => ['required', Rule::enum(AssistantMode::class)],
        ];
    }
}
