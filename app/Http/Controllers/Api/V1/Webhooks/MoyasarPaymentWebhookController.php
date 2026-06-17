<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Contexts\Commerce\Application\PaymentProcessor;
use App\Contexts\Commerce\Domain\PaymentGateway;
use App\Contexts\Commerce\Infrastructure\Persistence\Payment;
use App\Contexts\Enrollment\Infrastructure\Persistence\WebhookEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;

/**
 * Receives Moyasar payment webhooks (PRD §5.د, §8): signature-verified and
 * idempotent. A duplicate delivery is acknowledged without reprocessing.
 */
final class MoyasarPaymentWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentGateway $gateway, PaymentProcessor $processor): JsonResponse
    {
        $signature = (string) $request->header('X-Moyasar-Signature', '');
        abort_unless($gateway->verifySignature($request->getContent(), $signature), 401, 'Invalid signature.');

        $ref = (string) ($request->input('data.id') ?? $request->input('id', ''));
        $status = (string) ($request->input('data.status') ?? $request->input('status', ''));
        abort_if($ref === '' || $status === '', 422, 'Malformed webhook payload.');

        $event = WebhookEvent::query()->firstOrNew([
            'provider' => 'moyasar',
            'external_id' => "{$ref}:{$status}",
        ]);

        if ($event->exists) {
            return response()->json(['status' => 'duplicate']);
        }

        $event->payload = $request->all();
        $event->processed_at = Date::now();
        $event->save();

        if ($status === 'paid') {
            $payment = Payment::query()->where('gateway_ref', $ref)->first();
            if ($payment !== null) {
                $processor->markPaid($payment);
            }
        }

        return response()->json(['status' => 'ok']);
    }
}
