<?php

declare(strict_types=1);

namespace App\Http\Requests\Notification;

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Enrollment\Application\CourseAccess;
use Illuminate\Foundation\Http\FormRequest;

/**
 * طلب نشر إعلان مقرر (C3 — PRD §5.ط).
 *
 * التخويل: طاقم المقرر فقط (isStaffFor).
 * التحقّق: title (255) + body (5000) مطلوبان.
 */
final class StoreAnnouncementRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
        ];
    }
}
