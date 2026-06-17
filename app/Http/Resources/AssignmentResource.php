<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Contexts\Assessment\Infrastructure\Persistence\Assignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Assignment
 */
final class AssignmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'section_id' => $this->section_id,
            'title' => $this->title,
            'description' => $this->description,
            'due_at' => $this->due_at?->toIso8601String(),
            'points' => $this->points,
            'rubric' => $this->rubric,
            'weight' => $this->weight,
            'position' => $this->position,
        ];
    }
}
