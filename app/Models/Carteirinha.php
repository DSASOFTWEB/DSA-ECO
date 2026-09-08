<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Carteirinha extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'cliente_id', 'dependente_id', 'codigo', 'status', 'emitida_em',
        'expira_em', 'motivo_bloqueio',
    ];

    protected $casts = [
        'emitida_em' => 'datetime',
        'expira_em' => 'date',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function dependente(): BelongsTo
    {
        return $this->belongsTo(Dependente::class);
    }

    public function acessos(): HasMany
    {
        return $this->hasMany(Acesso::class);
    }

    /**
     * Retorna o titular (Cliente ou Dependente) desta carteirinha.
     */
    public function titular(): Cliente|Dependente|null
    {
        return $this->cliente_id ? $this->cliente : $this->dependente;
    }

    public function estaAtiva(): bool
    {
        return $this->status === 'ativa';
    }
}
