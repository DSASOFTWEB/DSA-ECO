<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CobrancaPix extends Model
{
    use BelongsToTenant;

    public const STATUS_PENDENTE = 'pendente';

    public const STATUS_PAGA = 'paga';

    public const STATUS_CANCELADA = 'cancelada';

    public const STATUS_EXPIRADA = 'expirada';

    protected $table = 'cobrancas_pix';

    protected $fillable = [
        'empresa_id', 'caixa_id', 'usuario_id', 'venda_id', 'gateway', 'txid', 'valor',
        'status', 'pix_copia_e_cola', 'dados_venda', 'payload', 'e2eid', 'erro',
        'expira_em', 'pago_em',
    ];

    protected $hidden = ['payload', 'dados_venda'];

    protected $casts = [
        'valor' => 'decimal:2',
        'dados_venda' => 'array',
        'payload' => 'array',
        'expira_em' => 'datetime',
        'pago_em' => 'datetime',
    ];

    public function caixa(): BelongsTo
    {
        return $this->belongsTo(Caixa::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function venda(): BelongsTo
    {
        return $this->belongsTo(Venda::class);
    }

    public function estaPendente(): bool
    {
        return $this->status === self::STATUS_PENDENTE;
    }

    public function estaPaga(): bool
    {
        return $this->status === self::STATUS_PAGA;
    }
}
