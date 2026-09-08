<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Comissao extends Model
{
    use BelongsToTenant, HasFactory;

    /** Plural irregular em PT — sem isso o Eloquent assume "comissaos". */
    protected $table = 'comissoes';

    protected $fillable = [
        'empresa_id', 'vendedor_id', 'venda_id', 'contrato_id', 'tipo',
        'base_calculo', 'percentual', 'valor', 'status', 'pago_em',
    ];

    protected $casts = [
        'base_calculo' => 'decimal:2',
        'percentual' => 'decimal:2',
        'valor' => 'decimal:2',
        'pago_em' => 'date',
    ];

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendedor_id');
    }

    public function venda(): BelongsTo
    {
        return $this->belongsTo(Venda::class);
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function scopePendentes($query)
    {
        return $query->where('status', 'pendente');
    }
}
