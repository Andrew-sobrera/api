<?php

namespace App\Console\Commands;

use App\Enums\PaymentMethod;
use App\Jobs\CreatePaymentJob;
use App\Models\Order;
use App\Support\QueueNames;
use Illuminate\Console\Command;

class ReprocessPendingPaymentsCommand extends Command
{
    protected $signature = 'orders:reprocess-pending-payments';

    protected $description = 'Re-dispatch payment jobs for pending PIX orders without payment id';

    public function handle(): int
    {
        $orders = Order::query()
            ->whereNull('asaas_payment_id')
            ->where('payment_method', PaymentMethod::PIX)
            ->get();

        if ($orders->isEmpty()) {
            $this->components->info('No pending PIX orders found.');

            return self::SUCCESS;
        }

        foreach ($orders as $order) {
            CreatePaymentJob::dispatch($order->id)
                ->onConnection(config('queue.default'))
                ->onQueue(QueueNames::PAYMENTS_CREATE);

            $this->line("Dispatched payment job for order #{$order->id}");
        }

        $this->components->info("Dispatched {$orders->count()} payment job(s).");

        return self::SUCCESS;
    }
}
