<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Commerce;

use App\Contexts\Commerce\Application\RefundService;
use App\Contexts\Commerce\Infrastructure\Persistence\Order;
use App\Contexts\Identity\Domain\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class OrderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return OrderResource::collection(
            Order::query()->where('user_id', $request->user()->getKey())->latest()->paginate(15),
        );
    }

    public function refund(Request $request, Order $order, RefundService $refunds): OrderResource
    {
        abort_unless($request->user()->can(Permission::ManageCommerce->value), 403);

        return new OrderResource($refunds->refund($order));
    }
}
