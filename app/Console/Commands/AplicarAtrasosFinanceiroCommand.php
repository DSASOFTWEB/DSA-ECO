<?php

namespace App\Console\Commands;

use App\Services\FinanceiroGestaoService;
use Illuminate\Console\Command;

class AplicarAtrasosFinanceiroCommand extends Command
{
    protected $signature = 'financeiro:aplicar-atrasos';

    protected $description = 'Marca como "atrasado" toda conta a pagar/receber pendente cujo vencimento já passou';

    public function handle(FinanceiroGestaoService $financeiroGestaoService): int
    {
        $total = $financeiroGestaoService->aplicarAtrasos();

        $this->info("Contas a pagar atrasadas: {$total['pagar']} | Contas a receber atrasadas: {$total['receber']}");

        return self::SUCCESS;
    }
}
