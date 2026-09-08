<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HospedagemConsumo extends Model
{
    use HasFactory;

    protected $table = 'hospedagem_consumos';

    protected $fillable = [
        'hospedagem_id', 'produto_id', 'descricao', 'quantidade', 'valor_unitario', 'subtotal', 'registrado_por_id',
    ];

    protected $casts = [
        'quantidade' => 'integer',
        'valor_unitario' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function hospedagem(): BelongsTo
    {
        return $this->belongsTo(Hospedagem::class);
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_id');
    }

    public function nomeItem(): string
    {
        return $this->produto->nome ?? $this->descricao ?? 'Item avulso';
    }
}
