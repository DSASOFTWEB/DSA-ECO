<?php

namespace App\Console\Commands;

use App\Services\CobrancaWhatsappFila;
use Illuminate\Console\Command;

/**
 * Enviador da fila de cobranças por WhatsApp (agendado a cada minuto):
 * manda no máximo 1 mensagem por empresa a cada COBRANCA_INTERVALO_MINUTOS,
 * só dentro do horário configurado.
 */
class EnviarFilaCobrancasCommand extends Command
{
    protected $signature = 'mensalidades:enviar-fila';

    protected $description = 'Envia a próxima cobrança da fila de WhatsApp de cada empresa, respeitando o intervalo anti-banimento';

    public function handle(CobrancaWhatsappFila $fila): int
    {
        $enviadas = $fila->enviarProximas();
        if ($enviadas > 0) {
            $this->info("Cobranças enviadas nesta rodada: {$enviadas}");
        }

        return self::SUCCESS;
    }
}
