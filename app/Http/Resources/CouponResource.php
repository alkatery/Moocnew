<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Contexts\Commerce\Infrastructure\Persistence\Coupon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Coupon */
final class CouponResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'type' => $this->type->value,
            'value' => $this->value,
            'max_uses' => $this->max_uses,
            'used_count' => $this->used_count,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'active' => $this->active,
        ];
    }
}
