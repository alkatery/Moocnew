<?php

declare(strict_types=1);

namespace App\Http\Requests\Notification;

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Enrollment\Application\CourseAccess;
use Illuminate\Foundation\Http\FormRequest;

/**
 * طلب إرسال بريد جماعي للمقرر (C3 — PRD §5.ط).
 *
 * التخويل: طاقم المقرر فقط (isStaffFor).
 * التحقّق: subject (255) + body (5000) مطلوبان؛
 *           audience اختياري — القيمة الوحيدة المدعومة: all_active.
 */
final class SendBulkEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Course|null $course */
        $course = $this->route('course');

        return $course !== null
            && app(CourseAccess::class)->isStaffFor($this->user(), $course);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'audience' => ['nullable', 'string', 'in:all_active'],
        ];
    }
}
