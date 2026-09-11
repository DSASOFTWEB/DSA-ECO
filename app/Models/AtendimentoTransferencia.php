<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class AtendimentoTransferencia extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'empresa_id', 'atendimento_origem_id', 'atendimento_destino_id', 'ponto_origem_id',
        'ponto_destino_id', 'usuario_id', 'tipo', 'detalhes',
    ];

    protected $casts = ['detalhes' => 'array'];
}
