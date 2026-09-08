<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MovimentacaoEstoque extends Model
{
    use HasFactory;

    protected $table = 'movimentacoes_estoque';

    protected $fillable = [
        'produto_id', 'tipo', 'quantidade', 'quantidade_anterior', 'quantidade_atual',
        'motivo', 'referencia_type', 'referencia_id', 'usuario_id',
    ];

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
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
