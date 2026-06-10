<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Contexts\Enrollment\Infrastructure\Persistence\EnrollmentCode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EnrollmentCode
 */
final class EnrollmentCodeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'code' => $this->code,
            'max_uses' => $this->max_uses,
            'used_count' => $this->used_count,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'is_expired' => $this->isExpired(),
            'is_exhausted' => $this->isExhausted(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
