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

    /*
    |--------------------------------------------------------------------------
    | QR Code size (pixels)
    |--------------------------------------------------------------------------
    |
    | Tamanho do QR na geração (largura/altura). Valores maiores melhoram
    | legibilidade na impressão e leitura na entrada do evento.
    |
    */
    'qr_size' => (int) env('TICKET_QR_SIZE', 800),
];
