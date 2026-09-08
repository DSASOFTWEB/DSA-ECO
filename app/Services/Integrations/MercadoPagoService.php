<?php

namespace App\Services\Integrations;

use App\Exceptions\IntegrationException;
use App\Models\Empresa;
use App\Services\Integrations\Concerns\RealizaRequisicoesComRetry;

/**
 * Integração com a API do Mercado Pago (Pix / Checkout Pro / consulta de
 * pagamento). O processamento do webhook em si (validar assinatura,
 * idempotência) fica em App\Http\Controllers\Webhooks\MercadoPagoWebhookController
 * + App\Jobs\ProcessarWebhookMercadoPagoJob; este Service só fala HTTP com
 * o Mercado Pago.
 */
class MercadoPagoService
{
    use RealizaRequisicoesComRetry;

    public function __construct(
        protected string $accessToken = '',
        protected string $webhookSecret = '',
        protected int $timeout = 15,
        protected int $maxTentativas = 3,
        protected int $delayMs = 500,
        protected string $baseUrl = '',
    ) {
        $this->accessToken = $accessToken ?: config('mercadopago.access_token');
        $this->webhookSecret = $webhookSecret ?: config('mercadopago.webhook_secret');
        $this->timeout = $timeout ?: config('mercadopago.timeout');
        $this->maxTentativas = $maxTentativas ?: config('mercadopago.retries');
        $this->delayMs = $delayMs ?: config('mercadopago.retry_delay_ms');
        $this->baseUrl = $baseUrl ?: config('mercadopago.base_url');
    }

    /**
     * Monta o serviço com a credencial da PRÓPRIA empresa quando ela tiver
     * configurado a sua conta de Mercado Pago; qualquer campo que a empresa
     * não tenha preenchido cai de volta pro .env da plataforma (construtor
     * já faz esse fallback campo a campo). $empresa nula = usa só o .env.
     */
    public static function paraEmpresa(?Empresa $empresa): self
    {
        $credenciais = $empresa?->credenciaisMercadoPago() ?? [];

        return new self(
            accessToken: $credenciais['access_token'] ?? '',
            webhookSecret: $credenciais['webhook_secret'] ?? '',
        );
    }

    /**
     * Cria uma cobrança Pix (pagamento com expiração) para uma mensalidade
     * ou venda. Retorna o payload bruto do Mercado Pago, de onde a
     * aplicação extrai o QR Code (campo point_of_interaction) e o id do
     * pagamento (usado depois para casar com o webhook).
     *
     * @throws IntegrationException
     */
    public function criarCobrancaPix(float $valor, string $descricao, string $referenciaExterna, string $emailPagador): array
    {
        $this->garantirConfigurado();

        $client = $this->client($this->timeout);

        return $this->comRetry(
            servico: 'mercadopago',
            operacao: "criarCobrancaPix:{$referenciaExterna}",
            requisicao: function () use ($client, $valor, $descricao, $referenciaExterna, $emailPagador) {
                $resposta = $client->post("{$this->baseUrl}/v1/payments", [
                    'headers' => [
                        'Authorization' => "Bearer {$this->accessToken}",
                        'Content-Type' => 'application/json',
                        // Evita cobrança duplicada em caso de retry desta própria chamada.
                        'X-Idempotency-Key' => $referenciaExterna,
                    ],
                    'json' => [
                        'transaction_amount' => round($valor, 2),
                        'description' => $descricao,
                        'payment_method_id' => 'pix',
                        'external_reference' => $referenciaExterna,
                        'payer' => ['email' => $emailPagador],
                    ],
                ]);

                return json_decode((string) $resposta->getBody(), true) ?? [];
            },
            maxTentativas: $this->maxTentativas,
            delayMs: $this->delayMs,
        );
    }

    public function consultarPagamento(string $paymentId): array
    {
        $this->garantirConfigurado();

        $client = $this->client($this->timeout);

        return $this->comRetry(
            servico: 'mercadopago',
            operacao: "consultarPagamento:{$paymentId}",
            requisicao: function () use ($client, $paymentId) {
                $resposta = $client->get("{$this->baseUrl}/v1/payments/{$paymentId}", [
                    'headers' => ['Authorization' => "Bearer {$this->accessToken}"],
                ]);

                return json_decode((string) $resposta->getBody(), true) ?? [];
            },
            maxTentativas: $this->maxTentativas,
            delayMs: $this->delayMs,
        );
    }

    /**
     * Valida a assinatura do webhook (header x-signature) conforme a
     * especificação do Mercado Pago:
     * manifest = "id:{data.id};request-id:{x-request-id};ts:{ts};"
     * assinatura esperada = HMAC-SHA256(manifest, webhookSecret)
     *
     * @see https://www.mercadopago.com.br/developers/pt/docs/checkout-api/webhooks#editor_5
     */
    public function validarAssinaturaWebhook(string $xSignature, string $xRequestId, string $dataId): bool
    {
        if (! $this->webhookSecret) {
            // Sem segredo configurado não há como validar — quem chama decide
            // se aceita mesmo assim (ex.: ambiente de desenvolvimento).
            return false;
        }

        $partes = collect(explode(',', $xSignature))
            ->mapWithKeys(function (string $parte) {
                [$chave, $valor] = array_pad(explode('=', trim($parte), 2), 2, null);

                return [$chave => $valor];
            });

        $ts = $partes->get('ts');
        $v1 = $partes->get('v1');

        if (! $ts || ! $v1) {
            return false;
        }

        $manifest = "id:{$dataId};request-id:{$xRequestId};ts:{$ts};";
        $assinaturaCalculada = hash_hmac('sha256', $manifest, $this->webhookSecret);

        return hash_equals($assinaturaCalculada, $v1);
    }

    protected function garantirConfigurado(): void
    {
        if (! $this->accessToken) {
            throw new IntegrationException(
                'Mercado Pago não configurado (MERCADOPAGO_ACCESS_TOKEN ausente).',
                servico: 'mercadopago',
            );
        }
    }
}
