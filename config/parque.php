<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Regras de negócio do domínio (Parque Aquático)
    |--------------------------------------------------------------------------
    */

    // Quantos dias antes do vencimento a primeira cobrança de lembrete é enviada
    'cobranca_dias_antes_vencimento' => (int) env('COBRANCA_DIAS_ANTES_VENCIMENTO', 3),

    // Dias após o vencimento em que novas tentativas de cobrança são disparadas (régua de cobrança)
    'cobranca_dias_apos_vencimento' => array_map(
        'intval',
        array_filter(explode(',', env('COBRANCA_DIAS_APOS_VENCIMENTO', '1,5,10')))
    ),

    // Validade (em horas) do QR Code exibido na carteirinha digital antes de precisar ser recarregado
    'carteirinha_qr_ttl_horas' => (int) env('CARTEIRINHA_QR_TTL_HORAS', 24),

    // Após quantos dias de atraso a mensalidade passa de "atrasado" para bloquear o acesso
    'dias_atraso_bloqueia_acesso' => (int) env('DIAS_ATRASO_BLOQUEIA_ACESSO', 5),

    /*
    |--------------------------------------------------------------------------
    | Impressão ESC/POS (cupom térmico)
    |--------------------------------------------------------------------------
    | colunas: 48 ≈ 80mm, 42 ≈ 72mm, 32 ≈ 58mm
    | agente_url: serviço local opcional (ex.: http://127.0.0.1:9110) no estilo
    | do agente USB do GestorWEB — POST /print com { lines|payload_base64, cut }
    */
    'escpos_colunas' => (int) env('ESCPOS_COLUNAS', 48),
    'escpos_agente_url' => env('ESCPOS_AGENTE_URL', 'http://127.0.0.1:9110'),

    /*
    |--------------------------------------------------------------------------
    | Proxies confiáveis (Cloudflare / Traefik / rede Docker)
    |--------------------------------------------------------------------------
    | Lista CSV de CIDRs, ou "*" se a origem NÃO for pública (só proxy).
    | Com Cloudflare na frente de softplanerp.com.br use o padrão abaixo
    | (faixas oficiais CF + rede privada Docker).
    */
    'trusted_proxies' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env(
            'TRUSTED_PROXIES',
            // Privadas (Docker/Traefik local)
            '10.0.0.0/8,172.16.0.0/12,192.168.0.0/16,'.
            // Cloudflare IPv4 (https://www.cloudflare.com/ips-v4)
            '173.245.48.0/20,103.21.244.0/22,103.22.200.0/22,103.31.4.0/22,'.
            '141.101.64.0/18,108.162.192.0/18,190.93.240.0/20,188.114.96.0/20,'.
            '197.234.240.0/22,198.41.128.0/17,162.158.0.0/15,104.16.0.0/13,'.
            '104.24.0.0/14,172.64.0.0/13,131.0.72.0/22'
        ))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Bluesoft Cosmos (consulta EAN/GTIN + imagem do produto)
    |--------------------------------------------------------------------------
    | Token gratuito em https://cosmos.bluesoft.com.br/api
    */
    'cosmos_token' => env('COSMOS_TOKEN', ''),
    'cosmos_base_url' => env('COSMOS_BASE_URL', 'https://api.cosmos.bluesoft.com.br'),
    'cosmos_timeout' => (int) env('COSMOS_TIMEOUT', 15),

    // Fallbacks de plataforma quando a empresa não cadastrou o próprio token
    'token_nfse' => env('TOKEN_NFSE', ''),
    'token_ibpt' => env('TOKEN_IBPT', ''),

    /*
    |--------------------------------------------------------------------------
    | NFS-e municipal GISS (ABRASF 2.04) — schemas XSD
    |--------------------------------------------------------------------------
    | Pasta com os XSD (cabecalho, enviar-lote-rps-envio, tipos-v2_04, xmldsig…).
    | Padrão: storage/SchemasXSDgiss
    */
    'nfse_giss_schemas_path' => env('NFSE_GISS_SCHEMAS_PATH', storage_path('SchemasXSDgiss')),
    'nfse_giss_validar_schema' => filter_var(env('NFSE_GISS_VALIDAR_SCHEMA', true), FILTER_VALIDATE_BOOL),
];
