<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Contexts\Catalog\Infrastructure\Persistence\Section;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Section
 */
final class SectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'position' => $this->position,
            'lessons' => LessonResource::collection($this->whenLoaded('lessons')),
        ];
    }
}
