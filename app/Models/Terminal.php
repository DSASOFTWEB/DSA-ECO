<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Terminal extends Model
{
    use BelongsToTenant, HasFactory;

    // O inflector do Laravel não conhece plural em português: sem isso ele
    // tentaria usar a tabela "terminals" (inglês) em vez de "terminais".
    protected $table = 'terminais';

    protected $fillable = [
        'empresa_id', 'unidade_id', 'nome', 'status',
        'serie_nfce', 'numero_ultima_nfce_producao', 'numero_ultima_nfce_homologacao',
    ];

    protected $casts = [
        'serie_nfce' => 'integer',
        'numero_ultima_nfce_producao' => 'integer',
        'numero_ultima_nfce_homologacao' => 'integer',
    ];

    /** Terminal com série própria de NFC-e (senão usa a série padrão da empresa). */
    public function temSerieNfce(): bool
    {
        return $this->serie_nfce !== null;
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    public function caixas(): HasMany
    {
        return $this->hasMany(Caixa::class);
    }

    public function scopeAtivos($query)
    {
        return $query->where('status', 'ativo');
    }
}
