<?php

namespace App\Services\Integrations\Pix;

use App\Exceptions\IntegrationException;
use App\Models\Empresa;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Itau\API\BaseResponse;
use Itau\API\Itau;
use Itau\API\Pix\Pix;

/**
 * Pix Recebimentos v2 do Itaú (cob imediata) via mastria/api-itau, que já
 * usa os hosts novos (sts.itau.com.br + secure.gateway.api.itau) com mTLS.
 *
 * @see https://devportal.itau.com.br/nossas-apis/itau-ep9-gtw-pix-recebimentos-ext-v2
 */
class ItauPixGateway implements GatewayPix
{
    protected const SERVICO = 'itau_pix';

    public function __construct(protected Empresa $empresa) {}

    public function nome(): string
    {
        return Empresa::GATEWAY_PIX_ITAU;
    }

    public function criarCobranca(float $valor, string $descricao): array
    {
        $config = $this->config();
        $expiracao = $this->empresa->configuracaoGatewayPix()['expiracao_minutos'] * 60;

        $pix = (new Pix($config['chave_pix'], number_format($valor, 2, '.', '')))
            ->setExpiracao($expiracao);

        $resposta = $this->cliente()->pix($pix);
        $json = $this->jsonOuFalha($resposta, 'criar a cobrança Pix');

        $txid = (string) ($json['txid'] ?? '');
        $copiaECola = (string) ($json['pixCopiaECola'] ?? '');

        if ($txid === '' || $copiaECola === '') {
            throw new IntegrationException('O Itaú não devolveu o QR Code da cobrança Pix.', self::SERVICO);
        }

        return [
            'txid' => $txid,
            'copia_e_cola' => $copiaECola,
            'expiracao_segundos' => (int) ($json['calendario']['expiracao'] ?? $expiracao),
            'payload' => $this->semRuido($json),
        ];
    }

    public function consultarCobranca(string $txid): array
    {
        $json = $this->jsonOuFalha($this->cliente()->consultaPix($txid), 'consultar a cobrança Pix');

        $statusItau = (string) ($json['status'] ?? '');
        $pagamentos = $json['pix'] ?? [];

        $status = match (true) {
            $statusItau === 'CONCLUIDA', ! empty($pagamentos) => self::STATUS_PAGA,
            str_starts_with($statusItau, 'REMOVIDA') => self::STATUS_CANCELADA,
            default => self::STATUS_PENDENTE,
        };

        return [
            'status' => $status,
            'e2eid' => $pagamentos[0]['endToEndId'] ?? null,
            'payload' => $this->semRuido($json),
        ];
    }

    public function cancelarCobranca(string $txid): void
    {
        $this->jsonOuFalha($this->cliente()->cancelarPix($txid), 'cancelar a cobrança Pix');
    }

    protected function cliente(): Itau
    {
        $config = $this->config();
        $disco = Storage::disk('local');

        return (new Itau(
            $config['client_id'],
            $config['client_secret'],
            $disco->path($this->empresa->itauCertificadoCaminho()),
            $disco->path($this->empresa->itauChavePrivadaCaminho()),
        ))->enableTokenCache(
            $disco->path("gateways/itau/token-empresa-{$this->empresa->getKey()}.json"),
            240,
        );
    }

    /**
     * @return array{client_id: string, client_secret: string, chave_pix: string, expiracao_minutos: int, tem_certificado: bool}
     */
    protected function config(): array
    {
        $itau = $this->empresa->configuracaoGatewayPix()['itau'];

        if ($itau['client_id'] === '' || $itau['client_secret'] === '' || $itau['chave_pix'] === '' || ! $itau['tem_certificado']) {
            throw new IntegrationException('Pix Itaú não configurado para esta empresa.', self::SERVICO);
        }

        return $itau;
    }

    /**
     * A SDK engole exceções e devolve a resposta com status ERROR — aqui
     * isso vira IntegrationException, sem repassar o corpo bruto ao usuário.
     */
    protected function jsonOuFalha(BaseResponse $resposta, string $operacao): array
    {
        $json = $resposta->getJson() ?? [];
        $falhou = $resposta->getMensagem() !== null
            || isset($json['detail'], $json['title'])
            || isset($json['error'])
            || $json === [];

        if ($falhou) {
            Log::warning('Falha na API Pix do Itaú', [
                'empresa_id' => $this->empresa->getKey(),
                'operacao' => $operacao,
                'title' => $json['title'] ?? null,
                'detail' => $json['detail'] ?? $resposta->getMensagem(),
            ]);

            $detalhe = $json['detail'] ?? $json['title'] ?? null;

            throw new IntegrationException(
                "Não foi possível {$operacao} no Itaú".($detalhe ? ": {$detalhe}" : '.'),
                self::SERVICO,
            );
        }

        return $json;
    }

    /**
     * A SDK anexa um item numérico {status_code} ao fim do JSON.
     */
    protected function semRuido(array $json): array
    {
        return array_filter($json, fn ($chave) => ! is_int($chave), ARRAY_FILTER_USE_KEY);
    }
}
