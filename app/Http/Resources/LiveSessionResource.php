<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Contexts\Scheduling\Infrastructure\Persistence\LiveSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LiveSession */
final class LiveSessionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'title' => $this->title,
            'provider' => $this->provider->value,
            'join_url' => $this->join_url,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'capacity' => $this->capacity,
            'seats_remaining' => $this->seatsRemaining(),
        ];
    }
}
