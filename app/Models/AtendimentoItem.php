<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AtendimentoItem extends Model
{
    use HasFactory;

    protected $table = 'atendimento_itens';

    protected $fillable = [
        'atendimento_id', 'produto_id', 'lancado_por_id', 'cancelado_por_id', 'quantidade',
        'preco_unitario', 'desconto', 'subtotal', 'status', 'observacao',
        'motivo_cancelamento', 'cancelado_em',
    ];

    protected $casts = [
        'preco_unitario' => 'decimal:2',
        'desconto' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'cancelado_em' => 'datetime',
    ];

    public function atendimento(): BelongsTo
    {
        return $this->belongsTo(Atendimento::class);
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }
}
