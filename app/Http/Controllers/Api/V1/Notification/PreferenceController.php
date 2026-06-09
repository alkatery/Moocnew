<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Notification;

use App\Contexts\Notification\Application\NotificationPreferences;
use App\Contexts\Notification\Domain\NotificationChannel;
use App\Contexts\Notification\Domain\NotificationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\UpdatePreferencesRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PreferenceController extends Controller
{
    public function index(Request $request, NotificationPreferences $preferences): JsonResponse
    {
        return response()->json([
            'data' => $preferences->matrix($request->user()),
        ]);
    }

    public function update(UpdatePreferencesRequest $request, NotificationPreferences $preferences): JsonResponse
    {
        foreach ($request->validated('preferences') as $row) {
            $preferences->set(
                $request->user(),
                NotificationType::from($row['type']),
                NotificationChannel::from($row['channel']),
                (bool) $row['enabled'],
            );
        }

        return response()->json([
            'data' => $preferences->matrix($request->user()),
        ]);
    }
}
