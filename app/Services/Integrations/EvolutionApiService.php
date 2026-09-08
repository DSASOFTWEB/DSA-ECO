<?php

namespace App\Services\Integrations;

use App\Exceptions\IntegrationException;
use App\Models\Empresa;
use App\Services\Integrations\Concerns\RealizaRequisicoesComRetry;

/**
 * Integração com a Evolution API (https://doc.evolution-api.com) para
 * envio de mensagens de WhatsApp — usada principalmente pela régua de
 * cobrança de mensalidades (App\Jobs\EnviarCobrancaWhatsappJob).
 *
 * Desacoplada de qualquer Model: recebe apenas dados primitivos e devolve
 * um array com a resposta bruta. Quem decide O QUE mandar (texto, para
 * quem) é o Service de domínio (MensalidadeService/cobrança), não este.
 */
class EvolutionApiService
{
    use RealizaRequisicoesComRetry;

    public function __construct(
        protected string $baseUrl = '',
        protected string $apiKey = '',
        protected string $instancia = '',
        protected int $timeout = 15,
        protected int $maxTentativas = 3,
        protected int $delayMs = 500,
    ) {
        $this->baseUrl = $baseUrl ?: config('evolution.base_url');
        $this->apiKey = $apiKey ?: config('evolution.api_key');
        $this->instancia = $instancia ?: config('evolution.instance');
        $this->timeout = $timeout ?: config('evolution.timeout');
        $this->maxTentativas = $maxTentativas ?: config('evolution.retries');
        $this->delayMs = $delayMs ?: config('evolution.retry_delay_ms');
    }

    /**
     * Mesma ideia de MercadoPagoService::paraEmpresa() — usa a instância de
     * WhatsApp da própria empresa quando configurada, senão cai pro .env
     * (instância única compartilhada da plataforma).
     */
    public static function paraEmpresa(?Empresa $empresa): self
    {
        $credenciais = $empresa?->credenciaisEvolution() ?? [];

        return new self(
            baseUrl: $credenciais['base_url'] ?? '',
            apiKey: $credenciais['api_key'] ?? '',
            instancia: $credenciais['instance'] ?? '',
        );
    }

    /**
     * Envia uma mensagem de texto simples para um número de WhatsApp.
     *
     * @param  string  $numero  Formato E.164 sem símbolos, ex: 5511999999999
     * @return array Resposta bruta da Evolution API (guardada em cobrancas.resposta_gateway)
     *
     * @throws IntegrationException
     */
    public function enviarTexto(string $numero, string $mensagem): array
    {
        if (! $this->baseUrl || ! $this->apiKey) {
            throw new IntegrationException(
                'Evolution API não configurada (EVOLUTION_API_URL/EVOLUTION_API_KEY ausentes).',
                servico: 'evolution-api',
            );
        }

        $client = $this->client($this->timeout);

        return $this->comRetry(
            servico: 'evolution-api',
            operacao: "enviarTexto:{$numero}",
            requisicao: function () use ($client, $numero, $mensagem) {
                $resposta = $client->post("{$this->baseUrl}/message/sendText/{$this->instancia}", [
                    'headers' => [
                        'apikey' => $this->apiKey,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'number' => $numero,
                        'text' => $mensagem,
                    ],
                ]);

                return json_decode((string) $resposta->getBody(), true) ?? [];
            },
            maxTentativas: $this->maxTentativas,
            delayMs: $this->delayMs,
        );
    }

    /**
     * Consulta o status de conexão da instância (útil para o painel admin
     * avisar "WhatsApp desconectado" antes de tentar disparar cobranças).
     */
    public function statusConexao(): array
    {
        $client = $this->client($this->timeout);

        return $this->comRetry(
            servico: 'evolution-api',
            operacao: 'statusConexao',
            requisicao: function () use ($client) {
                $resposta = $client->get("{$this->baseUrl}/instance/connectionState/{$this->instancia}", [
                    'headers' => ['apikey' => $this->apiKey],
                ]);

                return json_decode((string) $resposta->getBody(), true) ?? [];
            },
            maxTentativas: 1,
            delayMs: $this->delayMs,
        );
    }
}
