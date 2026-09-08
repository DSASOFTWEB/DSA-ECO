<?php

namespace App\Console\Commands;

use App\Services\MensalidadeService;
use Illuminate\Console\Command;

class AplicarAtrasosMensalidadesCommand extends Command
{
    protected $signature = 'mensalidades:aplicar-atrasos';

    protected $description = 'Marca como "atrasado" toda mensalidade pendente cujo vencimento já passou';

    public function handle(MensalidadeService $mensalidadeService): int
    {
        $total = $mensalidadeService->aplicarAtrasos();

        $this->info("Mensalidades marcadas como atrasadas: {$total}");

        return self::SUCCESS;
    }
}
