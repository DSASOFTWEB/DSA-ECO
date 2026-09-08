<?php

namespace App\Console\Commands;

use App\Jobs\EnviarCobrancaWhatsappJob;
use App\Models\Mensalidade;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

/**
 * Régua de cobrança: dispara um lembrete X dias ANTES do vencimento e
 * novas tentativas nos dias configurados DEPOIS do vencimento
 * (config/parque.php -> cobranca_dias_antes_vencimento / cobranca_dias_apos_vencimento).
 * O envio em si acontece de forma assíncrona (fila), este comando só
 * decide QUEM deve ser cobrado hoje.
 */
class EnviarCobrancasCommand extends Command
{
    protected $signature = 'mensalidades:cobrar';

    protected $description = 'Dispara lembretes e cobranças de mensalidades por WhatsApp conforme a régua de cobrança configurada';

    public function handle(): int
    {
        $diasAntes = Config::get('parque.cobranca_dias_antes_vencimento', 3);
        $diasApos = Config::get('parque.cobranca_dias_apos_vencimento', [1, 5, 10]);

        $totalDisparado = 0;

        // 1) Lembrete antes do vencimento
        $dataAlvo = now()->addDays($diasAntes)->toDateString();
        $pendentes = Mensalidade::where('status', 'pendente')->whereDate('data_vencimento', $dataAlvo)->get();

        foreach ($pendentes as $mensalidade) {
            EnviarCobrancaWhatsappJob::dispatch($mensalidade->id, tentativaRegua: 0);
            $totalDisparado++;
        }

        // 2) Régua pós-vencimento
        foreach ($diasApos as $dias) {
            $dataAlvo = now()->subDays($dias)->toDateString();

            $atrasadas = Mensalidade::where('status', 'atrasado')->whereDate('data_vencimento', $dataAlvo)->get();

            foreach ($atrasadas as $mensalidade) {
                EnviarCobrancaWhatsappJob::dispatch($mensalidade->id, tentativaRegua: $dias);
                $totalDisparado++;
            }
        }

        $this->info("Cobranças enfileiradas: {$totalDisparado}");

        return self::SUCCESS;
    }
}
