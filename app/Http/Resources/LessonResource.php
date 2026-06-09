<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Contexts\Catalog\Infrastructure\Persistence\Lesson;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Catalogue view of a lesson. Deliberately exposes only structural
 * metadata — the actual article body, file, and signed video playback are
 * gated behind enrollment (handled in the Enrollment context, PRD §5.ج).
 *
 * @mixin Lesson
 */
final class LessonResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type->value,
            'position' => $this->position,
            'is_free_preview' => $this->is_free_preview,
            'video_status' => $this->video_status->value,
        ];
    }
}
