<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Pagamento extends Model
{
    use BelongsToTenant, HasFactory, LogsActivity;

    protected $fillable = [
        'empresa_id', 'mensalidade_id', 'venda_id', 'gateway', 'gateway_payment_id',
        'valor', 'status', 'metodo_pagamento', 'payload', 'pago_em',
    ];

    protected $casts = [
        'payload' => 'array',
        'valor' => 'decimal:2',
        'pago_em' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function mensalidade(): BelongsTo
    {
        return $this->belongsTo(Mensalidade::class);
    }

    public function venda(): BelongsTo
    {
        return $this->belongsTo(Venda::class);
    }

    public function movimentacoesCaixa(): MorphMany
    {
        return $this->morphMany(CaixaMovimentacao::class, 'referencia');
    }

    public function estaAprovado(): bool
    {
        return $this->status === 'aprovado';
    }
}
