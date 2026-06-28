<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Order;
use App\Models\Producer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Regras de liberação de saldo do produtor.
 *
 * Regra: Ingressos vendidos só podem ser sacados 1 dia após o evento.
 *
 * O saldo em si é gerenciado pelo Asaas (bloqueio de D+1 ou D+2 a partir
 * da data do evento). Este serviço é responsável por:
 *  - Calcular quando os fundos de um evento serão liberados
 *  - Verificar se um evento já passou do período de liberação
 *  - Fornecer dados para o dashboard financeiro
 */
class ProducerBalanceReleaseService
{
    private const RELEASE_DAYS_AFTER_EVENT = 1;

    /**
     * Data em que os fundos de um evento serão liberados para saque.
     */
    public function getReleaseDate(Event $event): Carbon
    {
        return Carbon::parse($event->date)
            ->addDays(self::RELEASE_DAYS_AFTER_EVENT)
            ->startOfDay();
    }

    /**
     * Verifica se os fundos de um evento já estão liberados.
     */
    public function isReleased(Event $event): bool
    {
        return now()->gte($this->getReleaseDate($event));
    }

    /**
     * Quantidade de dias restantes para liberação.
     * Retorna 0 se já liberado.
     */
    public function daysUntilRelease(Event $event): int
    {
        $releaseDate = $this->getReleaseDate($event);

        if (now()->gte($releaseDate)) {
            return 0;
        }

        return (int) now()->diffInDays($releaseDate, false);
    }

    /**
     * Retorna os valores futuros a liberar de um produtor,
     * agrupados por evento.
     */
    public function getFutureReleasesForProducer(Producer $producer): array
    {
        $futureEvents = Event::query()
            ->where('producer_id', $producer->id)
            ->where('date', '>', now()->subDays(self::RELEASE_DAYS_AFTER_EVENT))
            ->with(['orders' => function ($q) {
                $q->where('payment_status', 'PAID')
                    ->select(['id', 'event_id', 'producer_amount', 'payment_status']);
            }])
            ->get();

        $releases = [];

        foreach ($futureEvents as $event) {
            $totalProducerAmount = $event->orders->sum('producer_amount');

            if ($totalProducerAmount <= 0) {
                continue;
            }

            $releases[] = [
                'event_id' => $event->id,
                'event_name' => $event->name,
                'event_date' => $event->date,
                'release_date' => $this->getReleaseDate($event)->toDateString(),
                'is_released' => $this->isReleased($event),
                'days_until_release' => $this->daysUntilRelease($event),
                'producer_amount_cents' => $totalProducerAmount,
                'producer_amount' => round($totalProducerAmount / 100, 2),
            ];
        }

        usort($releases, fn ($a, $b) => strcmp($a['release_date'], $b['release_date']));

        Log::debug('[ProducerBalanceReleaseService] Liberações futuras calculadas', [
            'producer_id' => $producer->id,
            'total_events' => count($releases),
        ]);

        return $releases;
    }

    /**
     * Valor total aguardando liberação (em centavos).
     */
    public function getTotalPendingReleaseCents(Producer $producer): int
    {
        return (int) Order::query()
            ->whereHas('event', fn ($q) => $q->where('producer_id', $producer->id)
                ->where('date', '>', now()->subDays(self::RELEASE_DAYS_AFTER_EVENT)))
            ->where('payment_status', 'PAID')
            ->sum('producer_amount');
    }
}
