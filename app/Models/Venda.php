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

class Venda extends Model
{
    use BelongsToTenant, HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'empresa_id', 'unidade_id', 'cliente_id', 'vendedor_id', 'caixa_id',
        'valor_bruto', 'desconto', 'valor_total', 'status', 'forma_pagamento', 'observacao',
    ];

    protected $casts = [
        'valor_bruto' => 'decimal:2',
        'desconto' => 'decimal:2',
        'valor_total' => 'decimal:2',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendedor_id');
    }

    public function caixa(): BelongsTo
    {
        return $this->belongsTo(Caixa::class);
    }

    public function itens(): HasMany
    {
        return $this->hasMany(VendaItem::class);
    }

    public function pagamentos(): HasMany
    {
        return $this->hasMany(Pagamento::class);
    }

    public function comissoes(): HasMany
    {
        return $this->hasMany(Comissao::class);
    }

    public function acessos(): HasMany
    {
        return $this->hasMany(Acesso::class);
    }

    /**
     * Uma venda é uma entrada avulsa quando pelo menos um item vendido é um
     * tipo de entrada (ingresso), em vez de um produto de estoque.
     */
    public function ehEntradaAvulsa(): bool
    {
        return $this->itens->contains(fn (VendaItem $item) => $item->tipo_entrada_id !== null);
    }
}
