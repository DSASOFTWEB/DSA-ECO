<?php

namespace App\Jobs;

use App\Models\FechamentoFiscal;
use App\Services\Fiscal\FechamentoFiscalService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Gera o pacote do fechamento mensal de NFC-e fora da requisição (meses
 * com muitas notas: o DANFE em PDF de cada uma leva tempo).
 */
class GerarFechamentoFiscalJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(public int $fechamentoId) {}

    public function handle(FechamentoFiscalService $service): void
    {
        $fechamento = FechamentoFiscal::withoutGlobalScopes()->find($this->fechamentoId);

        if ($fechamento && $fechamento->status === FechamentoFiscal::STATUS_PROCESSANDO) {
            $service->gerar($fechamento);
        }
    }
}
