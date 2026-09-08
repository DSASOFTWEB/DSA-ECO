<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CaixaMovimentacao extends Model
{
    use HasFactory;

    protected $table = 'caixa_movimentacoes';

    protected $fillable = [
        'caixa_id', 'tipo', 'categoria', 'descricao', 'valor', 'forma_pagamento',
        'referencia_type', 'referencia_id', 'usuario_id',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
    ];

    public function caixa(): BelongsTo
    {
        return $this->belongsTo(Caixa::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function referencia(): MorphTo
    {
        return $this->morphTo();
    }
}
