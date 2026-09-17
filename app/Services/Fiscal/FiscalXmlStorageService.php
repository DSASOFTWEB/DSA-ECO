<?php

namespace App\Services\Fiscal;

use App\Models\DocumentoFiscal;
use Illuminate\Support\Facades\Storage;

/**
 * Persiste XMLs fiscais em disco (pasta privada) e devolve o caminho relativo
 * para gravar em documentos_fiscais — alinhado à regra do Gestor (XML no banco + pasta).
 *
 * Estrutura: fiscal/{empresa_id}/{modelo}/{ano}/{mes}/{arquivo}.xml
 */
class FiscalXmlStorageService
{
    public function salvarEnvio(DocumentoFiscal $documento, string $xml): ?string
    {
        return $this->gravar($documento, $xml, 'envio');
    }

    public function salvarAutorizado(DocumentoFiscal $documento, string $xml, ?string $chave = null): ?string
    {
        return $this->gravar($documento, $xml, 'autorizado', $chave);
    }

    /**
     * Decodifica nfseXmlGZipB64 / dpsXmlGZipB64 da API NFS-e Nacional.
     */
    public function decodificarGzipBase64(?string $payload): ?string
    {
        if ($payload === null || $payload === '') {
            return null;
        }

        $bin = base64_decode($payload, true);
        if ($bin === false || $bin === '') {
            return null;
        }

        $xml = @gzdecode($bin);
        if ($xml === false || $xml === '') {
            return null;
        }

        return $xml;
    }

    protected function gravar(DocumentoFiscal $documento, string $xml, string $tipo, ?string $chave = null): ?string
    {
        $xml = trim($xml);
        if ($xml === '') {
            return null;
        }

        $ident = preg_replace('/\D+/', '', (string) ($chave ?: $documento->chave ?: $documento->numero ?: $documento->id));
        if ($ident === '') {
            $ident = (string) $documento->id;
        }

        $modelo = strtolower((string) $documento->modelo);
        $ano = now()->format('Y');
        $mes = now()->format('m');
        $sufixo = $tipo === 'envio' ? 'envio' : $modelo;
        $nome = "{$ident}-{$sufixo}.xml";
        $relativo = "fiscal/{$documento->empresa_id}/{$modelo}/{$ano}/{$mes}/{$nome}";

        Storage::disk('local')->put($relativo, $xml);

        return $relativo;
    }

    public function caminhoAbsoluto(?string $relativo): ?string
    {
        if ($relativo === null || $relativo === '') {
            return null;
        }

        if (! Storage::disk('local')->exists($relativo)) {
            return null;
        }

        return Storage::disk('local')->path($relativo);
    }

    public function ler(?string $relativo): ?string
    {
        if ($relativo === null || $relativo === '' || ! Storage::disk('local')->exists($relativo)) {
            return null;
        }

        return Storage::disk('local')->get($relativo);
    }
}
