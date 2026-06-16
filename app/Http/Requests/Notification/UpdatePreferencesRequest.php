<?php

declare(strict_types=1);

namespace App\Http\Requests\Notification;

use App\Contexts\Notification\Domain\DigestFrequency;
use App\Contexts\Notification\Domain\NotificationChannel;
use App\Contexts\Notification\Domain\NotificationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdatePreferencesRequest extends FormRequest
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
            // مصفوفة القنوات — اختيارية الآن (D3: يمكن إرسال digest_frequency وحده)
            'preferences' => ['sometimes', 'array', 'min:1'],
            'preferences.*.type' => ['required_with:preferences', Rule::enum(NotificationType::class)],
            'preferences.*.channel' => ['required_with:preferences', Rule::enum(NotificationChannel::class)],
            'preferences.*.enabled' => ['required_with:preferences', 'boolean'],

            // D3: تردّد الملخّص — مستقلّ عن مصفوفة القنوات
            'digest_frequency' => ['sometimes', Rule::enum(DigestFrequency::class)],
        ];
    }

    /**
     * يُحقَّق بعد قواعد الحقول: يجب وجود preferences أو digest_frequency على الأقل.
     *
     * @return array<int, \Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $hasPreferences = $this->has('preferences') && $this->input('preferences') !== null;
                $hasFrequency = $this->has('digest_frequency') && $this->input('digest_frequency') !== null;

                if (! $hasPreferences && ! $hasFrequency) {
                    $validator->errors()->add(
                        'preferences',
                        'يجب توفير preferences أو digest_frequency على الأقل.',
                    );
                }
            },
        ];
    }
}
