<?php

namespace App\Services\Fiscal\NfseMunicipal\Providers;

use App\Exceptions\IntegrationException;
use App\Exceptions\NegocioException;
use App\Models\Empresa;
use App\Models\Hospedagem;
use App\Models\Unidade;
use App\Services\Fiscal\CertificadoA1Service;
use App\Services\Fiscal\NfseMunicipal\Contracts\NfseMunicipalProvider;
use App\Services\Fiscal\NfseMunicipal\NfseMunicipalResultado;
use App\Services\Fiscal\NfseMunicipal\NfseMunicipalSchemaValidator;
use App\Services\Fiscal\NfseMunicipal\NfseXmlAssinador;
use App\Services\Fiscal\NfseMunicipal\Soap\AbrasfSoapClient;
use App\Services\Fiscal\NfseMunicipal\Xml\AbrasfV2RpsBuilder;

/**
 * Provedor GISS ABRASF 2.04 (ex.: Maceió 2704302) — espelho TACBrNFSeProviderGiss204.
 *
 * Schemas XSD locais (validação antes do envio):
 *   storage/SchemasXSDgiss
 * Config: parque.nfse_giss_schemas_path / NFSE_GISS_SCHEMAS_PATH
 */
class GissAbrasf204Provider implements NfseMunicipalProvider
{
    public function __construct(
        protected CertificadoA1Service $certificadoA1,
        protected AbrasfV2RpsBuilder $builder,
        protected AbrasfSoapClient $soap,
        protected NfseMunicipalSchemaValidator $schemas,
        protected NfseXmlAssinador $assinador,
    ) {}

    public function emitir(
        Empresa $empresa,
        Unidade $unidade,
        Hospedagem $hospedagem,
        array $municipio,
        array $itens,
        int $serie,
        int $numeroRps,
        string $numeroLote,
    ): NfseMunicipalResultado {
        $url = $this->url($empresa, $municipio);
        $hospedagem->loadMissing('cliente');

        $montado = $this->builder->montarRps($empresa, $unidade, $hospedagem, $municipio, $itens, $serie, $numeroRps);
        // Assina InfDeclaracao (root = Rps) e depois o LoteRps (root = EnviarLoteRpsEnvio).
        $rpsAssinado = $this->assinar($empresa, $montado['rps'], 'InfDeclaracaoPrestacaoServico', 'Id', true, 'Rps');
        $rpsInner = $this->builder->extrairRpsParaLote($rpsAssinado);

        $lote = $this->builder->montarLoteEnvio($empresa, $unidade, $municipio, $numeroLote, $rpsInner);
        $loteAssinado = $this->assinar($empresa, $lote, 'LoteRps', 'Id', true, 'EnviarLoteRpsEnvio');
        $this->validarSchema($loteAssinado, 'enviar-lote-rps-envio-v2_04.xsd');
        $cabec = $this->builder->cabecalho();
        $this->validarSchema($cabec, 'cabecalho-v2_04.xsd');

        $pem = $this->certificadoA1->arquivosTemporariosPem($empresa);

        try {
            $resposta = $this->soap->chamar(
                $url,
                'http://nfse.abrasf.org.br/RecepcionarLoteRps',
                'RecepcionarLoteRpsRequest',
                $cabec,
                $loteAssinado,
                $pem,
            );

            return $this->interpretarEnvio($resposta, $loteAssinado, $numeroLote, (string) $serie, (string) $numeroRps);
        } catch (IntegrationException $e) {
            return NfseMunicipalResultado::erro($e->getMessage(), '', '');
        } catch (\Throwable $e) {
            return NfseMunicipalResultado::erro('Erro de comunicação GISS: '.$e->getMessage());
        } finally {
            ($pem['limpar'])();
        }
    }

    public function consultarLote(
        Empresa $empresa,
        Unidade $unidade,
        array $municipio,
        string $protocolo,
        string $numeroLote,
    ): NfseMunicipalResultado {
        $url = $this->url($empresa, $municipio);
        $consulta = $this->builder->montarConsultaLote($empresa, $unidade, $protocolo);
        $consultaAssinada = $this->assinar($empresa, $consulta, 'ConsultarLoteRpsEnvio', 'Id', false);
        $this->validarSchema($consultaAssinada, 'consultar-lote-rps-envio-v2_04.xsd');
        $cabec = $this->builder->cabecalho();
        $pem = $this->certificadoA1->arquivosTemporariosPem($empresa);

        try {
            $resposta = $this->soap->chamar(
                $url,
                'http://nfse.abrasf.org.br/ConsultarLoteRps',
                'ConsultarLoteRpsRequest',
                $cabec,
                $consultaAssinada,
                $pem,
            );

            return $this->interpretarConsulta($resposta, $protocolo, $numeroLote);
        } catch (IntegrationException $e) {
            $msg = $e->getMessage();
            if ($this->erroIndicaProcessando($msg)) {
                return NfseMunicipalResultado::processando($protocolo, $numeroLote);
            }

            return NfseMunicipalResultado::erro($msg, '', $protocolo);
        } catch (\Throwable $e) {
            return NfseMunicipalResultado::processando($protocolo, $numeroLote);
        } finally {
            ($pem['limpar'])();
        }
    }

    protected function validarSchema(string $xml, string $arquivoXsd): void
    {
        if (! config('parque.nfse_giss_validar_schema', true)) {
            return;
        }

        $base = $this->schemas->caminhoBase();
        if (! is_dir($base)) {
            throw new NegocioException(
                'Pasta de schemas GISS não encontrada. Coloque os XSD em: '
                .storage_path('SchemasXSDgiss')
                .' (ou defina NFSE_GISS_SCHEMAS_PATH). Arquivo esperado: '.$arquivoXsd
            );
        }

        $this->schemas->validar($xml, $arquivoXsd);
    }

    /**
     * @param  array{url_producao:string,url_homologacao:string}  $municipio
     */
    protected function url(Empresa $empresa, array $municipio): string
    {
        $producao = (int) ($empresa->ambiente_nfe ?? 2) === Empresa::AMBIENTE_PRODUCAO;
        $url = $producao
            ? (string) ($municipio['url_producao'] ?? '')
            : (string) ($municipio['url_homologacao'] ?? $municipio['url_producao'] ?? '');

        if ($url === '') {
            throw new NegocioException('URL do webservice NFS-e municipal não encontrada para este município.');
        }

        return $url;
    }

    protected function assinar(
        Empresa $empresa,
        string $xml,
        string $tag,
        string $idAttr,
        bool $obrigatorio = true,
        string $rootname = '',
    ): string {
        $cert = $this->certificadoA1->carregar($empresa);
        $xml = $this->garantirXmlUtf8($xml);

        if (! $obrigatorio && ! preg_match('/<'.$tag.'\b[^>]*\b'.$idAttr.'=/i', $xml)) {
            return $xml;
        }

        try {
            return $this->assinador->assinar(
                $cert['certificate'],
                $xml,
                $tag,
                $idAttr,
                ['rootname' => $rootname]
            );
        } catch (\Throwable $e) {
            if (! $obrigatorio) {
                return $xml;
            }
            throw new NegocioException('Falha ao assinar XML NFS-e municipal: '.$e->getMessage());
        }
    }

    protected function interpretarEnvio(
        string $resposta,
        string $xmlEnvio,
        string $numeroLote,
        string $serie,
        string $numeroRps,
    ): NfseMunicipalResultado {
        [$codigo, $mensagem] = $this->primeiroErro($resposta);
        $protocolo = $this->tagValor($resposta, 'Protocolo') ?? '';

        if ($codigo !== '' && ! $this->erroIndicaProcessando($mensagem)) {
            $r = NfseMunicipalResultado::erro($mensagem !== '' ? $mensagem : 'Lote rejeitado pela prefeitura.', $codigo, $protocolo);
            $r->xmlEnvio = $xmlEnvio;
            $r->numeroLote = $numeroLote;
            $r->retornoBruto = ['xml' => $resposta];

            return $r;
        }

        if ($protocolo !== '') {
            $r = NfseMunicipalResultado::processando($protocolo, $numeroLote, $xmlEnvio);
            $r->serie = $serie;
            $r->numero = $numeroRps;
            $r->retornoBruto = ['xml' => $resposta];

            return $r;
        }

        // Resposta síncrona rara com ListaNfse
        $autorizado = $this->extrairNfseAutorizada($resposta);
        if ($autorizado !== null) {
            $autorizado->xmlEnvio = $xmlEnvio;
            $autorizado->numeroLote = $numeroLote;
            $autorizado->retornoBruto = ['xml' => $resposta];

            return $autorizado;
        }

        return NfseMunicipalResultado::erro(
            $mensagem !== '' ? $mensagem : 'Envio não confirmado. Verifique a lista de erros.',
            $codigo,
        );
    }

    protected function interpretarConsulta(string $resposta, string $protocolo, string $numeroLote): NfseMunicipalResultado
    {
        [$codigo, $mensagem] = $this->primeiroErro($resposta);

        if ($codigo !== '' || $mensagem !== '') {
            if ($this->erroIndicaProcessando($mensagem)) {
                return NfseMunicipalResultado::processando($protocolo, $numeroLote);
            }
            $r = NfseMunicipalResultado::erro($mensagem, $codigo, $protocolo);
            $r->numeroLote = $numeroLote;
            $r->retornoBruto = ['xml' => $resposta];

            return $r;
        }

        $autorizado = $this->extrairNfseAutorizada($resposta);
        if ($autorizado !== null) {
            $autorizado->protocolo = $protocolo;
            $autorizado->numeroLote = $numeroLote;
            $autorizado->retornoBruto = ['xml' => $resposta];

            return $autorizado;
        }

        return NfseMunicipalResultado::processando($protocolo, $numeroLote);
    }

    protected function extrairNfseAutorizada(string $xml): ?NfseMunicipalResultado
    {
        if (! preg_match('/<(?:\w+:)?CompNfse\b/i', $xml) && ! preg_match('/<(?:\w+:)?Nfse\b/i', $xml)) {
            return null;
        }

        $numero = $this->tagValor($xml, 'Numero') ?? '';
        $codVerif = $this->tagValor($xml, 'CodigoVerificacao') ?? '';
        $serie = $this->tagValor($xml, 'Serie') ?? '';

        // Preferir Número da NFSe (primeira ocorrência após InfNfse)
        if (preg_match('/<(?:\w+:)?InfNfse\b[^>]*>.*?<(?:\w+:)?Numero>([^<]+)</is', $xml, $m)) {
            $numero = trim($m[1]);
        }
        if (preg_match('/<(?:\w+:)?CodigoVerificacao>([^<]+)</i', $xml, $m)) {
            $codVerif = trim($m[1]);
        }

        if ($numero === '' && $codVerif === '') {
            return null;
        }

        return new NfseMunicipalResultado(
            sucesso: true,
            numero: $numero,
            serie: $serie,
            codigoVerificacao: $codVerif,
            xmlAutorizado: $xml,
        );
    }

    /**
     * @return array{0:string,1:string}
     */
    protected function primeiroErro(string $xml): array
    {
        if (preg_match('/<(?:\w+:)?Codigo>([^<]+)<\/(?:\w+:)?Codigo>.*<(?:\w+:)?Mensagem>([^<]+)<\/(?:\w+:)?Mensagem>/is', $xml, $m)
            || preg_match('/<(?:\w+:)?Mensagem>([^<]+)<\/(?:\w+:)?Mensagem>.*<(?:\w+:)?Codigo>([^<]+)<\/(?:\w+:)?Codigo>/is', $xml, $m2)) {
            if (isset($m[1], $m[2])) {
                return [trim($m[1]), trim(html_entity_decode($m[2]))];
            }
            if (isset($m2[1], $m2[2])) {
                return [trim($m2[2]), trim(html_entity_decode($m2[1]))];
            }
        }

        $codigo = $this->tagValor($xml, 'Codigo') ?? '';
        $mensagem = $this->tagValor($xml, 'Mensagem')
            ?? $this->tagValor($xml, 'Descricao')
            ?? '';

        // Situação "4" etc. sem mensagem útil
        if ($mensagem === '' && preg_match('/ainda|aguard|processando|em processamento/i', $xml)) {
            $mensagem = 'Lote ainda não processado.';
        }

        return [$codigo, $mensagem];
    }

    protected function tagValor(string $xml, string $tag): ?string
    {
        if (preg_match('/<(?:\w+:)?'.$tag.'(?:\s[^>]*)?>([^<]*)<\/(?:\w+:)?'.$tag.'>/i', $xml, $m)) {
            $v = trim(html_entity_decode($m[1]));

            return $v !== '' ? $v : null;
        }

        return null;
    }

    /**
     * Espelho de ErroIndicaProcessando do Delphi.
     */
    public function erroIndicaProcessando(string $mensagem): bool
    {
        $l = mb_strtolower($mensagem, 'UTF-8');

        return str_contains($l, 'ainda')
            || str_contains($l, 'aguard')
            || str_contains($l, 'em processamento')
            || str_contains($l, 'processando');
    }

    protected function garantirXmlUtf8(string $xml): string
    {
        $xml = preg_replace('/^\xEF\xBB\xBF/', '', $xml) ?? $xml;
        if (! mb_check_encoding($xml, 'UTF-8')) {
            $xml = mb_convert_encoding($xml, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
        }
        $trimmed = ltrim($xml);
        $decl = '<'.'?xml version="1.0" encoding="UTF-8"?'.'>';
        if (! str_starts_with($trimmed, '<'.'?xml')) {
            return $decl.$trimmed;
        }

        return preg_replace('/^<\?xml[^?]*\?>/i', $decl, $trimmed, 1) ?? $trimmed;
    }
}
