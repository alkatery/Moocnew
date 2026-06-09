<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Contexts\Enrollment\Infrastructure\Persistence\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Enrollment
 */
final class EnrollmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'status' => $this->status->value,
            'progress_percent' => $this->progress_percent,
            'access_expires_at' => $this->access_expires_at?->toIso8601String(),
            'enrolled_at' => $this->enrolled_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'course' => new CourseResource($this->whenLoaded('course')),
        ];
    }
}
