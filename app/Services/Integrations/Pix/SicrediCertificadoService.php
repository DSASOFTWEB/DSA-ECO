<?php

namespace App\Services\Integrations\Pix;

use App\Exceptions\NegocioException;
use App\Models\Empresa;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * Certificado mTLS da API Pix Sicredi. O cliente registra um CSR no Portal
 * do Desenvolvedor e recebe o certificado assinado; para ele não precisar
 * mexer com OpenSSL, o sistema gera aqui a chave privada + o CSR no padrão
 * do manual Sicredi. A chave fica guardada (cifrada) até o certificado
 * voltar e ser enviado na tela — aí os dois viram o par mTLS.
 */
class SicrediCertificadoService extends CertificadoPixService
{
    public function provedor(): string
    {
        return 'sicredi';
    }

    /** Nome do parceiro no CN do CSR (api-parceiros-{nome}.sicredi.net). */
    public function nomeParceiroPadrao(Empresa $empresa): string
    {
        return Str::of($empresa->nome ?: $empresa->razao_social ?: 'empresa')
            ->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '')->limit(40, '')->value() ?: 'empresa';
    }

    /**
     * Gera chave RSA 2048 + CSR (SHA-256) com o assunto exigido pelo Sicredi
     * e guarda a chave pendente. Gerar de novo substitui o anterior — o CSR
     * antigo deixa de valer, pois a chave dele é descartada.
     *
     * @return string CSR em PEM, para o cliente enviar no Portal
     */
    public function gerarCsr(Empresa $empresa, string $nomeParceiro): string
    {
        $cnpj = preg_replace('/\D+/', '', (string) $empresa->cnpj) ?? '';
        if (! in_array(strlen($cnpj), [11, 14], true)) {
            throw new NegocioException('Cadastre o CNPJ da empresa (aba Geral) antes de gerar o CSR do Sicredi.');
        }

        $nomeParceiro = Str::of($nomeParceiro)->ascii()->lower()->replaceMatches('/[^a-z0-9-]+/', '')->value();
        if ($nomeParceiro === '') {
            $nomeParceiro = $this->nomeParceiroPadrao($empresa);
        }

        $chave = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $csr = $chave ? openssl_csr_new($this->assuntoCsr($nomeParceiro, $cnpj), $chave, ['digest_alg' => 'sha256']) : false;

        if (! $chave || ! $csr || ! openssl_pkey_export($chave, $chavePem) || ! openssl_csr_export($csr, $csrPem)) {
            throw new NegocioException('Não foi possível gerar o CSR neste servidor (OpenSSL).');
        }

        $config = $empresa->configuracoes ?? [];
        $config['gateway_pix']['sicredi'] = array_merge(Arr::get($config, 'gateway_pix.sicredi', []), [
            'csr_pem' => $csrPem,
            'csr_chave_privada_pem' => Crypt::encryptString($chavePem),
            'csr_nome_parceiro' => $nomeParceiro,
            'csr_gerado_em' => now()->toIso8601String(),
        ]);
        $empresa->forceFill(['configuracoes' => $config])->saveQuietly();

        return $csrPem;
    }

    /**
     * Assunto do CSR conforme o manual da API Pix Sicredi.
     *
     * @return array<string, string>
     */
    public function assuntoCsr(string $nomeParceiro, string $cpfCnpj): array
    {
        return [
            'countryName' => 'BR',
            'organizationName' => 'Confederacao Interestadual das Cooperativas Ligadas ao Sicredi',
            'commonName' => "api-parceiros-{$nomeParceiro}.sicredi.net",
            'stateOrProvinceName' => 'RS',
            'localityName' => 'Porto Alegre',
            'jurisdictionCountryName' => 'BR',
            'organizationalUnitName' => 'API Pix Sicredi',
            'serialNumber' => "urn:cpfcnpj:{$cpfCnpj}",
        ];
    }

    public function csrPendente(Empresa $empresa): ?string
    {
        $csr = Arr::get($empresa->configuracoes, 'gateway_pix.sicredi.csr_pem');

        return is_string($csr) && $csr !== '' ? $csr : null;
    }

    /** Chave privada gerada junto com o CSR (para casar com o certificado que o Sicredi devolver). */
    public function chaveDoCsr(Empresa $empresa): ?string
    {
        return $this->decifrar(Arr::get($empresa->configuracoes, 'gateway_pix.sicredi.csr_chave_privada_pem'));
    }
}
