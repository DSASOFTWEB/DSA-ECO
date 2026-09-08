<?php

namespace App\Jobs;

use App\Exceptions\IntegrationException;
use App\Models\Cobranca;
use App\Models\Mensalidade;
use App\Notifications\CobrancaMensalidadeNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;

class EnviarCobrancaWhatsappJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120, 600];

    public function __construct(protected int $mensalidadeId, protected int $tentativaRegua = 1) {}

    public function handle(): void
    {
        $mensalidade = Mensalidade::with('contrato.cliente')->find($this->mensalidadeId);

        if (! $mensalidade || $mensalidade->estaPaga()) {
            // Cliente já pagou entre o agendamento e a execução do job: não faz sentido cobrar.
            return;
        }

        $cliente = $mensalidade->contrato->cliente;

        if (! $cliente->routeNotificationForWhatsapp()) {
            Cobranca::create([
                'mensalidade_id' => $mensalidade->id,
                'canal' => 'whatsapp',
                'status' => 'falhou',
                'tentativa' => $this->tentativaRegua,
                'mensagem' => 'Cliente sem número de WhatsApp/telefone cadastrado.',
            ]);

            return;
        }

        try {
            NotificationFacade::send($cliente, new CobrancaMensalidadeNotification($mensalidade));

            Cobranca::create([
                'mensalidade_id' => $mensalidade->id,
                'canal' => 'whatsapp',
                'status' => 'enviado',
                'tentativa' => $this->tentativaRegua,
                'enviado_em' => now(),
            ]);
        } catch (IntegrationException $e) {
            Cobranca::create([
                'mensalidade_id' => $mensalidade->id,
                'canal' => 'whatsapp',
                'status' => 'falhou',
                'tentativa' => $this->tentativaRegua,
                'mensagem' => $e->getMessage(),
            ]);

            Log::error('Falha ao enviar cobrança por WhatsApp', [
                'mensalidade_id' => $mensalidade->id,
                'erro' => $e->getMessage(),
            ]);

            // Relança para o Job entrar no fluxo de retry/backoff da fila também
            // (além do retry interno da própria chamada HTTP no Service).
            throw $e;
        }
    }
}
