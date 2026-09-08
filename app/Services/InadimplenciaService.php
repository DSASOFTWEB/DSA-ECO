<?php

namespace App\Services;

use App\Models\Contrato;
use App\Models\Mensalidade;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

/**
 * Rotina diária de inadimplência: identifica contratos com mensalidade
 * vencida há mais dias que o limite configurado e bloqueia a(s)
 * carteirinha(s) do titular/dependentes, registrando o motivo.
 *
 * Importante: NÃO cancela o contrato automaticamente — apenas suspende o
 * acesso físico ao parque. O cancelamento do contrato continua sendo uma
 * decisão humana (ver App\Services\ContratoService::cancelar).
 */
class InadimplenciaService
{
    public function __construct(
        protected CarteirinhaService $carteirinhaService,
    ) {}

    public function mensalidadesAtrasadas(): Collection
    {
        return Mensalidade::atrasadas()->get();
    }

    /**
     * Percorre os contratos com mensalidade em atraso além do limite
     * configurado (parque.dias_atraso_bloqueia_acesso) e bloqueia as
     * carteirinhas associadas (titular + dependentes do contrato).
     */
    public function processarBloqueiosPorAtraso(): int
    {
        $diasLimite = Config::get('parque.dias_atraso_bloqueia_acesso', 5);
        $bloqueadas = 0;

        $contratosComAtraso = Contrato::query()
            ->where('status', 'ativo')
            ->whereHas('mensalidades', fn ($q) => $q->where('status', 'atrasado'))
            ->with(['cliente.carteirinha', 'dependentes.carteirinha', 'mensalidades' => fn ($q) => $q->where('status', 'atrasado')])
            ->get();

        foreach ($contratosComAtraso as $contrato) {
            $piorAtraso = $contrato->mensalidades->max(fn ($m) => $m->diasEmAtraso());

            if ($piorAtraso < $diasLimite) {
                continue;
            }

            $motivo = "Inadimplência: mensalidade em atraso há {$piorAtraso} dia(s)";

            if ($carteirinha = $contrato->cliente->carteirinha) {
                if ($carteirinha->estaAtiva()) {
                    $this->carteirinhaService->bloquear($carteirinha, $motivo);
                    $bloqueadas++;
                }
            }

            foreach ($contrato->dependentes as $dependente) {
                if ($dependente->carteirinha && $dependente->carteirinha->estaAtiva()) {
                    $this->carteirinhaService->bloquear($dependente->carteirinha, $motivo);
                    $bloqueadas++;
                }
            }
        }

        Log::info('Rotina de inadimplência processada', ['carteirinhas_bloqueadas' => $bloqueadas]);

        return $bloqueadas;
    }
}
