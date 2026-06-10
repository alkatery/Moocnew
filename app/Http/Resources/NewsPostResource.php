<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Contexts\Content\Infrastructure\Persistence\NewsPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NewsPost
 */
final class NewsPostResource extends JsonResource
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
            'excerpt' => $this->excerpt,
            'body' => $this->body,
            'published_at' => $this->published_at?->toIso8601String(),
            'author' => [
                'id' => $this->whenLoaded('author', fn () => $this->author->id),
                'name' => $this->whenLoaded('author', fn () => $this->author->name),
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
