<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TipoEntrada extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $table = 'tipos_entrada';

    protected $fillable = ['empresa_id', 'nome', 'valor', 'eh_plano', 'ativo'];

    protected $casts = [
        'valor' => 'decimal:2',
        'eh_plano' => 'boolean',
        'ativo' => 'boolean',
    ];

    public function itensVenda(): HasMany
    {
        return $this->hasMany(VendaItem::class);
    }

    public function scopeAtivos($query)
    {
        return $query->where('ativo', true);
    }
}
