<?php

namespace App\Services\Fiscal;

use App\Exceptions\NegocioException;
use App\Models\Empresa;
use NFePHP\Common\Certificate;

class CertificadoA1Service
{
    /**
     * @return array{pfx:string, senha:string, certificate:\NFePHP\Common\Certificate}
     */
    public function carregar(Empresa $empresa): array
    {
        $pfx = $empresa->getRawOriginal('certificado_arquivo');
        if (is_resource($pfx)) {
            $pfx = stream_get_contents($pfx) ?: '';
        }
        $senha = (string) ($empresa->certificado_senha ?? '');

        if (! is_string($pfx) || $pfx === '' || $senha === '') {
            throw new NegocioException('Cadastre o certificado digital A1 e a senha em Dados da empresa antes de emitir.');
        }

        try {
            $certificate = Certificate::readPfx($pfx, $senha);
        } catch (\Throwable $e) {
            throw new NegocioException('Não foi possível ler o certificado A1. Verifique o arquivo .pfx e a senha.');
        }

        return [
            'pfx' => $pfx,
            'senha' => $senha,
            'certificate' => $certificate,
        ];
    }

    /**
     * Extrai PEM cert/key temporários para mTLS (NFS-e Nacional).
     *
     * @return array{cert:string, key:string, limpar:callable}
     */
    public function arquivosTemporariosPem(Empresa $empresa): array
    {
        $dados = $this->carregar($empresa);
        $certs = [];
        if (! openssl_pkcs12_read($dados['pfx'], $certs, $dados['senha'])) {
            throw new NegocioException('Falha ao abrir o PKCS#12 do certificado A1.');
        }

        $dir = sys_get_temp_dir();
        $certFile = tempnam($dir, 'cert');
        $keyFile = tempnam($dir, 'key');
        file_put_contents($certFile, $certs['cert']);
        file_put_contents($keyFile, $certs['pkey']);

        return [
            'cert' => $certFile,
            'key' => $keyFile,
            'limpar' => static function () use ($certFile, $keyFile): void {
                @unlink($certFile);
                @unlink($keyFile);
            },
        ];
    }
}
