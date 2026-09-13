<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Hospedagem extends Model
{
    use BelongsToTenant, HasFactory, LogsActivity, SoftDeletes;

    // O inflector do Laravel não conhece plural em português: sem isso ele
    // tentaria usar a tabela "hospedagems" em vez de "hospedagens".
    protected $table = 'hospedagens';

    protected $fillable = [
        'empresa_id', 'unidade_id', 'quarto_id', 'cliente_id', 'quantidade_hospedes',
        'quantidade_adultos', 'quantidade_criancas', 'quantidade_isentos',
        'valor_diaria', 'data_checkin_prevista', 'data_checkout_prevista',
        'data_checkin_real', 'data_checkout_real', 'valor_total', 'desconto',
        'forma_pagamento', 'status', 'observacoes', 'caixa_id', 'registrado_por_id',
    ];

    protected $casts = [
        'quantidade_hospedes' => 'integer',
        'quantidade_adultos' => 'integer',
        'quantidade_criancas' => 'integer',
        'quantidade_isentos' => 'integer',
        'valor_diaria' => 'decimal:2',
        'valor_total' => 'decimal:2',
        'desconto' => 'decimal:2',
        'data_checkin_prevista' => 'date',
        'data_checkout_prevista' => 'date',
        'data_checkin_real' => 'datetime',
        'data_checkout_real' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function quarto(): BelongsTo
    {
        return $this->belongsTo(Quarto::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function caixa(): BelongsTo
    {
        return $this->belongsTo(Caixa::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_id');
    }

    public function consumos(): HasMany
    {
        return $this->hasMany(HospedagemConsumo::class);
    }

    /**
     * Conta noites como uma pousada conta: pela diferença de DATA (dia
     * civil) entre entrada e saída, ignorando a hora — check-in dia 11 às
     * 15h e check-out dia 13 às 00h11 são 2 noites (passou a noite de
     * 11→12 e a de 12→13), não "1,38 dia" (o cálculo ingênuo de horas
     * decorridas ÷ 24, que é o que `diffInDays` entre dois horários
     * diferentes devolve nesta versão do Carbon — sem o `startOfDay()` em
     * ambos os lados, cobrança e indicadores saíam errados).
     */
    public static function contarNoites(\Illuminate\Support\Carbon $checkin, \Illuminate\Support\Carbon $checkout): int
    {
        return max(1, $checkin->copy()->startOfDay()->diffInDays($checkout->copy()->startOfDay()));
    }

    public function estaReservado(): bool
    {
        return $this->status === 'reservado';
    }

    public function estaHospedado(): bool
    {
        return $this->status === 'hospedado';
    }

    public function estaFinalizado(): bool
    {
        return $this->status === 'finalizado';
    }

    public function estaCancelado(): bool
    {
        return $this->status === 'cancelado';
    }
}
