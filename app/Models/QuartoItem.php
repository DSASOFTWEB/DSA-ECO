<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuartoItem extends Model
{
    use BelongsToTenant, HasFactory;

    // Sem isso o Eloquent adivinha "quarto_items" (pluralização em inglês
    // de "item"), não "quarto_itens" (nome real da tabela/migration).
    protected $table = 'quarto_itens';

    protected $fillable = ['empresa_id', 'quarto_id', 'produto_id', 'quantidade'];

    protected $casts = [
        'quantidade' => 'integer',
    ];

    public function quarto(): BelongsTo
    {
        return $this->belongsTo(Quarto::class);
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }
}
