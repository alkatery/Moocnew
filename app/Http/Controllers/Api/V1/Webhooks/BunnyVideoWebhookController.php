<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Contexts\Enrollment\Application\VideoStatusUpdater;
use App\Contexts\Enrollment\Infrastructure\Persistence\WebhookEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;

/**
 * Receives Bunny Stream video status webhooks (PRD §5.ب, §8). The request
 * signature is verified against a shared secret and the event is recorded
 * for idempotency, so retried deliveries are processed at most once.
 */
final class BunnyVideoWebhookController extends Controller
{
    public function __invoke(Request $request, VideoStatusUpdater $updater): JsonResponse
    {
        $this->verifySignature($request);

        $videoGuid = (string) $request->input('VideoGuid', $request->input('video_guid', ''));
        $status = (int) $request->input('Status', $request->input('status', -1));

        abort_if($videoGuid === '' || $status < 0, 422, 'Malformed webhook payload.');

        $externalId = "{$videoGuid}:{$status}";

        // Idempotency: a unique (provider, external_id) means a duplicate
        // delivery is acknowledged without reprocessing.
        $event = WebhookEvent::query()->firstOrNew([
            'provider' => 'bunny',
            'external_id' => $externalId,
        ]);

        if ($event->exists) {
            return response()->json(['status' => 'duplicate'], 200);
        }

        $event->payload = $request->all();
        $event->processed_at = Date::now();
        $event->save();

        $updater->fromBunny($videoGuid, $status);

        return response()->json(['status' => 'ok']);
    }

    private function verifySignature(Request $request): void
    {
        $secret = config('video.bunny.webhook_secret');

        abort_if(empty($secret), 503, 'Bunny webhook secret is not configured.');

        $provided = (string) $request->header('X-Bunny-Signature', '');
        $expected = hash_hmac('sha256', $request->getContent(), (string) $secret);

        abort_unless($provided !== '' && hash_equals($expected, $provided), 401, 'Invalid signature.');
    }
}
