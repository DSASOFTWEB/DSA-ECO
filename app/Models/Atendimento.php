<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Atendimento extends Model
{
    use BelongsToTenant, HasFactory, LogsActivity;

    protected $fillable = [
        'empresa_id', 'unidade_id', 'ponto_atendimento_id', 'cliente_id', 'aberto_por_id',
        'fechado_por_id', 'venda_id', 'status', 'quantidade_pessoas', 'subtotal', 'desconto',
        'percentual_servico', 'valor_servico', 'valor_total', 'observacao', 'aberto_em',
        'pre_fechado_em', 'fechado_em',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'desconto' => 'decimal:2',
        'percentual_servico' => 'decimal:2',
        'valor_servico' => 'decimal:2',
        'valor_total' => 'decimal:2',
        'aberto_em' => 'datetime',
        'pre_fechado_em' => 'datetime',
        'fechado_em' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function ponto(): BelongsTo
    {
        return $this->belongsTo(PontoAtendimento::class, 'ponto_atendimento_id');
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function abertoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aberto_por_id');
    }

    public function fechadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fechado_por_id');
    }

    public function venda(): BelongsTo
    {
        return $this->belongsTo(Venda::class);
    }

    public function itens(): HasMany
    {
        return $this->hasMany(AtendimentoItem::class);
    }

    public function itensAtivos(): HasMany
    {
        return $this->itens()->where('status', 'ativo');
    }

    public function estaEmAndamento(): bool
    {
        return in_array($this->status, ['aberto', 'pre_fechado'], true);
    }
}
