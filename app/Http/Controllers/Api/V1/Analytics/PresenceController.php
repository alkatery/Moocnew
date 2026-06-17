<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Analytics;

use App\Contexts\Analytics\Infrastructure\PresenceTracker;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Records a learner heartbeat to feed the aggregate online counter
 * (PRD §5.ي). The client pings this periodically while active.
 */
final class PresenceController extends Controller
{
    public function heartbeat(Request $request, PresenceTracker $presence): JsonResponse
    {
        $presence->heartbeat($request->user()->getKey());

        return response()->json(['online_now' => $presence->onlineCount()]);
    }
}
