<?php

namespace App\Console\Commands;

use App\Models\Mensalidade;
use App\Services\CobrancaWhatsappFila;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

/**
 * Régua de cobrança: decide QUEM deve ser cobrado hoje — lembrete X dias
 * ANTES do vencimento e novas tentativas nos dias configurados DEPOIS
 * (config/parque.php). Não envia nada: só põe na fila, que o comando
 * mensalidades:enviar-fila esvazia aos poucos (1 a cada N minutos por
 * empresa) — disparo em lote leva a banimento do número no WhatsApp.
 */
class EnviarCobrancasCommand extends Command
{
    protected $signature = 'mensalidades:cobrar';

    protected $description = 'Agenda na fila de WhatsApp os lembretes e cobranças de mensalidades conforme a régua de cobrança';

    public function handle(CobrancaWhatsappFila $fila): int
    {
        $diasAntes = Config::get('parque.cobranca_dias_antes_vencimento', 3);
        $diasApos = Config::get('parque.cobranca_dias_apos_vencimento', [1, 5, 10]);

        $agendadas = 0;

        // 1) Lembrete antes do vencimento
        $dataAlvo = now()->addDays($diasAntes)->toDateString();
        foreach (Mensalidade::withoutGlobalScopes()->where('status', 'pendente')->whereDate('data_vencimento', $dataAlvo)->get() as $mensalidade) {
            $agendadas += (int) (bool) $fila->agendar($mensalidade, tentativa: 0, daRegua: true);
        }

        // 2) Régua pós-vencimento
        foreach ($diasApos as $dias) {
            $dataAlvo = now()->subDays($dias)->toDateString();
            foreach (Mensalidade::withoutGlobalScopes()->where('status', 'atrasado')->whereDate('data_vencimento', $dataAlvo)->get() as $mensalidade) {
                $agendadas += (int) (bool) $fila->agendar($mensalidade, tentativa: $dias, daRegua: true);
            }
        }

        $this->info("Cobranças colocadas na fila: {$agendadas} (envio espaçado de ".config('parque.cobranca_intervalo_minutos').' em '.config('parque.cobranca_intervalo_minutos').' min).');

        return self::SUCCESS;
    }
}
