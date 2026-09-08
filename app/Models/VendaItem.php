<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendaItem extends Model
{
    use HasFactory;

    protected $table = 'venda_itens';

    protected $fillable = [
        'venda_id', 'produto_id', 'tipo_entrada_id', 'quantidade', 'preco_unitario', 'desconto', 'subtotal',
    ];

    protected $casts = [
        'preco_unitario' => 'decimal:2',
        'desconto' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function venda(): BelongsTo
    {
        return $this->belongsTo(Venda::class);
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }

    public function tipoEntrada(): BelongsTo
    {
        return $this->belongsTo(TipoEntrada::class);
    }

    /**
     * Nome de exibição do item, seja ele um produto de estoque ou uma
     * entrada avulsa (as duas fontes são mutuamente exclusivas).
     */
    public function nomeItem(): string
    {
        return $this->produto->nome ?? $this->tipoEntrada->nome;
    }
}
