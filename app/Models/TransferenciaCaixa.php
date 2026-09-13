<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class TransferenciaCaixa extends Model
{
    use BelongsToTenant, HasFactory, LogsActivity;

    protected $table = 'transferencias_caixa';

    protected $fillable = [
        'empresa_id', 'caixa_origem_id', 'caixa_destino_id', 'valor', 'usuario_id',
        'observacao', 'movimentacao_saida_id', 'movimentacao_entrada_id',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function caixaOrigem(): BelongsTo
    {
        return $this->belongsTo(Caixa::class, 'caixa_origem_id');
    }

    public function caixaDestino(): BelongsTo
    {
        return $this->belongsTo(Caixa::class, 'caixa_destino_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function movimentacaoSaida(): BelongsTo
    {
        return $this->belongsTo(CaixaMovimentacao::class, 'movimentacao_saida_id');
    }

    public function movimentacaoEntrada(): BelongsTo
    {
        return $this->belongsTo(CaixaMovimentacao::class, 'movimentacao_entrada_id');
    }
}
