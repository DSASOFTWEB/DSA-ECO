<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Mensalidade extends Model
{
    use BelongsToTenant, HasFactory, LogsActivity;

    protected $fillable = [
        'contrato_id', 'empresa_id', 'competencia', 'valor_original', 'desconto',
        'acrescimo', 'valor_total', 'data_vencimento', 'data_pagamento', 'status',
        'forma_pagamento', 'gateway_transaction_id', 'observacoes',
    ];

    protected $casts = [
        'competencia' => 'date',
        'data_vencimento' => 'date',
        'data_pagamento' => 'date',
        'valor_original' => 'decimal:2',
        'desconto' => 'decimal:2',
        'acrescimo' => 'decimal:2',
        'valor_total' => 'decimal:2',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function cobrancas(): HasMany
    {
        return $this->hasMany(Cobranca::class);
    }

    public function pagamentos(): HasMany
    {
        return $this->hasMany(Pagamento::class);
    }

    public function scopePendentes($query)
    {
        return $query->whereIn('status', ['pendente', 'atrasado']);
    }

    public function scopeAtrasadas($query)
    {
        return $query->where('status', 'pendente')->where('data_vencimento', '<', now()->toDateString());
    }

    public function estaPaga(): bool
    {
        return $this->status === 'pago';
    }

    public function diasEmAtraso(): int
    {
        if ($this->estaPaga() || $this->data_vencimento->isFuture()) {
            return 0;
        }

        return (int) $this->data_vencimento->diffInDays(now());
    }
}
