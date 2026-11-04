<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quarto extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'empresa_id', 'unidade_id', 'numero', 'capacidade_maxima', 'valor_diaria', 'status', 'precisa_limpeza', 'nao_perturbe', 'observacoes',
    ];

    protected $casts = [
        'capacidade_maxima' => 'integer',
        'valor_diaria' => 'decimal:2',
        'precisa_limpeza' => 'boolean',
        'nao_perturbe' => 'boolean',
        'ical_sincronizado_em' => 'datetime',
    ];

    protected $hidden = ['ical_token'];

    public function bloqueios(): HasMany
    {
        return $this->hasMany(QuartoBloqueio::class);
    }

    /** Link que o Booking.com/Airbnb leem (gerado na primeira vez que a tela pede). */
    public function linkIcal(): string
    {
        if (! $this->ical_token) {
            $this->forceFill(['ical_token' => \Illuminate\Support\Str::random(40)])->save();
        }

        return route('ical.quarto', $this->ical_token);
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    public function hospedagens(): HasMany
    {
        return $this->hasMany(Hospedagem::class);
    }

    public function itensComodato(): HasMany
    {
        return $this->hasMany(QuartoItem::class);
    }

    public function scopeAtivos($query)
    {
        return $query->where('status', 'ativo');
    }
}
