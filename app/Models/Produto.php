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

class Produto extends Model
{
    use BelongsToTenant, HasFactory, LogsActivity, SoftDeletes;

    public const TIPO_PRODUTO = 'produto';

    public const TIPO_SERVICO = 'servico';

    protected $fillable = [
        'empresa_id', 'unidade_id', 'categoria_id', 'nome', 'tipo_item', 'sku', 'ean',
        'unidade_comercial', 'descricao', 'imagem_url',
        'preco_custo', 'preco_venda', 'controla_estoque', 'estoque_atual',
        'estoque_minimo', 'ativo',
        // NFC-e
        'ncm', 'cest', 'cfop', 'origem', 'cst_icms', 'csosn', 'aliq_icms',
        'cst_pis', 'aliq_pis', 'cst_cofins', 'aliq_cofins', 'cst_ipi', 'aliq_ipi', 'cod_beneficio',
        'cst_pis_entrada', 'aliq_pis_entrada', 'cst_cofins_entrada', 'aliq_cofins_entrada',
        'forma_pagamento_fiscal',
        // NFS-e Nacional
        'codigo_servico_lc116', 'codigo_tributacao_municipal', 'cnae_servico', 'nbs', 'aliq_iss', 'iss_retido',
    ];

    protected $casts = [
        'preco_custo' => 'decimal:2',
        'preco_venda' => 'decimal:2',
        'controla_estoque' => 'boolean',
        'ativo' => 'boolean',
        'iss_retido' => 'boolean',
        'origem' => 'integer',
        'aliq_icms' => 'decimal:4',
        'aliq_pis' => 'decimal:4',
        'aliq_cofins' => 'decimal:4',
        'aliq_ipi' => 'decimal:4',
        'aliq_iss' => 'decimal:4',
        'aliq_pis_entrada' => 'decimal:4',
        'aliq_cofins_entrada' => 'decimal:4',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function isServico(): bool
    {
        return $this->tipo_item === self::TIPO_SERVICO;
    }

    public function isProduto(): bool
    {
        return $this->tipo_item === self::TIPO_PRODUTO;
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaProduto::class, 'categoria_id');
    }

    public function movimentacoesEstoque(): HasMany
    {
        return $this->hasMany(MovimentacaoEstoque::class);
    }

    public function itensVenda(): HasMany
    {
        return $this->hasMany(VendaItem::class);
    }

    public function itensAtendimento(): HasMany
    {
        return $this->hasMany(AtendimentoItem::class);
    }

    public function estoqueAbaixoDoMinimo(): bool
    {
        return $this->controla_estoque && $this->estoque_atual <= $this->estoque_minimo;
    }

    public function scopeAtivos($query)
    {
        return $query->where('ativo', true);
    }

    public function scopeProdutos($query)
    {
        return $query->where('tipo_item', self::TIPO_PRODUTO);
    }

    public function scopeServicos($query)
    {
        return $query->where('tipo_item', self::TIPO_SERVICO);
    }
}
