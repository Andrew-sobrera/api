<?php

namespace App\Console\Commands;

use App\Services\CartExpirationService;
use Illuminate\Console\Command;

class ExpireDueCartsCommand extends Command
{
    protected $signature = 'carts:expire-due';

    protected $description = 'Expire shopping carts past their TTL and release held stock';

    public function handle(CartExpirationService $cartExpirationService): int
    {
        $cartExpirationService->expireDueCarts();

        $this->info('Expired due shopping carts.');

        return self::SUCCESS;
    }
}
