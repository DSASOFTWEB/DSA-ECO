<?php

namespace App\Notifications\Channels;

use App\Services\Integrations\EvolutionApiService;
use Illuminate\Notifications\Notification;

/**
 * Canal de notificação customizado que entrega via WhatsApp (Evolution API).
 * Qualquer Notification que implemente toWhatsapp($notifiable) e retorne
 * ['to' => '55...', 'message' => '...'] pode ser enviada por este canal,
 * bastando declarar `whatsapp` em Notification::via().
 */
class WhatsappChannel
{
    public function send(mixed $notifiable, Notification $notification): array
    {
        if (! method_exists($notification, 'toWhatsapp')) {
            throw new \LogicException(get_class($notification).' precisa implementar toWhatsapp().');
        }

        $dados = $notification->toWhatsapp($notifiable);

        $numero = $dados['to'] ?? $notifiable->routeNotificationForWhatsapp($notification);

        if (! $numero) {
            throw new \RuntimeException('Destinatário sem número de WhatsApp cadastrado.');
        }

        // Instância de WhatsApp da PRÓPRIA empresa do destinatário quando
        // configurada — cada parque manda pelo seu número. Resolvido aqui
        // (em vez de injetado no construtor) justamente porque só dá pra
        // saber a empresa depois de saber pra quem é a notificação.
        $empresa = $notifiable->empresa ?? null;
        $evolutionApi = EvolutionApiService::paraEmpresa($empresa);

        return $evolutionApi->enviarTexto($this->normalizarNumero($numero), $dados['message']);
    }

    protected function normalizarNumero(string $numero): string
    {
        $digitos = preg_replace('/\D+/', '', $numero);

        // Garante o código do país (Brasil) quando o número foi salvo só com DDD.
        if (strlen($digitos) <= 11) {
            $digitos = '55'.$digitos;
        }

        return $digitos;
    }
}
