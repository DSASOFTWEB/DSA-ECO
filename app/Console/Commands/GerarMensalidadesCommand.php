<?php

namespace App\Console\Commands;

use App\Services\MensalidadeService;
use Illuminate\Console\Command;

class GerarMensalidadesCommand extends Command
{
    protected $signature = 'mensalidades:gerar {--dia= : Gera para um dia de vencimento específico (padrão: hoje)}';

    protected $description = 'Gera as mensalidades do mês corrente para todo contrato ativo cujo dia de vencimento seja hoje';

    public function handle(MensalidadeService $mensalidadeService): int
    {
        $dia = (int) ($this->option('dia') ?? now()->day);

        $geradas = $mensalidadeService->gerarMensalidadesDoDia($dia);

        $this->info("Mensalidades geradas para o dia de vencimento {$dia}: {$geradas}");

        return self::SUCCESS;
    }
}
