<?php

namespace App\Services\Integrations\Pix;

use App\Models\Empresa;
use Carbon\Carbon;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Certificado mTLS de um gateway Pix bancário (Itaú, Sicredi): cópia cifrada
 * no banco (configuracoes->gateway_pix->{provedor}) + arquivos em
 * storage/app/gateways/{provedor}. O banco sobrevive rebuild; o arquivo é o
 * que o cliente HTTP usa. Se o arquivo sumir, restaura a partir do banco.
 */
abstract class CertificadoPixService
{
    public const TIPOS_CHAVE = [
        'cnpj' => 'CNPJ',
        'cpf' => 'CPF',
        'email' => 'E-mail',
        'telefone' => 'Telefone',
        'aleatoria' => 'Chave aleatória (EVP)',
    ];

    /** Chave do provedor em configuracoes->gateway_pix e pasta em storage. */
    abstract public function provedor(): string;

    protected function rotulo(): string
    {
        return ucfirst($this->provedor());
    }

    public function caminhoCertificado(Empresa $empresa): string
    {
        return "gateways/{$this->provedor()}/empresa-{$empresa->getKey()}.crt";
    }

    public function caminhoChavePrivada(Empresa $empresa): string
    {
        return "gateways/{$this->provedor()}/empresa-{$empresa->getKey()}.key";
    }

    /**
     * @return array{certificado: ?string, chave_privada: ?string, validade: ?Carbon, assunto: ?string}
     */
    public function lerDoBanco(Empresa $empresa): array
    {
        $cert = $this->decifrar(Arr::get($empresa->configuracoes, "gateway_pix.{$this->provedor()}.certificado_pem"));
        $chave = $this->decifrar(Arr::get($empresa->configuracoes, "gateway_pix.{$this->provedor()}.chave_privada_pem"));

        return [
            'certificado' => $cert,
            'chave_privada' => $chave,
            'validade' => $this->validadeDe($cert),
            'assunto' => $this->assuntoDe($cert),
        ];
    }

    public function temNoBanco(Empresa $empresa): bool
    {
        $dados = $this->lerDoBanco($empresa);

        return filled($dados['certificado']) && filled($dados['chave_privada']);
    }

    public function temNoDisco(Empresa $empresa): bool
    {
        $disco = Storage::disk('local');

        return $disco->exists($this->caminhoCertificado($empresa))
            && $disco->exists($this->caminhoChavePrivada($empresa));
    }

    public function temCertificado(Empresa $empresa): bool
    {
        return $this->temNoBanco($empresa) || $this->temNoDisco($empresa);
    }

    /**
     * Garante arquivos no disco a partir do banco (e vice-versa).
     * Retorna true se o par ficou disponível para o cliente HTTP.
     */
    public function garantirArquivos(Empresa $empresa): bool
    {
        $disco = Storage::disk('local');
        $doBanco = $this->lerDoBanco($empresa);
        $certBanco = $doBanco['certificado'];
        $chaveBanco = $doBanco['chave_privada'];

        $certDisco = $disco->exists($this->caminhoCertificado($empresa))
            ? $disco->get($this->caminhoCertificado($empresa))
            : null;
        $chaveDisco = $disco->exists($this->caminhoChavePrivada($empresa))
            ? $disco->get($this->caminhoChavePrivada($empresa))
            : null;

        // Banco tem → restaura disco se faltar ou divergir.
        if (filled($certBanco) && filled($chaveBanco)) {
            if ($certDisco !== $certBanco || $chaveDisco !== $chaveBanco) {
                $disco->put($this->caminhoCertificado($empresa), $certBanco);
                $disco->put($this->caminhoChavePrivada($empresa), $chaveBanco);
                Log::info("{$this->rotulo()} Pix: certificado restaurado do banco para o disco", [
                    'empresa_id' => $empresa->getKey(),
                ]);
            }

            return true;
        }

        // Só disco → faz backup no banco (empresas antigas / 1ª sincronização).
        if (filled($certDisco) && filled($chaveDisco)) {
            $this->persistirNoBanco($empresa, $certDisco, $chaveDisco);
            Log::info("{$this->rotulo()} Pix: certificado do disco copiado para o banco", [
                'empresa_id' => $empresa->getKey(),
            ]);

            return true;
        }

        return false;
    }

    /**
     * Grava PEM no banco (cifrado) e nos arquivos. Retorna metadados para o JSON.
     *
     * @return array{certificado_pem: string, chave_privada_pem: string, certificado_validade: ?string, certificado_assunto: ?string}
     */
    public function gravarPar(Empresa $empresa, string $certificadoPem, string $chavePem): array
    {
        Storage::disk('local')->put($this->caminhoCertificado($empresa), $certificadoPem);
        Storage::disk('local')->put($this->caminhoChavePrivada($empresa), $chavePem);

        $validade = $this->validadeDe($certificadoPem);
        $assunto = $this->assuntoDe($certificadoPem);

        return [
            'certificado_pem' => Crypt::encryptString($certificadoPem),
            'chave_privada_pem' => Crypt::encryptString($chavePem),
            'certificado_validade' => $validade?->toIso8601String(),
            'certificado_assunto' => $assunto,
        ];
    }

    public function persistirNoBanco(Empresa $empresa, string $certificadoPem, string $chavePem): void
    {
        $meta = $this->gravarPar($empresa, $certificadoPem, $chavePem);
        $config = $empresa->configuracoes ?? [];
        $atual = Arr::get($config, "gateway_pix.{$this->provedor()}", []);
        $config['gateway_pix'][$this->provedor()] = array_merge($atual, $meta);
        $empresa->forceFill(['configuracoes' => $config])->saveQuietly();
    }

    public function removerArquivos(Empresa $empresa): void
    {
        Storage::disk('local')->delete([
            $this->caminhoCertificado($empresa),
            $this->caminhoChavePrivada($empresa),
            "gateways/{$this->provedor()}/token-empresa-{$empresa->getKey()}.json",
        ]);
    }

    public function limparCacheToken(Empresa $empresa): void
    {
        Storage::disk('local')->delete("gateways/{$this->provedor()}/token-empresa-{$empresa->getKey()}.json");
    }

    /**
     * Banco costuma entregar o certificado em DER (binário): converte para PEM,
     * que é o formato aceito pelo cURL/OpenSSL. PEM chega intacto.
     */
    public function paraPem(string $conteudo, string $tipo = 'CERTIFICATE'): string
    {
        if (str_contains($conteudo, '-----BEGIN')) {
            return trim($conteudo)."\n";
        }

        return "-----BEGIN {$tipo}-----\n".chunk_split(base64_encode($conteudo), 64, "\n")."-----END {$tipo}-----\n";
    }

    public function validadeDe(?string $certificadoPem): ?Carbon
    {
        if (! filled($certificadoPem)) {
            return null;
        }

        $cert = @openssl_x509_read($certificadoPem);
        if ($cert === false) {
            return null;
        }

        $dados = openssl_x509_parse($cert);
        if (! is_array($dados) || empty($dados['validTo_time_t'])) {
            return null;
        }

        return Carbon::createFromTimestamp((int) $dados['validTo_time_t']);
    }

    public function assuntoDe(?string $certificadoPem): ?string
    {
        if (! filled($certificadoPem)) {
            return null;
        }

        $cert = @openssl_x509_read($certificadoPem);
        if ($cert === false) {
            return null;
        }

        $dados = openssl_x509_parse($cert);
        if (! is_array($dados)) {
            return null;
        }

        return $dados['subject']['CN'] ?? $dados['subject']['commonName'] ?? null;
    }

    /**
     * Normaliza a chave conforme o tipo escolhido na empresa.
     */
    public function normalizarChave(string $chave, ?string $tipo = null): string
    {
        $chave = trim($chave);
        $tipo = $tipo ?: $this->detectarTipo($chave);

        return match ($tipo) {
            'cnpj', 'cpf' => preg_replace('/\D+/', '', $chave) ?? $chave,
            'telefone' => $this->normalizarTelefone($chave),
            'email' => strtolower($chave),
            default => $chave,
        };
    }

    public function detectarTipo(string $chave): string
    {
        $chave = trim($chave);
        $digitos = preg_replace('/\D+/', '', $chave) ?? '';

        if (filter_var($chave, FILTER_VALIDATE_EMAIL)) {
            return 'email';
        }
        if (strlen($digitos) === 14 && preg_match('/^[\d.\-\/]+$/', $chave)) {
            return 'cnpj';
        }
        if (strlen($digitos) === 11 && preg_match('/^[\d.\-]+$/', $chave)) {
            return 'cpf';
        }
        if (preg_match('/^\+?\d{10,14}$/', $digitos) || preg_match('/^\+55\d{10,11}$/', preg_replace('/\D+/', '', $chave) ?? '')) {
            return 'telefone';
        }

        return 'aleatoria';
    }

    protected function normalizarTelefone(string $chave): string
    {
        $digitos = preg_replace('/\D+/', '', $chave) ?? '';

        if (str_starts_with($digitos, '55') && strlen($digitos) >= 12) {
            return '+'.$digitos;
        }

        if (strlen($digitos) >= 10 && strlen($digitos) <= 11) {
            return '+55'.$digitos;
        }

        return str_starts_with($chave, '+') ? $chave : '+'.$digitos;
    }

    protected function decifrar(mixed $valor): ?string
    {
        if (! is_string($valor) || $valor === '') {
            return null;
        }

        try {
            return Crypt::decryptString($valor);
        } catch (DecryptException) {
            // Conteúdo antigo em texto puro (não deve acontecer) — ignora.
            return str_contains($valor, 'BEGIN') ? $valor : null;
        }
    }
}
