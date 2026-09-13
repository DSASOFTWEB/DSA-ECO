<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PontoAtendimento extends Model
{
    use BelongsToTenant, HasFactory, LogsActivity;

    protected $table = 'pontos_atendimento';

    protected $fillable = [
        'empresa_id', 'unidade_id', 'tipo', 'numero', 'nome', 'capacidade', 'status', 'ordem',
    ];

    protected $casts = [
        'numero' => 'integer',
        'capacidade' => 'integer',
        'ordem' => 'integer',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    public function atendimentos(): HasMany
    {
        return $this->hasMany(Atendimento::class);
    }

    public function atendimentoAtual()
    {
        return $this->hasOne(Atendimento::class)->whereIn('status', ['aberto', 'pre_fechado'])->latestOfMany();
    }

    public function getIdentificacaoAttribute(): string
    {
        return $this->nome ?: ucfirst($this->tipo).' '.$this->numero;
    }
}
