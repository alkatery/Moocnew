<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Scheduling;

use App\Contexts\Scheduling\Application\CalendarService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

final class CalendarController extends Controller
{
    public function index(Request $request, CalendarService $calendar): JsonResponse
    {
        $from = $request->filled('from') ? Carbon::parse($request->query('from')) : Carbon::now()->startOfDay();
        $to = $request->filled('to') ? Carbon::parse($request->query('to')) : $from->copy()->addMonth();

        return response()->json([
            'data' => $calendar->forUser($request->user(), $from, $to),
        ]);
    }
}
