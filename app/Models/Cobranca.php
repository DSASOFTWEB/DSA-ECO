<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cobranca extends Model
{
    use HasFactory;

    protected $fillable = [
        'mensalidade_id', 'canal', 'status', 'tentativa', 'mensagem',
        'resposta_gateway', 'enviado_em',
    ];

    protected $casts = [
        'resposta_gateway' => 'array',
        'enviado_em' => 'datetime',
    ];

    public function mensalidade(): BelongsTo
    {
        return $this->belongsTo(Mensalidade::class);
    }
}
