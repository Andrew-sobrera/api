<?php

return [
    /*
    |--------------------------------------------------------------------------
    | QR Code writer
    |--------------------------------------------------------------------------
    |
    | svg — não requer extensão GD (recomendado para workers/CLI em produção)
    | png — requer php-gd habilitado no PHP-FPM e no PHP CLI do queue worker
    |
    */
    'qr_writer' => env('TICKET_QR_WRITER', 'svg'),
];
