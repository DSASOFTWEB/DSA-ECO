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
        // Instância registrada no container (testes trocam por um fake) tem prioridade.
        if (app()->bound(self::class)) {
            return app(self::class);
        }

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

    public function configurada(): bool
    {
        return filled($this->baseUrl) && filled($this->apiKey) && filled($this->instancia);
    }

    /**
     * Envia um arquivo (ex.: ZIP do fechamento fiscal) como documento do
     * WhatsApp. $base64 = conteúdo do arquivo em base64.
     *
     * @throws IntegrationException
     */
    public function enviarDocumento(string $numero, string $base64, string $nomeArquivo, string $mimetype, string $legenda = ''): array
    {
        if (! $this->configurada()) {
            throw new IntegrationException(
                'Evolution API não configurada (EVOLUTION_API_URL/EVOLUTION_API_KEY ausentes).',
                servico: 'evolution-api',
            );
        }

        // Formato da Evolution v2 (campos na raiz).
        $v2 = [
            'number' => $numero,
            'mediatype' => 'document',
            'mimetype' => $mimetype,
            'caption' => $legenda,
            'media' => $base64,
            'fileName' => $nomeArquivo,
        ];
        // Formato da Evolution v1 (dentro de mediaMessage) — o texto (sendText)
        // funciona nas duas, mas o arquivo não: a v1 recusa o corpo da v2.
        $v1 = [
            'number' => $numero,
            'options' => ['delay' => 1200, 'presence' => 'composing'],
            'mediaMessage' => ['mediatype' => 'document', 'fileName' => $nomeArquivo, 'caption' => $legenda, 'media' => $base64],
        ];

        try {
            return $this->postarMidia($numero, $v2);
        } catch (IntegrationException $e) {
            if (! $this->erroDeFormato($e)) {
                throw $this->explicar($e);
            }
        }

        try {
            return $this->postarMidia($numero, $v1);
        } catch (IntegrationException $e) {
            throw $this->explicar($e);
        }
    }

    protected function postarMidia(string $numero, array $corpo): array
    {
        $client = $this->client(max($this->timeout, 60));

        return $this->comRetry(
            servico: 'evolution-api',
            operacao: "enviarDocumento:{$numero}",
            requisicao: function () use ($client, $corpo) {
                $resposta = $client->post("{$this->baseUrl}/message/sendMedia/{$this->instancia}", [
                    'headers' => [
                        'apikey' => $this->apiKey,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => $corpo,
                ]);

                return json_decode((string) $resposta->getBody(), true) ?? [];
            },
            maxTentativas: $this->maxTentativas,
            delayMs: $this->delayMs,
        );
    }

    /** 400/422 = a Evolution não entendeu o corpo (versão diferente): vale tentar o outro formato. */
    protected function erroDeFormato(IntegrationException $e): bool
    {
        $anterior = $e->getPrevious();

        return $anterior instanceof \GuzzleHttp\Exception\RequestException
            && in_array($anterior->getResponse()?->getStatusCode(), [400, 422], true);
    }

    /**
     * Troca o "falha ao comunicar..." genérico pelo que a Evolution respondeu
     * (status + mensagem), para a tela mostrar o motivo real.
     */
    public function explicar(IntegrationException $e): IntegrationException
    {
        $anterior = $e->getPrevious();
        $resposta = $anterior instanceof \GuzzleHttp\Exception\RequestException ? $anterior->getResponse() : null;

        if (! $resposta) {
            $motivo = $anterior instanceof \GuzzleHttp\Exception\ConnectException
                ? 'servidor da Evolution não respondeu (fora do ar ou endereço errado).'
                : 'erro de comunicação com a Evolution.';

            return new IntegrationException('WhatsApp: '.$motivo, servico: 'evolution-api', previous: $e);
        }

        $json = json_decode((string) $resposta->getBody(), true) ?? [];
        $texto = data_get($json, 'response.message') ?? data_get($json, 'message') ?? data_get($json, 'error');
        $texto = is_array($texto) ? implode('; ', \Illuminate\Support\Arr::flatten($texto)) : (string) $texto;

        $motivo = match ($resposta->getStatusCode()) {
            401, 403 => 'API Key da Evolution recusada.',
            404 => 'instância não encontrada na Evolution (confira o nome da instância).',
            413 => 'arquivo grande demais para o servidor da Evolution (limite de upload).',
            default => "Evolution respondeu HTTP {$resposta->getStatusCode()}.",
        };

        return new IntegrationException(
            'WhatsApp: '.$motivo.($texto !== '' ? ' Detalhe: '.\Illuminate\Support\Str::limit($texto, 200) : ''),
            servico: 'evolution-api',
            respostaBruta: ['status' => $resposta->getStatusCode(), 'corpo' => \Illuminate\Support\Str::limit((string) $resposta->getBody(), 500)],
            previous: $e,
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
