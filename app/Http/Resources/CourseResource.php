<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Course
 */
final class CourseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'summary' => $this->summary,
            'description' => $this->description,
            'status' => $this->status->value,
            'pricing_type' => $this->pricing_type->value,
            'price_minor' => $this->price_minor,
            'published_at' => $this->published_at?->toIso8601String(),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'instructor' => [
                'id' => $this->whenLoaded('instructor', fn () => $this->instructor->id),
                'name' => $this->whenLoaded('instructor', fn () => $this->instructor->name),
            ],
            'sections' => SectionResource::collection($this->whenLoaded('sections')),
        ];
    }
}
