<?php

namespace App\Http\Controllers;

use App\Exceptions\BaseException;
use App\Jobs\ProcessAsaasWebhookJob;
use App\Support\QueueNames;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AsaasWebhookController extends Controller
{
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

            return response()->json(['message' => 'Event ignored']);
        }

        ProcessAsaasWebhookJob::dispatch($paymentId, $status)
            ->onConnection(config('queue.default'))
            ->onQueue(QueueNames::PAYMENTS_WEBHOOK);

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
