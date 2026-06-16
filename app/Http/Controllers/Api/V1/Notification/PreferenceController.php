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
    /**
     * GET /api/v1/notifications/preferences
     *
     * يُعيد مصفوفة type×channel + تردّد الملخّص (D3).
     * digest.frequency = 'off' للمستخدمين القدامى غير الضابطين (القيمة الافتراضية).
     */
    public function index(Request $request, NotificationPreferences $preferences): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => $preferences->matrix($user),
            // D3: تردّد الملخّص — بُعد مستقلّ عن مصفوفة القنوات
            'digest' => [
                'frequency' => $user->digest_frequency ?? 'off',
            ],
        ]);
    }

    /**
     * PUT /api/v1/notifications/preferences
     *
     * يقبل preferences (مصفوفة القنوات) و/أو digest_frequency — كلاهما اختياري
     * لكن يجب وجود أحدهما (يُحقَّق في UpdatePreferencesRequest).
     * تبديل التردّد لا يلمس last_digest_at — تغيير التفضيل لا يُرسل/يُلغي ملخّصاً.
     */
    public function update(UpdatePreferencesRequest $request, NotificationPreferences $preferences): JsonResponse
    {
        $user = $request->user();

        // معالجة تردّد الملخّص إن وُجد (D3)
        if ($request->has('digest_frequency')) {
            $user->update(['digest_frequency' => $request->validated('digest_frequency')]);
        }

        // معالجة مصفوفة القنوات إن وُجدت (سلوك قائم — بلا كسر)
        foreach ($request->validated('preferences', []) as $row) {
            $preferences->set(
                $user,
                NotificationType::from($row['type']),
                NotificationChannel::from($row['channel']),
                (bool) $row['enabled'],
            );
        }

        // الاستجابة بنفس شكل index (مصفوفة + digest.frequency المُحدَّث)
        return response()->json([
            'data' => $preferences->matrix($user->fresh()),
            'digest' => [
                'frequency' => $user->fresh()->digest_frequency ?? 'off',
            ],
        ]);
    }
}
