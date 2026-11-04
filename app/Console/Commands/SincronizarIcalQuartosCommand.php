<?php

namespace App\Console\Commands;

use App\Models\Quarto;
use App\Services\Hospedagem\IcalQuartoService;
use Illuminate\Console\Command;

/**
 * Lê o calendário do Booking.com/Airbnb de cada quarto configurado e
 * atualiza os bloqueios (agendado a cada 15 minutos). Falha de um quarto
 * fica registrada nele e não impede os outros.
 */
class SincronizarIcalQuartosCommand extends Command
{
    protected $signature = 'hospedagem:sincronizar-ical {--quarto= : só este quarto (id)}';

    protected $description = 'Sincroniza os calendários iCal (Booking.com, Airbnb) dos quartos';

    public function handle(IcalQuartoService $ical): int
    {
        $quartos = Quarto::withoutGlobalScopes()
            ->whereNotNull('ical_importacao_url')
            ->where('ical_importacao_url', '!=', '')
            ->when($this->option('quarto'), fn ($q, $id) => $q->whereKey($id))
            ->get();

        foreach ($quartos as $quarto) {
            try {
                $r = $ical->sincronizar($quarto);
                $this->line("Quarto {$quarto->numero} (#{$quarto->id}): +{$r['criados']} ~{$r['atualizados']} -{$r['removidos']}".($r['conflitos'] ? " CONFLITOS: {$r['conflitos']}" : ''));
            } catch (\Throwable $e) {
                $this->warn("Quarto {$quarto->numero} (#{$quarto->id}): {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
