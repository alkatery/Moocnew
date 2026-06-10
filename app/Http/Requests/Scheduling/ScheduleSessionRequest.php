<?php

declare(strict_types=1);

namespace App\Http\Requests\Scheduling;

use App\Contexts\Scheduling\Domain\Meeting\MeetingProviderType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ScheduleSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('course')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'provider' => ['required', Rule::enum(MeetingProviderType::class)],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            // NELC compliance: synchronous sessions are capped at 35 learners.
            'capacity' => ['nullable', 'integer', 'min:1', 'max:35'],
            'max_participants' => ['nullable', 'integer', 'min:1', 'max:35'],
            // Required only for the "paste a link" provider.
            'join_url' => ['nullable', 'url', 'required_if:provider,manual'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $cap = 'الحد الأقصى 35 متعلماً للجلسة المتزامنة وفق معايير المركز الوطني للتعليم الإلكتروني';

        return [
            'capacity.max' => $cap,
            'max_participants.max' => $cap,
        ];
    }
}
