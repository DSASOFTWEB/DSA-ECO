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

    protected $fillable = ['empresa_id', 'unidade_id', 'nome', 'status'];

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
