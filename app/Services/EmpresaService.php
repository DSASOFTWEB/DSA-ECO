<?php

namespace App\Services;

use App\Exceptions\NegocioException;
use App\Models\Empresa;
use App\Services\Fiscal\CertificadoA1Service;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class EmpresaService
{
    public function __construct(protected CertificadoA1Service $certificadoA1) {}

    public function atualizar(Empresa $empresa, array $dados, ?UploadedFile $logo = null, ?UploadedFile $certificado = null): Empresa
    {
        $endereco = $dados['endereco'] ?? null;

        $camposIntegracao = [
            'mercadopago_access_token', 'mercadopago_public_key', 'mercadopago_webhook_secret',
            'evolution_base_url', 'evolution_api_key', 'evolution_instance',
            'impressao_modo', 'impressao_colunas', 'impressao_agente_url', 'impressao_auto_imprimir',
            'gateway_pix_provedor', 'gateway_pix_expiracao_minutos', 'itau_client_id', 'itau_client_secret', 'itau_chave_pix',
            'itau_certificado', 'itau_chave_privada',
        ];

        $camposFiscaisSecretos = [
            'certificado_senha', 'token_nfse', 'bluesoft_token', 'token_ibpt', 'csc', 'nfse_ws_senha',
        ];

        $camposFiscais = [
            'ie', 'im', 'cnae', 'regime_tributario', 'aut_xml', 'ambiente_nfe',
            'csc', 'csc_id',
            'numero_serie_nfe', 'numero_serie_nfce', 'numero_serie_nfse',
            'numero_ultima_nfe_producao', 'numero_ultima_nfe_homologacao',
            'numero_ultima_nfce_producao', 'numero_ultima_nfce_homologacao',
            'numero_ultima_nfse',
            'nfse_provider', 'nfse_auth_mode', 'nfse_nacional_habilitado',
            'token_nfse', 'token_ibpt', 'bluesoft_token',
            'nfse_ws_user', 'nfse_ws_senha', 'nfse_ws_chave_acesso',
            'certificado_senha',
            'observacao_padrao_nfe', 'observacao_padrao_nfce',
            'codigo_municipio_ibge', 'codigo_servico_hospedagem_lc116', 'codigo_tributacao_municipal_hospedagem', 'aliquota_iss_hospedagem',
        ];

        $integracoes = array_intersect_key($dados, array_flip($camposIntegracao));
        $fiscais = array_intersect_key($dados, array_flip($camposFiscais));

        unset($dados['endereco'], $dados['logo'], $dados['certificado']);
        foreach (array_merge($camposIntegracao, $camposFiscais) as $campo) {
            unset($dados[$campo]);
        }

        if ($logo) {
            if ($empresa->logo_path) {
                Storage::disk('public')->delete($empresa->logo_path);
            }

            $dados['logo_path'] = $logo->store('logos', 'public');
        }

        if ($certificado) {
            $conteudo = $certificado->get();
            $senha = trim((string) ($fiscais['certificado_senha'] ?? ''));

            if (! is_string($conteudo) || $conteudo === '') {
                throw ValidationException::withMessages([
                    'certificado' => 'O arquivo do certificado está vazio. Selecione novamente o .pfx ou .p12.',
                ]);
            }

            if ($senha === '') {
                throw ValidationException::withMessages([
                    'certificado_senha' => 'Informe a senha do certificado que está sendo enviado.',
                ]);
            }

            try {
                // Mesmo fluxo usado no ZeusWeb: valida exatamente os bytes e a
                // senha recebidos antes de substituir o certificado do banco.
                $this->certificadoA1->validar($conteudo, $senha);
            } catch (NegocioException $e) {
                throw ValidationException::withMessages([
                    'certificado' => $e->getMessage(),
                ]);
            }

            $dados['certificado_arquivo'] = $conteudo;
        }

        foreach ($camposFiscaisSecretos as $campo) {
            if (! filled($fiscais[$campo] ?? null)) {
                unset($fiscais[$campo]);
            }
        }

        if (array_key_exists('nfse_nacional_habilitado', $fiscais)) {
            $fiscais['nfse_nacional_habilitado'] = (bool) $fiscais['nfse_nacional_habilitado'];
        }

        $configuracoes = $empresa->configuracoes ?? [];
        $configuracoes['endereco'] = $endereco;
        $configuracoes['mercadopago'] = $this->mesclarSemApagar($configuracoes['mercadopago'] ?? [], [
            'access_token' => $integracoes['mercadopago_access_token'] ?? null,
            'public_key' => $integracoes['mercadopago_public_key'] ?? null,
            'webhook_secret' => $integracoes['mercadopago_webhook_secret'] ?? null,
        ]);
        $configuracoes['evolution'] = $this->mesclarSemApagar($configuracoes['evolution'] ?? [], [
            'base_url' => $integracoes['evolution_base_url'] ?? null,
            'api_key' => $integracoes['evolution_api_key'] ?? null,
            'instance' => $integracoes['evolution_instance'] ?? null,
        ]);

        // Impressão: campos com default explícito — sempre grava (não usa mesclarSemApagar
        // de senha), inclusive checkbox desmarcado e troca de modo.
        $configuracoes['impressao'] = [
            'modo' => $integracoes['impressao_modo'] ?? 'dom',
            'colunas' => (int) ($integracoes['impressao_colunas'] ?? 48),
            'agente_url' => filled($integracoes['impressao_agente_url'] ?? null)
                ? $integracoes['impressao_agente_url']
                : (string) config('parque.escpos_agente_url', 'http://127.0.0.1:9110'),
            'auto_imprimir' => (bool) ($integracoes['impressao_auto_imprimir'] ?? false),
        ];

        $arquivosItau = $this->lerParCertificadoItau(
            $empresa,
            $integracoes['itau_certificado'] ?? null,
            $integracoes['itau_chave_privada'] ?? null,
        );

        if (array_key_exists('gateway_pix_provedor', $integracoes)) {
            $itauAtual = $configuracoes['gateway_pix']['itau'] ?? [];
            unset($itauAtual['expiracao_minutos']);
            $segredo = $integracoes['itau_client_secret'] ?? null;
            $expiracaoAtual = $empresa->configuracaoGatewayPix()['expiracao_minutos'];

            $configuracoes['gateway_pix'] = [
                'provedor' => $integracoes['gateway_pix_provedor'],
                'expiracao_minutos' => (int) ($integracoes['gateway_pix_expiracao_minutos'] ?? $expiracaoAtual) ?: 10,
                'itau' => $this->mesclarSemApagar($itauAtual, [
                    'client_id' => $integracoes['itau_client_id'] ?? null,
                    'chave_pix' => $integracoes['itau_chave_pix'] ?? null,
                    'client_secret' => filled($segredo) ? Crypt::encryptString($segredo) : null,
                ]),
            ];
        }

        $dados['configuracoes'] = $configuracoes;
        $dados = array_merge($dados, $fiscais);

        DB::transaction(function () use ($empresa, $dados, $arquivosItau): void {
            $empresa->update($dados);

            foreach ($arquivosItau as $caminho => $conteudo) {
                Storage::disk('local')->put($caminho, $conteudo);
            }

            if (isset($dados['certificado_arquivo'])) {
                $gravado = Storage::disk('local')->put(
                    $empresa->certificadoCaminho(),
                    $dados['certificado_arquivo']
                );

                if (! $gravado) {
                    throw ValidationException::withMessages([
                        'certificado' => 'Não foi possível guardar a cópia persistente do certificado. Tente novamente.',
                    ]);
                }
            }
        });

        return $empresa->fresh();
    }

    public function removerCertificado(Empresa $empresa): Empresa
    {
        DB::transaction(function () use ($empresa): void {
            $empresa->update([
                'certificado_arquivo' => null,
                'certificado_senha' => null,
                'certificado_validade' => null,
            ]);

            Storage::disk('local')->delete($empresa->certificadoCaminho());
        });

        return $empresa->fresh();
    }

    /**
     * Confere que o certificado e a chave privada do Itaú formam um par
     * (quando só um dos dois é reenviado, compara com o outro já salvo).
     *
     * @return array<string, string> caminho no disco local => conteúdo PEM
     */
    protected function lerParCertificadoItau(Empresa $empresa, mixed $certificado, mixed $chave): array
    {
        $certificado = $certificado instanceof UploadedFile ? (string) $certificado->get() : null;
        $chave = $chave instanceof UploadedFile ? (string) $chave->get() : null;

        if ($certificado === null && $chave === null) {
            return [];
        }

        $disco = Storage::disk('local');
        $certParaConferir = $certificado ?? ($disco->exists($empresa->itauCertificadoCaminho()) ? $disco->get($empresa->itauCertificadoCaminho()) : null);
        $chaveParaConferir = $chave ?? ($disco->exists($empresa->itauChavePrivadaCaminho()) ? $disco->get($empresa->itauChavePrivadaCaminho()) : null);

        if ($certParaConferir === null || $chaveParaConferir === null) {
            throw ValidationException::withMessages([
                'itau_certificado' => 'Envie o certificado (.crt) e a chave privada (.key) juntos.',
            ]);
        }

        if (@openssl_x509_read($certParaConferir) === false) {
            throw ValidationException::withMessages([
                'itau_certificado' => 'O certificado do Itaú não é um PEM válido.',
            ]);
        }

        if (@openssl_pkey_get_private($chaveParaConferir) === false) {
            throw ValidationException::withMessages([
                'itau_chave_privada' => 'A chave privada não é um PEM válido (ou está protegida por senha).',
            ]);
        }

        if (! @openssl_x509_check_private_key($certParaConferir, $chaveParaConferir)) {
            throw ValidationException::withMessages([
                'itau_chave_privada' => 'A chave privada não corresponde ao certificado enviado.',
            ]);
        }

        return array_filter([
            $empresa->itauCertificadoCaminho() => $certificado,
            $empresa->itauChavePrivadaCaminho() => $chave,
        ], fn ($conteudo) => $conteudo !== null);
    }

    /**
     * Campo enviado em branco no formulário = mantém o valor já salvo (não
     * apaga a credencial sem querer só porque o operador deixou o campo de
     * senha/token vazio ao salvar outra coisa na mesma tela).
     */
    protected function mesclarSemApagar(array $atual, array $novo): array
    {
        foreach ($novo as $chave => $valor) {
            if (filled($valor)) {
                $atual[$chave] = $valor;
            }
        }

        return $atual;
    }
}
