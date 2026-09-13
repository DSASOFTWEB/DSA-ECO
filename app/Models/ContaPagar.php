<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ContaPagar extends Model
{
    use BelongsToTenant, HasFactory, LogsActivity, SoftDeletes;

    protected $table = 'contas_pagar';

    protected $fillable = [
        'empresa_id', 'unidade_id', 'fornecedor', 'descricao', 'categoria', 'valor',
        'data_vencimento', 'data_pagamento', 'status', 'forma_pagamento',
        'caixa_movimentacao_id', 'observacoes', 'criado_por_id',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'data_vencimento' => 'date',
        'data_pagamento' => 'date',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    public function caixaMovimentacao(): BelongsTo
    {
        return $this->belongsTo(CaixaMovimentacao::class);
    }

    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por_id');
    }

    public function estaPaga(): bool
    {
        return $this->status === 'pago';
    }

    public function estaAtrasada(): bool
    {
        return $this->status === 'pendente' && $this->data_vencimento->isPast();
    }
}
