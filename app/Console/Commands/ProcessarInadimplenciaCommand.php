<?php

namespace App\Console\Commands;

use App\Services\InadimplenciaService;
use Illuminate\Console\Command;

class ProcessarInadimplenciaCommand extends Command
{
    protected $signature = 'inadimplencia:processar';

    protected $description = 'Bloqueia o acesso (carteirinha) dos clientes com mensalidade em atraso além do limite configurado';

    public function handle(InadimplenciaService $inadimplenciaService): int
    {
        $total = $inadimplenciaService->processarBloqueiosPorAtraso();

        $this->info("Carteirinhas bloqueadas por inadimplência: {$total}");

        return self::SUCCESS;
    }
}
