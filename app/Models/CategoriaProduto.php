<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoriaProduto extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'categorias_produtos';

    protected $fillable = ['empresa_id', 'categoria_pai_id', 'nome'];

    public function produtos(): HasMany
    {
        return $this->hasMany(Produto::class, 'categoria_id');
    }

    public function categoriaPai(): BelongsTo
    {
        return $this->belongsTo(self::class, 'categoria_pai_id');
    }

    public function subcategorias(): HasMany
    {
        return $this->hasMany(self::class, 'categoria_pai_id');
    }

    public function ehSubcategoria(): bool
    {
        return $this->categoria_pai_id !== null;
    }

    public function scopeRaizes($query)
    {
        return $query->whereNull('categoria_pai_id');
    }
}
