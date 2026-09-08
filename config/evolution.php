<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Evolution API (WhatsApp) — configuração de integração
    |--------------------------------------------------------------------------
    | Documentação: https://doc.evolution-api.com
    | A Evolution API expõe uma instância própria por número de WhatsApp
    | conectado; cada empresa/unidade pode, no futuro, ter sua própria
    | instância (hoje o SaaS usa uma instância única compartilhada, definida
    | em EVOLUTION_API_INSTANCE).
    */

    'base_url' => rtrim(env('EVOLUTION_API_URL', ''), '/'),
    'api_key' => env('EVOLUTION_API_KEY', ''),
    'instance' => env('EVOLUTION_API_INSTANCE', 'default'),

    // Timeout de conexão/resposta em segundos
    'timeout' => (int) env('EVOLUTION_API_TIMEOUT', 15),

    // Tentativas de reenvio em caso de falha de rede/5xx, com backoff exponencial
    'retries' => (int) env('EVOLUTION_API_RETRIES', 3),
    'retry_delay_ms' => (int) env('EVOLUTION_API_RETRY_DELAY_MS', 500),
];
