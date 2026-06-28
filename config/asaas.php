<?php

return [
    'api_key' => env('ASAAS_API_KEY'),
    'url' => rtrim(env('ASAAS_URL', 'https://sandbox.asaas.com/api/v3'), '/'),
    'webhook_token' => env('ASAAS_WEBHOOK_TOKEN'),
    'webhook_process_sync' => filter_var(env('ASAAS_WEBHOOK_PROCESS_SYNC', false), FILTER_VALIDATE_BOOL),
    'billing_type' => env('ASAAS_BILLING_TYPE', 'PIX'),
    'payment_due_days' => (int) env('ASAAS_PAYMENT_DUE_DAYS', 1),

    // Wallet ID da conta principal da plataforma para split
    'platform_wallet_id' => env('ASAAS_PLATFORM_WALLET_ID'),

    // Comissão padrão da plataforma (%)
    'default_commission_percentage' => (float) env('ASAAS_DEFAULT_COMMISSION_PERCENTAGE', 5.0),

    // Timeout das requisições HTTP (segundos)
    'timeout' => (int) env('ASAAS_TIMEOUT', 30),
    'retry_times' => (int) env('ASAAS_RETRY_TIMES', 3),
    'retry_sleep' => (int) env('ASAAS_RETRY_SLEEP', 1000),

    // Bypass da validação Asaas (somente sandbox/dev)
    'bypass_producer_validation' => filter_var(env('ASAAS_BYPASS_PRODUCER_VALIDATION', false), FILTER_VALIDATE_BOOL),
];
