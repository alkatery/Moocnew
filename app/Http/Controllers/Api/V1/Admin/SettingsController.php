<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Contexts\Identity\Application\ActivityLogger;
use App\Contexts\Identity\Domain\Permission;
use App\Contexts\Platform\Application\FeatureFlags;
use App\Contexts\Platform\Domain\Settings\SettingKey;
use App\Contexts\Platform\Domain\Settings\SettingsRepository;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAssistantRequest;
use App\Http\Requests\Admin\UpdatePaymentsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Super Admin settings panel (PRD §4): viewing platform settings and
 * flipping the payments master switch that activates the Commerce context.
 */
final class SettingsController extends Controller
{
    public function show(Request $request, FeatureFlags $features): JsonResponse
    {
        abort_unless($request->user()->can(Permission::ManageSettings->value), 403);

        return response()->json([
            'payments_enabled' => $features->paymentsEnabled(),
            'assistant_mode' => $features->assistantMode(),
        ]);
    }

    public function updateAssistant(
        UpdateAssistantRequest $request,
        SettingsRepository $settings,
        ActivityLogger $activity,
    ): JsonResponse {
        $mode = $request->validated('mode');

        $settings->set(SettingKey::AssistantMode, $mode);

        $activity->log('settings.assistant_mode_changed', $request->user(), properties: [
            'mode' => $mode,
        ]);

        return response()->json(['assistant_mode' => $mode]);
    }

    public function updatePayments(
        UpdatePaymentsRequest $request,
        SettingsRepository $settings,
        ActivityLogger $activity,
    ): JsonResponse {
        $enabled = $request->validated('enabled');

        $settings->set(SettingKey::PaymentsEnabled, $enabled);

        $activity->log('settings.payments_toggled', $request->user(), properties: [
            'enabled' => $enabled,
        ]);

        return response()->json([
            'payments_enabled' => $enabled,
        ]);
    }
}
