<?php

namespace App\Services\Integrations\Pix;

use App\Exceptions\IntegrationException;
use App\Models\Empresa;
use App\Services\Integrations\MercadoPagoService;
use Illuminate\Support\Str;

/**
 * Pix dinâmico do PDV pela conta Mercado Pago da empresa (ou da plataforma).
 * O "txid" guardado na cobrança é o id do pagamento no Mercado Pago, que é
 * o mesmo id que chega no webhook (ver ProcessarWebhookMercadoPagoJob).
 */
class MercadoPagoPixGateway implements GatewayPix
{
    public const PREFIXO_REFERENCIA = 'pdv';

    public function __construct(protected Empresa $empresa) {}

    public function nome(): string
    {
        return Empresa::GATEWAY_PIX_MERCADOPAGO;
    }

    public function criarCobranca(float $valor, string $descricao): array
    {
        $expiracao = $this->empresa->configuracaoGatewayPix()['expiracao_minutos'] * 60;

        $pagamento = $this->servico()->criarCobrancaPix(
            $valor,
            $descricao,
            self::PREFIXO_REFERENCIA.':'.Str::uuid(),
            $this->emailPagador(),
            now()->addSeconds($expiracao),
        );

        $id = (string) ($pagamento['id'] ?? '');
        $copiaECola = (string) data_get($pagamento, 'point_of_interaction.transaction_data.qr_code', '');

        if ($id === '' || $copiaECola === '') {
            throw new IntegrationException('O Mercado Pago não devolveu o QR Code da cobrança Pix.', 'mercadopago');
        }

        return [
            'txid' => $id,
            'copia_e_cola' => $copiaECola,
            'expiracao_segundos' => $expiracao,
            'payload' => [
                'id' => $id,
                'status' => $pagamento['status'] ?? null,
                'external_reference' => $pagamento['external_reference'] ?? null,
                'date_of_expiration' => $pagamento['date_of_expiration'] ?? null,
            ],
        ];
    }

    public function consultarCobranca(string $txid): array
    {
        $pagamento = $this->servico()->consultarPagamento($txid);
        $statusMp = (string) ($pagamento['status'] ?? '');

        return [
            'status' => match ($statusMp) {
                'approved' => self::STATUS_PAGA,
                'cancelled', 'rejected', 'refunded', 'charged_back' => self::STATUS_CANCELADA,
                default => self::STATUS_PENDENTE,
            },
            'e2eid' => data_get($pagamento, 'point_of_interaction.transaction_data.e2e_id')
                ?: (isset($pagamento['id']) ? (string) $pagamento['id'] : null),
            'payload' => [
                'id' => $pagamento['id'] ?? null,
                'status' => $statusMp,
                'status_detail' => $pagamento['status_detail'] ?? null,
                'date_approved' => $pagamento['date_approved'] ?? null,
            ],
        ];
    }

    public function cancelarCobranca(string $txid): void
    {
        $this->servico()->cancelarPagamento($txid);
    }

    protected function servico(): MercadoPagoService
    {
        return MercadoPagoService::paraEmpresa($this->empresa);
    }

    protected function emailPagador(): string
    {
        $email = (string) ($this->empresa->email ?: config('mail.from.address', ''));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : 'pdv@'.parse_url((string) config('app.url'), PHP_URL_HOST);
    }
}
