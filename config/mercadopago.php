<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Mercado Pago — configuração de integração (Checkout Pro / Pix / Webhook)
    |--------------------------------------------------------------------------
    | Documentação: https://www.mercadopago.com.br/developers
    */

    'access_token' => env('MERCADOPAGO_ACCESS_TOKEN', ''),
    'public_key' => env('MERCADOPAGO_PUBLIC_KEY', ''),

    // Usado para validar a assinatura (x-signature) enviada nos webhooks
    'webhook_secret' => env('MERCADOPAGO_WEBHOOK_SECRET', ''),

    'timeout' => (int) env('MERCADOPAGO_TIMEOUT', 15),
    'retries' => (int) env('MERCADOPAGO_RETRIES', 3),
    'retry_delay_ms' => (int) env('MERCADOPAGO_RETRY_DELAY_MS', 500),

    'base_url' => 'https://api.mercadopago.com',
];
