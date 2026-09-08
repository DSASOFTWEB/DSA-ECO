<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoriaProduto extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'categorias_produtos';

    protected $fillable = ['empresa_id', 'nome'];

    public function produtos(): HasMany
    {
        return $this->hasMany(Produto::class, 'categoria_id');
    }
}
