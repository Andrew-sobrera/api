<?php

namespace App\Http\Controllers;

use App\Exceptions\BaseException;
use App\Jobs\ProcessAsaasWebhookJob;
use App\Services\PaymentWebhookAuditService;
use App\Support\QueueNames;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AsaasWebhookController extends Controller
{
    public function __construct(protected PaymentWebhookAuditService $auditService)
    {
    }

    public function handle(Request $request)
    {
        $token = config('asaas.webhook_token');

        if ($token && $request->header('asaas-access-token') !== $token) {
            throw new BaseException('Invalid webhook token.', 401);
        }

        $event = $request->input('event');
        $payment = $request->input('payment', []);
        $paymentId = $payment['id'] ?? null;

        if (! $paymentId) {
            return response()->json(['message' => 'Payment id missing'], 422);
        }

        $status = $this->mapEventToStatus($event, $payment['status'] ?? null);

        if (! $status) {
            Log::info('Asaas webhook ignored', [
                'event' => $event,
                'payment_id' => $paymentId,
            ]);

            $this->auditService->record($paymentId, $event, null, 'ignored', null, $request->all());

            return response()->json(['message' => 'Event ignored']);
        }

        if ($this->auditService->wasProcessed($paymentId, $event)) {
            return response()->json(['message' => 'Webhook already processed']);
        }

        $orderId = isset($payment['externalReference']) ? (int) $payment['externalReference'] : null;

        if (config('asaas.webhook_process_sync')) {
            app(\App\Services\PaymentWebhookService::class)->process(
                $paymentId,
                $status,
                $orderId ?: null,
                $event,
                $request->all(),
            );

            return response()->json(['message' => 'Webhook processed']);
        }

        ProcessAsaasWebhookJob::dispatch($paymentId, $status, $orderId ?: null, $event, $request->all())
            ->onConnection(config('queue.default'))
            ->onQueue(QueueNames::PAYMENTS_WEBHOOK);

        $this->auditService->record($paymentId, $event, $status, 'accepted', $orderId ?: null, $request->all());

        return response()->json(['message' => 'Webhook accepted']);
    }

    private function mapEventToStatus(?string $event, ?string $paymentStatus): ?string
    {
        return match ($event) {
            'PAYMENT_CONFIRMED' => 'CONFIRMED',
            'PAYMENT_RECEIVED' => 'RECEIVED',
            'PAYMENT_OVERDUE', 'PAYMENT_DELETED', 'PAYMENT_REFUNDED' => 'FAILED',
            default => $paymentStatus,
        };
    }
}
