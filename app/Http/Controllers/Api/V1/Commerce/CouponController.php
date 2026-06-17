<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Commerce;

use App\Contexts\Commerce\Domain\CouponType;
use App\Contexts\Commerce\Infrastructure\Persistence\Coupon;
use App\Contexts\Identity\Domain\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Commerce\StoreCouponRequest;
use App\Http\Resources\CouponResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class CouponController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()->can(Permission::ManageCommerce->value), 403);

        return CouponResource::collection(Coupon::query()->latest()->paginate(20));
    }

    public function store(StoreCouponRequest $request): JsonResponse
    {
        $coupon = Coupon::query()->create([
            'code' => $request->validated('code'),
            'type' => CouponType::from($request->validated('type')),
            'value' => (int) $request->validated('value'),
            'max_uses' => $request->validated('max_uses'),
            'expires_at' => $request->validated('expires_at'),
            'active' => (bool) $request->validated('active', true),
        ]);

        return (new CouponResource($coupon))->response()->setStatusCode(201);
    }
}
