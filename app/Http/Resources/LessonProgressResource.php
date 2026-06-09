<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Contexts\Enrollment\Infrastructure\Persistence\LessonProgress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LessonProgress
 */
final class LessonProgressResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'lesson_id' => $this->lesson_id,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'video_position' => $this->video_position,
        ];
    }
}
