<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Commerce;

use App\Contexts\Commerce\Application\PayoutService;
use App\Contexts\Commerce\Infrastructure\Persistence\PayoutRequest as PayoutModel;
use App\Contexts\Identity\Domain\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Commerce\RequestPayoutRequest;
use App\Http\Resources\PayoutResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class PayoutController extends Controller
{
    public function store(RequestPayoutRequest $request, PayoutService $payouts): JsonResponse
    {
        $payout = $payouts->request($request->user(), (int) $request->validated('amount_minor'));

        return (new PayoutResource($payout))->response()->setStatusCode(201);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = PayoutModel::query()->latest();

        // Admins see all; instructors see only their own.
        if (! $request->user()->can(Permission::ManageCommerce->value)) {
            $query->where('instructor_id', $request->user()->getKey());
        }

        return PayoutResource::collection($query->paginate(20));
    }

    public function approve(Request $request, PayoutModel $payout, PayoutService $payouts): PayoutResource
    {
        abort_unless($request->user()->can(Permission::ManageCommerce->value), 403);

        return new PayoutResource($payouts->approve($payout, $request->user()));
    }

    public function reject(Request $request, PayoutModel $payout, PayoutService $payouts): PayoutResource
    {
        abort_unless($request->user()->can(Permission::ManageCommerce->value), 403);

        return new PayoutResource($payouts->reject($payout, $request->user(), $request->input('note')));
    }

    public function pay(Request $request, PayoutModel $payout, PayoutService $payouts): PayoutResource
    {
        abort_unless($request->user()->can(Permission::ManageCommerce->value), 403);

        return new PayoutResource($payouts->markPaid($payout, $request->user()));
    }
}
