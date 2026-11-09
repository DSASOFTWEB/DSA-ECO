<?php

namespace App\Jobs;

use App\Models\Mensalidade;
use App\Services\CobrancaWhatsappFila;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Mantido por compatibilidade com jobs que já estavam na fila antes da
 * mudança: em vez de enviar na hora (o que causava rajada e banimento do
 * número), só coloca a cobrança na fila espaçada (CobrancaWhatsappFila).
 */
class EnviarCobrancaWhatsappJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(protected int $mensalidadeId, protected int $tentativaRegua = 1) {}

    public function handle(CobrancaWhatsappFila $fila): void
    {
        $mensalidade = Mensalidade::withoutGlobalScopes()->find($this->mensalidadeId);

        if ($mensalidade) {
            $fila->agendar($mensalidade, $this->tentativaRegua);
        }
    }
}
