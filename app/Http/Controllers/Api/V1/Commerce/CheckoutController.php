<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Commerce;

use App\Contexts\Catalog\Domain\Course\CourseStatus;
use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Commerce\Application\CheckoutService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Commerce\CheckoutRequest;
use App\Http\Resources\OrderResource;
use Illuminate\Http\JsonResponse;

final class CheckoutController extends Controller
{
    public function store(CheckoutRequest $request, CheckoutService $checkout): JsonResponse
    {
        $course = Course::query()->where('slug', $request->validated('course_slug'))->firstOrFail();
        abort_unless($course->status === CourseStatus::Published, 404);

        $order = $checkout->checkout($request->user(), $course, $request->validated('coupon'));

        return (new OrderResource($order))
            ->response()
            ->setStatusCode($order->wasRecentlyCreated ? 201 : 200);
    }
}
