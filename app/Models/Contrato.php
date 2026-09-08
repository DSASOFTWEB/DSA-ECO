<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Contrato extends Model
{
    use BelongsToTenant, HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'empresa_id', 'unidade_id', 'cliente_id', 'plano_id', 'vendedor_id',
        'numero_contrato', 'data_inicio', 'data_fim', 'dia_vencimento',
        'valor_mensal', 'desconto_percentual', 'status', 'motivo_cancelamento', 'cancelado_em', 'reativado_em',
    ];

    protected $casts = [
        'data_inicio' => 'date',
        'data_fim' => 'date',
        'cancelado_em' => 'datetime',
        'reativado_em' => 'datetime',
        'valor_mensal' => 'decimal:2',
        'desconto_percentual' => 'decimal:2',
        'dia_vencimento' => 'integer',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function plano(): BelongsTo
    {
        return $this->belongsTo(Plano::class);
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendedor_id');
    }

    public function dependentes(): BelongsToMany
    {
        return $this->belongsToMany(Dependente::class, 'contrato_dependente');
    }

    public function mensalidades(): HasMany
    {
        return $this->hasMany(Mensalidade::class);
    }

    public function comissoes(): HasMany
    {
        return $this->hasMany(Comissao::class);
    }

    public function scopeAtivos($query)
    {
        return $query->where('status', 'ativo');
    }

    public function estaAtivo(): bool
    {
        return $this->status === 'ativo';
    }

    public function estaCancelado(): bool
    {
        return $this->status === 'cancelado';
    }
}
