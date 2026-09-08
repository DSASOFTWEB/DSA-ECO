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

    protected $fillable = [
        'empresa_id', 'unidade_id', 'categoria_id', 'nome', 'sku', 'descricao',
        'preco_custo', 'preco_venda', 'controla_estoque', 'estoque_atual',
        'estoque_minimo', 'ativo',
    ];

    protected $casts = [
        'preco_custo' => 'decimal:2',
        'preco_venda' => 'decimal:2',
        'controla_estoque' => 'boolean',
        'ativo' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnlyDirty()->dontSubmitEmptyLogs();
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

    public function estoqueAbaixoDoMinimo(): bool
    {
        return $this->controla_estoque && $this->estoque_atual <= $this->estoque_minimo;
    }

    public function scopeAtivos($query)
    {
        return $query->where('ativo', true);
    }
}
