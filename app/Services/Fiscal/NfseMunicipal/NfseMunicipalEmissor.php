<?php

namespace App\Services\Fiscal\NfseMunicipal;

use App\Exceptions\IntegrationException;
use App\Exceptions\NegocioException;
use App\Models\DocumentoFiscal;
use App\Models\Empresa;
use App\Models\Hospedagem;
use App\Models\Unidade;
use App\Services\Fiscal\FiscalXmlStorageService;
use App\Services\Fiscal\NfseMunicipal\Contracts\NfseMunicipalProvider;
use App\Services\Fiscal\NfseMunicipal\Providers\GissAbrasf204Provider;

/**
 * Fachada municipal (= TEmissorNFSe): resolve provedor pelo IBGE e emite/consulta.
 */
class NfseMunicipalEmissor
{
    public function __construct(
        protected MunicipioNfseCatalog $catalog,
        protected GissAbrasf204Provider $giss,
        protected FiscalXmlStorageService $xmlStorage,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $itens
     */
    public function emitir(
        Empresa $empresa,
        Unidade $unidade,
        Hospedagem $hospedagem,
        DocumentoFiscal $documento,
        array $itens,
    ): DocumentoFiscal {
        $municipio = $this->catalog->resolve((string) $empresa->codigo_municipio_ibge);
        $provider = $this->providerPara($municipio);

        $serie = (int) ($empresa->numero_serie_nfse ?: 1);
        $numero = ((int) $empresa->numero_ultima_nfse) + 1;
        $numeroLote = (string) max(1, $numero);
        $valor = round(collect($itens)->sum(fn ($i) => ((float) $i['quantidade']) * ((float) $i['valor_unitario'])), 2);

        $documento->update([
            'status' => DocumentoFiscal::STATUS_PROCESSANDO,
            'serie' => $serie,
            'numero' => $numero,
            'itens' => $itens,
            'valor_total' => $valor,
        ]);

        $resultado = $provider->emitir(
            $empresa,
            $unidade,
            $hospedagem,
            $municipio,
            $itens,
            $serie,
            $numero,
            $numeroLote,
        );

        if ($resultado->xmlEnvio !== '') {
            $pathEnvio = $this->xmlStorage->salvarEnvio($documento, $resultado->xmlEnvio);
            $documento->update([
                'xml' => $resultado->xmlEnvio,
                'xml_envio_path' => $pathEnvio,
            ]);
        }

        // Consulta imediata após envio assíncrono (ConsultaLoteAposEnvio do ACBr).
        if ($resultado->emProcessamento && $resultado->protocolo !== '') {
            $consulta = $provider->consultarLote(
                $empresa,
                $unidade,
                $municipio,
                $resultado->protocolo,
                $numeroLote,
            );
            if ($consulta->sucesso || (! $consulta->emProcessamento && $consulta->mensagemErro !== '')) {
                $resultado = $consulta;
                if ($resultado->xmlEnvio === '' && $documento->xml) {
                    $resultado->xmlEnvio = (string) $documento->xml;
                }
            } else {
                $resultado = $consulta;
            }
        }

        return $this->aplicarResultado($empresa, $documento, $resultado, $numeroLote);
    }

    public function consultarLoteDocumento(
        Empresa $empresa,
        Unidade $unidade,
        DocumentoFiscal $documento,
    ): DocumentoFiscal {
        if ($documento->status !== DocumentoFiscal::STATUS_PROCESSANDO || ! filled($documento->protocolo)) {
            throw new NegocioException('Documento não está aguardando processamento de lote municipal.');
        }

        $municipio = $this->catalog->resolve((string) $empresa->codigo_municipio_ibge);
        $provider = $this->providerPara($municipio);
        $numeroLote = (string) ($documento->retorno['numero_lote'] ?? $documento->numero ?? '1');

        $resultado = $provider->consultarLote(
            $empresa,
            $unidade,
            $municipio,
            (string) $documento->protocolo,
            $numeroLote,
        );

        return $this->aplicarResultado($empresa, $documento, $resultado, $numeroLote);
    }

    /**
     * @param  array{provedor:string,nome:string,ibge:string}  $municipio
     */
    protected function providerPara(array $municipio): NfseMunicipalProvider
    {
        $provedor = strtolower(trim((string) ($municipio['provedor'] ?? '')));

        if ($provedor === '' || $provedor === 'naosuportado') {
            throw new NegocioException(
                'Município '.$municipio['nome'].' ('.$municipio['ibge'].') sem provedor NFS-e municipal no catálogo ACBr.'
            );
        }

        if ($provedor === 'padraonacional') {
            throw new NegocioException(
                'Este município usa NFS-e Nacional (Gov.br). Habilite a opção “NFS-e Nacional” em Dados da empresa.'
            );
        }

        if ($provedor === 'giss') {
            return $this->giss;
        }

        throw new NegocioException(
            'Provedor NFS-e municipal "'.$municipio['provedor'].'" ainda não suportado neste sistema '
            .'(município '.$municipio['nome'].'). Na v1 apenas GISS está disponível.'
        );
    }

    protected function aplicarResultado(
        Empresa $empresa,
        DocumentoFiscal $documento,
        NfseMunicipalResultado $resultado,
        string $numeroLote,
    ): DocumentoFiscal {
        $retorno = array_merge($resultado->retornoBruto, [
            'numero_lote' => $numeroLote,
            'codigo_erro' => $resultado->codigoErro,
            'provedor' => 'municipal',
        ]);

        if ($resultado->sucesso) {
            $xmlAut = $resultado->xmlAutorizado !== '' ? $resultado->xmlAutorizado : $resultado->xmlEnvio;
            $chave = $resultado->codigoVerificacao !== '' ? $resultado->codigoVerificacao : null;
            $xmlPath = $xmlAut !== '' ? $this->xmlStorage->salvarAutorizado($documento, $xmlAut, $chave) : null;

            $documento->update([
                'status' => DocumentoFiscal::STATUS_AUTORIZADO,
                'numero' => $resultado->numero !== '' ? (int) preg_replace('/\D+/', '', $resultado->numero) ?: $documento->numero : $documento->numero,
                'serie' => $resultado->serie !== '' ? (int) $resultado->serie : $documento->serie,
                'chave' => $chave,
                'protocolo' => $resultado->protocolo !== '' ? $resultado->protocolo : $documento->protocolo,
                'recibo' => $numeroLote,
                'xml_protocolado' => $xmlAut !== '' ? $xmlAut : null,
                'xml_path' => $xmlPath,
                'autorizado_em' => now(),
                'retorno' => $retorno,
                'mensagem_erro' => null,
            ]);
            $empresa->update(['numero_ultima_nfse' => (int) $documento->fresh()->numero]);

            return $documento->fresh();
        }

        if ($resultado->emProcessamento) {
            $documento->update([
                'status' => DocumentoFiscal::STATUS_PROCESSANDO,
                'protocolo' => $resultado->protocolo !== '' ? $resultado->protocolo : $documento->protocolo,
                'recibo' => $numeroLote,
                'retorno' => $retorno,
                'mensagem_erro' => $resultado->mensagemErro,
            ]);

            return $documento->fresh();
        }

        $documento->update([
            'status' => DocumentoFiscal::STATUS_REJEITADO,
            'protocolo' => $resultado->protocolo !== '' ? $resultado->protocolo : $documento->protocolo,
            'mensagem_erro' => substr($resultado->mensagemErro !== '' ? $resultado->mensagemErro : 'Rejeição municipal.', 0, 800),
            'retorno' => $retorno,
        ]);

        throw new IntegrationException($documento->mensagem_erro, servico: 'nfse-municipal', respostaBruta: $retorno);
    }
}
