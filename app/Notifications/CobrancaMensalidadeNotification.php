<?php

namespace App\Notifications;

use App\Models\Mensalidade;
use App\Notifications\Channels\WhatsappChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CobrancaMensalidadeNotification extends Notification
{
    use Queueable;

    public function __construct(protected Mensalidade $mensalidade) {}

    public function via(mixed $notifiable): array
    {
        // Referenciar a classe diretamente (em vez de uma string "whatsapp")
        // evita ter que registrar um driver customizado no ChannelManager.
        return [WhatsappChannel::class];
    }

    public function toWhatsapp(mixed $notifiable): array
    {
        $contrato = $this->mensalidade->contrato;
        $cliente = $contrato->cliente;
        $vencimento = $this->mensalidade->data_vencimento->format('d/m/Y');
        $valor = number_format((float) $this->mensalidade->valor_total, 2, ',', '.');

        $atrasada = $this->mensalidade->status === 'atrasado';

        $mensagem = $atrasada
            ? "Olá, {$cliente->nome}! Identificamos que sua mensalidade de R$ {$valor} (venc. {$vencimento}) está em atraso. "
              .'Para evitar o bloqueio do acesso, regularize o quanto antes. Qualquer dúvida, estamos à disposição.'
            : "Olá, {$cliente->nome}! Sua mensalidade de R$ {$valor} vence em {$vencimento}. Fique de olho para não perder o acesso ao parque :)";

        return [
            'to' => $cliente->routeNotificationForWhatsapp(),
            'message' => $mensagem,
        ];
    }
}
