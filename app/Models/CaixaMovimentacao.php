<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CaixaMovimentacao extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'caixa_movimentacoes';

    protected $fillable = [
        'caixa_id', 'tipo', 'categoria', 'descricao', 'valor', 'forma_pagamento',
        'referencia_type', 'referencia_id', 'usuario_id', 'estornado_em', 'estornado_por_id',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'estornado_em' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function caixa(): BelongsTo
    {
        return $this->belongsTo(Caixa::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function estornadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'estornado_por_id');
    }

    public function referencia(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Não tem BelongsToTenant/empresa_id própria (mesma situação de
     * Acesso) — todo lugar que lista movimentações de várias caixas
     * precisa filtrar explicitamente pela empresa via este scope.
     */
    public function scopeDaEmpresa(Builder $query, int $empresaId): Builder
    {
        return $query->whereHas('caixa', fn ($q) => $q->where('empresa_id', $empresaId));
    }

    public function estaEstornada(): bool
    {
        return $this->estornado_em !== null;
    }

    /**
     * Só lançamentos manuais (sangria/suprimento/despesa/ajuste avulsos,
     * lançados direto na tela do caixa) podem ser editados/estornados por
     * aqui. Lançamentos com origem automática (venda, mensalidade,
     * hospedagem, conta a pagar/receber, transferência) ficam de fora pra
     * não dessincronizar do registro que os gerou — o jeito certo de
     * desfazer esses é cancelar/estornar na tela de origem.
     */
    public function eManual(): bool
    {
        return $this->referencia_type === null;
    }

    public function podeEditar(): bool
    {
        return $this->eManual() && ! $this->estaEstornada() && $this->caixa->estaAberto();
    }

    public function podeEstornar(): bool
    {
        return $this->eManual() && ! $this->estaEstornada() && $this->caixa->estaAberto();
    }
}
