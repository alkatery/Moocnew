<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Contexts\Identity\Domain\Permission;
use App\Contexts\Identity\Infrastructure\Persistence\ActivityLog;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only view over the append-only audit trail (PRD §5.ي): who did what
 * and when. Gated by users.manage.
 */
final class ActivityLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can(Permission::ManageUsers->value), 403);

        $event = $request->query('event');

        $logs = ActivityLog::query()
            ->with('causer:id,name')
            ->when(is_string($event) && $event !== '', fn ($q) => $q->where('event', $event))
            ->latest('id')
            ->paginate(30);

        $logs->getCollection()->transform(fn (ActivityLog $log): array => [
            'id' => $log->id,
            'event' => $log->event,
            'causer' => $log->causer?->name,
            'subject_type' => $log->subject_type ? class_basename($log->subject_type) : null,
            'subject_id' => $log->subject_id,
            'properties' => $log->properties,
            'created_at' => $log->created_at?->toIso8601String(),
        ]);

        return response()->json([
            'data' => $logs->items(),
            'meta' => ['current_page' => $logs->currentPage(), 'last_page' => $logs->lastPage()],
            'events' => ActivityLog::query()->distinct()->orderBy('event')->pluck('event'),
        ]);
    }
}
