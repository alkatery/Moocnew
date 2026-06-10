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
            'content' => $this->content,
            'has_file' => $this->file_path !== null,
            'grade' => $this->grade,
            'rubric_scores' => $this->rubric_scores,
            'feedback' => $this->feedback,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'graded_at' => $this->graded_at?->toIso8601String(),
        ];
    }
}
