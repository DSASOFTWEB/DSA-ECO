<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Datas do quarto vendidas em outro canal (iCal do Booking.com, Airbnb...).
 * "fim" é exclusivo: o dia da saída fica livre para nova entrada.
 */
class QuartoBloqueio extends Model
{
    use BelongsToTenant;

    public const ORIGENS = [
        'booking' => 'Booking.com',
        'airbnb' => 'Airbnb',
        'ical' => 'Calendário externo',
    ];

    protected $table = 'quarto_bloqueios';

    protected $fillable = ['empresa_id', 'quarto_id', 'origem', 'uid', 'inicio', 'fim', 'resumo', 'conflito', 'hospedagem_id'];

    protected $casts = [
        'inicio' => 'date',
        'fim' => 'date',
        'conflito' => 'boolean',
    ];

    public function quarto(): BelongsTo
    {
        return $this->belongsTo(Quarto::class);
    }

    public function hospedagem(): BelongsTo
    {
        return $this->belongsTo(Hospedagem::class);
    }

    /**
     * Bloqueia o quarto enquanto o hóspede não foi registrado no ERP (ou se
     * a hospedagem registrada foi cancelada — a venda no canal continua).
     */
    public function scopeQueOcupam($query)
    {
        return $query->where(fn ($q) => $q->whereNull('hospedagem_id')
            ->orWhereHas('hospedagem', fn ($h) => $h->where('status', 'cancelado')));
    }

    public function origemLabel(): string
    {
        return self::ORIGENS[$this->origem] ?? 'Calendário externo';
    }

    public function scopeAtivos($query)
    {
        return $query->whereDate('fim', '>', now()->toDateString());
    }
}
