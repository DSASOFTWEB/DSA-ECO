<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fornecedor extends Model
{
    use BelongsToTenant, HasFactory;

    // Sem isso o Eloquent adivinha "fornecedors" (pluralização em inglês),
    // não "fornecedores" (nome real da tabela/migration).
    protected $table = 'fornecedores';

    protected $fillable = ['empresa_id', 'nome', 'documento', 'telefone', 'contato'];

    public function produtos(): HasMany
    {
        return $this->hasMany(Produto::class);
    }
}
