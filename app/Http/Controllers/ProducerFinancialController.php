<?php

namespace App\Http\Controllers;

use App\Http\Requests\CalculatorRequest;
use App\Http\Resources\ProducerOrderResource;
use App\Services\Payments\AsaasRefundService;
use App\Services\ProducerBalanceReleaseService;
use App\Services\ProducerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProducerFinancialController extends Controller
{
    public function __construct(
        private readonly ProducerService $producerService,
        private readonly ProducerBalanceReleaseService $releaseService,
        private readonly AsaasRefundService $refundService,
    ) {
    }

    /**
     * Dashboard financeiro do produtor.
     * Busca saldo em tempo real do Asaas.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $producer = $request->user()->producer;

        abort_unless($producer, 403, 'Usuário não é um produtor.');

        $dashboard = $this->producerService->getFinancialDashboard($producer);
        $futureReleases = $this->releaseService->getFutureReleasesForProducer($producer);
        $pendingCents = $this->releaseService->getTotalPendingReleaseCents($producer);

        return response()->json([
            'data' => array_merge($dashboard, [
                'future_releases' => $futureReleases,
                'total_pending_release' => round($pendingCents / 100, 2),
            ]),
        ]);
    }

    /**
     * Lista pedidos dos eventos do produtor.
     */
    public function orders(Request $request): JsonResponse
    {
        $producer = $request->user()->producer;

        abort_unless($producer, 403, 'Usuário não é um produtor.');

        $orders = $this->producerService->getProducerOrders($producer, $request->only([
            'status', 'event_id', 'search', 'per_page',
        ]));

        return response()->json(ProducerOrderResource::collection($orders)->response()->getData(true));
    }

    /**
     * Cancela / estorna um pedido do evento do produtor.
     */
    public function refundOrder(Request $request, int $orderId): JsonResponse
    {
        $producer = $request->user()->producer;

        abort_unless($producer, 403, 'Usuário não é um produtor.');

        $order = \App\Models\Order::query()
            ->whereHas('event', fn ($q) => $q->where('producer_id', $producer->id))
            ->with(['user', 'event', 'reservations'])
            ->findOrFail($orderId);

        $this->refundService->refundOrder($order, $request->input('reason'));

        return response()->json(['message' => 'Pedido cancelado e estorno solicitado com sucesso.']);
    }

    /**
     * Calculadora financeira do produtor.
     */
    public function calculate(CalculatorRequest $request): JsonResponse
    {
        $producer = $request->user()->producer;

        abort_unless($producer, 403, 'Usuário não é um produtor.');

        $breakdown = $this->producerService->calculateBreakdown($producer, $request->validated());

        return response()->json(['data' => $breakdown]);
    }
}
