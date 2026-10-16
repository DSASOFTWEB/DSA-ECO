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

        $chavePix = $this->normalizarChavePix($config['chave_pix']);

        Log::info('Itaú Pix: solicitando cobrança', [
            'empresa_id' => $this->empresa->getKey(),
            'valor' => number_format($valor, 2, '.', ''),
            'expiracao_segundos' => $expiracao,
            'chave_pix_mascara' => $this->mascara($chavePix),
            'chave_pix_tipo' => $this->tipoChavePix($chavePix),
            'client_id_mascara' => $this->mascara($config['client_id']),
            'tem_certificado' => $config['tem_certificado'],
            'certificado_existe' => Storage::disk('local')->exists($this->empresa->itauCertificadoCaminho()),
            'chave_privada_existe' => Storage::disk('local')->exists($this->empresa->itauChavePrivadaCaminho()),
        ]);

        $pix = (new Pix($chavePix, number_format($valor, 2, '.', '')))
            ->setExpiracao($expiracao);

        $resposta = $this->cliente()->pix($pix);
        $json = $this->jsonOuFalha($resposta, 'criar a cobrança Pix');

        $txid = (string) ($json['txid'] ?? '');
        $copiaECola = (string) ($json['pixCopiaECola'] ?? '');

        if ($txid === '' || $copiaECola === '') {
            Log::error('Itaú Pix: resposta sem txid/pixCopiaECola', [
                'empresa_id' => $this->empresa->getKey(),
                'chaves' => array_keys($this->semRuido($json)),
                'status' => $json['status'] ?? null,
            ]);

            throw new IntegrationException(
                'O Itaú não devolveu o QR Code da cobrança Pix.',
                self::SERVICO,
                $this->resumoErro($json),
            );
        }

        Log::info('Itaú Pix: cobrança criada', [
            'empresa_id' => $this->empresa->getKey(),
            'txid' => $txid,
            'status' => $json['status'] ?? null,
        ]);

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
        $mensagemSdk = $resposta->getMensagem();
        $falhou = $mensagemSdk !== null
            || isset($json['detail'], $json['title'])
            || isset($json['error'])
            || $json === [];

        if ($falhou) {
            $resumo = $this->resumoErro($json);
            if ($mensagemSdk !== null) {
                $resumo['mensagem_sdk'] = $mensagemSdk;
            }

            Log::error('Falha na API Pix do Itaú', [
                'empresa_id' => $this->empresa->getKey(),
                'operacao' => $operacao,
                'resposta' => $resumo,
                'json_vazio' => $json === [],
                'chaves' => array_keys($this->semRuido($json)),
            ]);

            $detalhe = $resumo['detail'] ?? $resumo['title'] ?? $resumo['error_description'] ?? $resumo['mensagem_sdk'] ?? null;

            throw new IntegrationException(
                "Não foi possível {$operacao} no Itaú".($detalhe ? ": {$detalhe}" : '.'),
                self::SERVICO,
                $resumo,
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

    /**
     * @return array<string, mixed>
     */
    protected function resumoErro(array $json): array
    {
        $limpo = $this->semRuido($json);
        $chaves = ['title', 'detail', 'error', 'error_description', 'status', 'type', 'code', 'mensagem', 'message', 'violacoes'];
        $resumo = [];

        foreach ($chaves as $chave) {
            if (array_key_exists($chave, $limpo)) {
                $resumo[$chave] = $limpo[$chave];
            }
        }

        // A SDK costuma anexar status HTTP numérico como chave 0 / status_code.
        foreach ($json as $chave => $valor) {
            if (is_array($valor) && isset($valor['status_code'])) {
                $resumo['status_code'] = $valor['status_code'];
            }
            if ($chave === 'status_code' || (is_int($chave) && is_numeric($valor))) {
                $resumo['status_code'] = $resumo['status_code'] ?? $valor;
            }
        }

        return $resumo !== [] ? $resumo : ['chaves' => array_keys($limpo)];
    }

    /**
     * CPF/CNPJ na API Pix devem ir só com dígitos. E-mail, telefone e chave
     * aleatória (EVP) seguem como estão cadastrados no Itaú.
     */
    protected function normalizarChavePix(string $chave): string
    {
        $chave = trim($chave);
        $soDigitos = preg_replace('/\D+/', '', $chave) ?? '';

        if (in_array(strlen($soDigitos), [11, 14], true) && preg_match('/^[\d.\-\/]+$/', $chave)) {
            return $soDigitos;
        }

        return $chave;
    }

    protected function tipoChavePix(string $chave): string
    {
        if (filter_var($chave, FILTER_VALIDATE_EMAIL)) {
            return 'email';
        }
        if (preg_match('/^\d{11}$/', $chave)) {
            return 'cpf';
        }
        if (preg_match('/^\d{14}$/', $chave)) {
            return 'cnpj';
        }
        if (preg_match('/^\+?\d{10,14}$/', $chave)) {
            return 'telefone';
        }
        if (preg_match('/^[0-9a-f-]{32,36}$/i', $chave)) {
            return 'aleatoria';
        }

        return 'desconhecido';
    }

    protected function mascara(string $valor): string
    {
        $valor = trim($valor);
        $len = strlen($valor);

        if ($len <= 8) {
            return str_repeat('*', $len);
        }

        return substr($valor, 0, 4).str_repeat('*', max(4, $len - 8)).substr($valor, -4);
    }
}
