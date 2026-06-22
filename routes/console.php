<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('orders:reprocess-pending-payments')->everyFiveMinutes();
Schedule::command('reservations:expire-due')->everyMinute();
Schedule::command('carts:expire-due')->everyMinute();
