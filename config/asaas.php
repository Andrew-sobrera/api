<?php

return [
    'api_key' => env('ASAAS_API_KEY'),
    'url' => rtrim(env('ASAAS_URL', 'https://sandbox.asaas.com/api/v3'), '/'),
    'webhook_token' => env('ASAAS_WEBHOOK_TOKEN'),
    'billing_type' => env('ASAAS_BILLING_TYPE', 'PIX'),
    'payment_due_days' => (int) env('ASAAS_PAYMENT_DUE_DAYS', 1),
];
