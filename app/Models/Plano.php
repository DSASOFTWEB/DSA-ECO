<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plano extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'empresa_id', 'nome', 'descricao', 'valor', 'periodicidade',
        'max_dependentes', 'dias_acesso_semana', 'permite_congelamento', 'ativo',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'permite_congelamento' => 'boolean',
        'ativo' => 'boolean',
    ];

    public function contratos(): HasMany
    {
        return $this->hasMany(Contrato::class);
    }

    public function scopeAtivos($query)
    {
        return $query->where('ativo', true);
    }
}
