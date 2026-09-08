<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Log bruto de cada webhook recebido do Mercado Pago, usado para
 * idempotência (evitar processar o mesmo evento duas vezes) e para
 * reprocessamento manual em caso de erro.
 */
class WebhookMercadoPago extends Model
{
    protected $table = 'webhooks_mercadopago';

    protected $fillable = [
        'empresa_id', 'gateway_id', 'tipo', 'payload', 'status', 'tentativas', 'erro', 'processado_em',
    ];

    protected $casts = [
        'payload' => 'array',
        'processado_em' => 'datetime',
    ];

    public function scopePendentes($query)
    {
        return $query->where('status', 'recebido');
    }

    /**
     * Nula quando nenhuma empresa tem credencial própria configurada e o
     * webhook foi aceito pela credencial global da plataforma (ver
     * MercadoPagoWebhookController) — nesse caso o Job usa a credencial
     * padrão do .env pra consultar o pagamento.
     */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
