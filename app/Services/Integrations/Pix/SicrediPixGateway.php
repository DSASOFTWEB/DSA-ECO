<?php

namespace App\Services\Integrations\Pix;

use App\Exceptions\IntegrationException;
use App\Models\Empresa;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * API Pix Sicredi (padrão BACEN, cob imediata): OAuth2 client_credentials
 * sobre mTLS. O token é reaproveitado até expirar — o Sicredi bloqueia
 * temporariamente quem gera token em excesso.
 *
 * @see https://developers.sicredi.com.br/public/docs/getting-started-pix
 */
class SicrediPixGateway implements GatewayPix
{
    public const URL_PRODUCAO = 'https://api-pix.sicredi.com.br';

    public const ESCOPOS = 'cob.write cob.read';

    protected const SERVICO = 'sicredi_pix';

    public function __construct(protected Empresa $empresa) {}

    public function nome(): string
    {
        return Empresa::GATEWAY_PIX_SICREDI;
    }

    public function criarCobranca(float $valor, string $descricao): array
    {
        $config = $this->config();
        $expiracao = $this->empresa->configuracaoGatewayPix()['expiracao_minutos'] * 60;

        // txid é do recebedor: 26–35 alfanuméricos, único por CNPJ.
        $txid = Str::upper(Str::random(32));

        $corpo = array_filter([
            'calendario' => ['expiracao' => $expiracao],
            // modalidadeAlteracao 0: o pagador não pode mudar o valor (recomendação Sicredi).
            'valor' => ['original' => number_format($valor, 2, '.', ''), 'modalidadeAlteracao' => 0],
            'chave' => $config['chave_pix'],
            'solicitacaoPagador' => Str::limit($descricao, 137, '...') ?: null,
        ]);

        $json = $this->chamar('put', "/api/v2/cob/{$txid}", $corpo, 'criar a cobrança Pix');

        $copiaECola = (string) ($json['pixCopiaECola'] ?? '');
        if ($copiaECola === '') {
            throw new IntegrationException('O Sicredi não devolveu o Pix Copia e Cola da cobrança.', self::SERVICO, $this->resumo($json));
        }

        Log::info('Sicredi Pix: cobrança criada', ['empresa_id' => $this->empresa->getKey(), 'txid' => $json['txid'] ?? $txid]);

        return [
            'txid' => (string) ($json['txid'] ?? $txid),
            'copia_e_cola' => $copiaECola,
            'expiracao_segundos' => (int) ($json['calendario']['expiracao'] ?? $expiracao),
            'payload' => $json,
        ];
    }

    public function consultarCobranca(string $txid): array
    {
        $json = $this->chamar('get', "/api/v2/cob/{$txid}", null, 'consultar a cobrança Pix');

        $status = (string) ($json['status'] ?? '');
        $pagamentos = $json['pix'] ?? [];

        return [
            'status' => match (true) {
                $status === 'CONCLUIDA', ! empty($pagamentos) => self::STATUS_PAGA,
                str_starts_with($status, 'REMOVIDA') => self::STATUS_CANCELADA,
                default => self::STATUS_PENDENTE,
            },
            'e2eid' => $pagamentos[0]['endToEndId'] ?? null,
            'payload' => $json,
        ];
    }

    public function cancelarCobranca(string $txid): void
    {
        $this->chamar('patch', "/api/v2/cob/{$txid}", ['status' => 'REMOVIDA_PELO_USUARIO_RECEBEDOR'], 'cancelar a cobrança Pix');
    }

    /**
     * Botão "Testar conexão": pede um token novo (certificado + credenciais)
     * sem criar cobrança.
     *
     * @return array{ambiente: string, escopos: string, expira_em_segundos: int}
     */
    public function testarConexao(): array
    {
        Cache::forget($this->chaveToken());
        $token = $this->token();

        return [
            'ambiente' => $this->config()['ambiente'],
            'escopos' => (string) ($token['scope'] ?? self::ESCOPOS),
            'expira_em_segundos' => (int) ($token['expires_in'] ?? 0),
        ];
    }

    public function urlBase(): string
    {
        $config = $this->config();

        return rtrim($config['ambiente'] === 'homologacao' ? $config['url_homologacao'] : self::URL_PRODUCAO, '/');
    }

    /**
     * Chamada autenticada; se o token tiver sido revogado (401), pega outro
     * uma vez e repete.
     */
    protected function chamar(string $metodo, string $caminho, ?array $corpo, string $operacao): array
    {
        $resposta = $this->enviar($metodo, $caminho, $corpo, $this->token()['access_token']);

        if ($resposta->status() === 401) {
            Cache::forget($this->chaveToken());
            $resposta = $this->enviar($metodo, $caminho, $corpo, $this->token()['access_token']);
        }

        if ($resposta->failed()) {
            $this->falhar($resposta, $operacao);
        }

        return (array) $resposta->json();
    }

    protected function enviar(string $metodo, string $caminho, ?array $corpo, string $accessToken): Response
    {
        try {
            $requisicao = $this->http()->withToken($accessToken)->acceptJson();

            return $corpo === null
                ? $requisicao->{$metodo}($this->urlBase().$caminho)
                : $requisicao->asJson()->{$metodo}($this->urlBase().$caminho, $corpo);
        } catch (ConnectionException $e) {
            throw new IntegrationException('Sem comunicação com a API Pix do Sicredi: '.$this->erroConexao($e), self::SERVICO, previous: $e);
        }
    }

    /**
     * @return array{access_token: string, expires_in?: int, scope?: string}
     */
    protected function token(): array
    {
        $chave = $this->chaveToken();
        $guardado = Cache::get($chave);
        if (is_array($guardado) && filled($guardado['access_token'] ?? null)) {
            return $guardado;
        }

        $config = $this->config();

        try {
            $resposta = $this->http()
                ->withBasicAuth($config['client_id'], $config['client_secret'])
                ->asForm()
                ->post($this->urlBase().'/oauth/token', ['grant_type' => 'client_credentials', 'scope' => self::ESCOPOS]);
        } catch (ConnectionException $e) {
            throw new IntegrationException('Sem comunicação com o Sicredi para autenticar: '.$this->erroConexao($e), self::SERVICO, previous: $e);
        }

        if ($resposta->failed() || ! filled($resposta->json('access_token'))) {
            $this->falhar($resposta, 'autenticar (token)');
        }

        $token = (array) $resposta->json();
        // Reaproveita até 1 min antes de expirar.
        Cache::put($chave, $token, max(30, (int) ($token['expires_in'] ?? 300) - 60));

        return $token;
    }

    protected function http(): PendingRequest
    {
        $certificados = app(SicrediCertificadoService::class);
        if (! $certificados->garantirArquivos($this->empresa)) {
            throw new IntegrationException('Certificado mTLS do Sicredi não cadastrado.', self::SERVICO);
        }

        $disco = Storage::disk('local');

        return Http::timeout(20)->connectTimeout(10)->withOptions([
            'cert' => $disco->path($certificados->caminhoCertificado($this->empresa)),
            'ssl_key' => $disco->path($certificados->caminhoChavePrivada($this->empresa)),
        ]);
    }

    protected function chaveToken(): string
    {
        $config = $this->config();

        return 'sicredi_pix_token:'.$this->empresa->getKey().':'.md5($config['ambiente'].$config['client_id']);
    }

    /**
     * @return array{client_id: string, client_secret: string, chave_pix: string, ambiente: string, url_homologacao: string}
     */
    protected function config(): array
    {
        $sicredi = $this->empresa->configuracaoGatewayPix()['sicredi'];

        if ($sicredi['client_id'] === '' || $sicredi['client_secret'] === '' || $sicredi['chave_pix'] === '' || ! $sicredi['tem_certificado']) {
            throw new IntegrationException('Pix Sicredi não configurado para esta empresa.', self::SERVICO);
        }

        if ($sicredi['ambiente'] === 'homologacao' && $sicredi['url_homologacao'] === '') {
            throw new IntegrationException('Informe a URL de homologação enviada pelo Sicredi.', self::SERVICO);
        }

        return $sicredi;
    }

    /**
     * Erros no padrão RFC 7807 (title/detail/violacoes). O corpo não vai para
     * a tela, só o resumo.
     */
    protected function falhar(Response $resposta, string $operacao): never
    {
        $json = (array) ($resposta->json() ?? []);
        $resumo = $this->resumo($json) + ['http' => $resposta->status()];

        Log::error('Falha na API Pix do Sicredi', ['empresa_id' => $this->empresa->getKey(), 'operacao' => $operacao, 'resposta' => $resumo]);

        $detalhe = $json['detail'] ?? $json['title'] ?? $json['error_description'] ?? $json['message'] ?? null;
        if (! $detalhe && in_array($resposta->status(), [401, 403], true)) {
            $detalhe = 'acesso negado — confira Client ID/Secret, o certificado e se a API Pix está liberada';
        }
        if (! empty($json['violacoes'][0]['razao'])) {
            $detalhe = trim(($detalhe ? $detalhe.' — ' : '').$json['violacoes'][0]['razao']);
        }

        throw new IntegrationException(
            "Não foi possível {$operacao} no Sicredi".($detalhe ? ": {$detalhe}" : " (HTTP {$resposta->status()})."),
            self::SERVICO,
            $resumo,
        );
    }

    protected function resumo(array $json): array
    {
        return array_intersect_key($json, array_flip(['type', 'title', 'status', 'detail', 'violacoes', 'error', 'error_description', 'message', 'txid']));
    }

    protected function erroConexao(ConnectionException $e): string
    {
        $msg = $e->getMessage();

        return match (true) {
            str_contains($msg, 'certificate') || str_contains($msg, 'SSL') => 'falha no certificado mTLS (confira se o certificado e a chave são o par certo e não venceram).',
            str_contains($msg, 'timed out') => 'tempo esgotado.',
            default => 'verifique a internet do servidor.',
        };
    }
}
