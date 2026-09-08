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
];
