<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Contexts\Assessment\Infrastructure\Persistence\AssignmentSubmission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AssignmentSubmission
 */
final class AssignmentSubmissionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'assignment_id' => $this->assignment_id,
            'user_id' => $this->user_id,
            // §2.أ — الاسم فقط (PDPL: لا بريد/هاتف)؛ مشروط بـ eager-load
            'student' => [
                'id' => $this->user_id,
                'name' => $this->whenLoaded('user', fn () => $this->user->name),
            ],
            'content' => $this->content,
            'has_file' => $this->file_path !== null,
            // §2.ب — رابط التنزيل المحمي؛ null صريح إن لا ملف (PDPL: لا يُسرَّب المسار الداخلي)
            'file_url' => $this->file_path !== null
                ? route('api.assessment.submissions.file', $this->resource)
                : null,
            'grade' => $this->grade,
            'rubric_scores' => $this->rubric_scores,
            'feedback' => $this->feedback,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'graded_at' => $this->graded_at?->toIso8601String(),
        ];
    }
}
