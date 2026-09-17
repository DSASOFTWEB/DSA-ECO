<?php

namespace App\Services\Fiscal;

use App\Exceptions\IntegrationException;
use App\Exceptions\NegocioException;
use App\Models\DocumentoFiscal;
use App\Models\Empresa;
use App\Models\Hospedagem;
use App\Models\Unidade;
use App\Services\Fiscal\NfseMunicipal\NfseMunicipalEmissor;
use Illuminate\Support\Facades\Http;
use NFePHP\Common\Signer;

/**
 * Emite NFS-e: Nacional (DPS/SEFIN) quando habilitada; senão municipal
 * (ACBr: IBGE → provedor, v1 = GISS ABRASF 2.04).
 */
class NfseEmissaoService
{
    private const FUSO_EMISSAO = 'America/Sao_Paulo';

    private const MARGEM_RELOGIO_MINUTOS = 5;

    public function __construct(
        protected CertificadoA1Service $certificadoA1,
        protected FiscalXmlStorageService $xmlStorage,
        protected NfseMunicipalEmissor $municipal,
    ) {}

    /**
     * @param  list<array{descricao:string, quantidade:float|int, valor_unitario:float, codigo_servico_lc116?:?string, cnae?:?string, nbs?:?string, aliq_iss?:?float}>  $itens
     */
    public function emitir(Empresa $empresa, Unidade $unidade, Hospedagem $hospedagem, DocumentoFiscal $documento, array $itens): DocumentoFiscal
    {
        if ($itens === []) {
            throw new NegocioException('Não há serviços/diárias para emitir NFS-e nesta hospedagem.');
        }

        $cMun = preg_replace('/\D+/', '', (string) $empresa->codigo_municipio_ibge);
        if (strlen((string) $cMun) !== 7) {
            throw new NegocioException('Informe o código IBGE do município (7 dígitos) em Dados da empresa.');
        }

        // Nacional desabilitada → prefeitura (catálogo ACBr / GISS).
        if (! $empresa->nfse_nacional_habilitado) {
            return $this->municipal->emitir($empresa, $unidade, $hospedagem, $documento, $itens);
        }

        $valor = round(collect($itens)->sum(fn ($i) => ((float) $i['quantidade']) * ((float) $i['valor_unitario'])), 2);
        $serie = (int) ($empresa->numero_serie_nfse ?: 1);
        $numero = ((int) $empresa->numero_ultima_nfse) + 1;

        $descricao = collect($itens)->map(fn ($i) => $i['descricao'])->implode('; ');
        $lc116 = (string) ($itens[0]['codigo_servico_lc116'] ?? '');
        if (! filled($lc116)) {
            $lc116 = (string) ($empresa->codigo_servico_hospedagem_lc116 ?: '09.01.05');
        }
        // O item genérico 09.01 não é um CTN N6 aceito para a atividade.
        // Neste sistema de pousada, seu desdobramento padrão é 09.01.05.
        if (in_array(preg_replace('/\D+/', '', $lc116), ['901', '0901'], true)) {
            $lc116 = '09.01.05';
        }
        $cTribNac = $this->normalizarCTribNac($lc116, $itens[0]['c_trib_nac'] ?? null);

        $xml = $this->montarDpsXml($empresa, $unidade, $hospedagem, $cMun, $serie, $numero, $valor, $descricao, $cTribNac, $itens[0] ?? []);
        $assinado = $this->assinarDps($empresa, $xml);
        $assinado = $this->garantirXmlUtf8($assinado);
        $xmlEnvioPath = $this->xmlStorage->salvarEnvio($documento, $assinado);

        $documento->update([
            'status' => DocumentoFiscal::STATUS_PROCESSANDO,
            'serie' => $serie,
            'numero' => $numero,
            'xml' => $assinado,
            'xml_envio_path' => $xmlEnvioPath,
            'itens' => $itens,
            'valor_total' => $valor,
        ]);

        $arquivos = $this->certificadoA1->arquivosTemporariosPem($empresa);

        try {
            $base = ((int) $empresa->ambiente_nfe === Empresa::AMBIENTE_PRODUCAO)
                ? 'https://sefin.nfse.gov.br/SefinNacional/'
                : 'https://sefin.producaorestrita.nfse.gov.br/SefinNacional/';

            $payload = [
                // SEFIN E1229 exige XML com declaração encoding UTF-8 antes do GZip.
                'dpsXmlGZipB64' => base64_encode(gzencode($assinado, 9)),
            ];

            $response = Http::timeout(60)
                ->withOptions([
                    'cert' => $arquivos['cert'],
                    'ssl_key' => [$arquivos['key'], ''],
                    'verify' => true,
                ])
                ->acceptJson()
                ->post($base.'nfse', $payload);

            $json = $response->json() ?? [];
            $statusHttp = $response->status();

            if ($statusHttp >= 200 && $statusHttp < 300 && (isset($json['chaveAcesso']) || isset($json['idDps']))) {
                $chave = preg_replace('/\D+/', '', (string) ($json['chaveAcesso'] ?? $json['chave'] ?? '')) ?: null;
                $idDps = (string) ($json['idDps'] ?? '');
                $protocolo = (string) ($json['protocolo'] ?? $json['nProt'] ?? $idDps);
                $recibo = (string) ($json['recibo'] ?? $json['nRec'] ?? $idDps);
                $link = (string) ($json['link'] ?? $json['url'] ?? '');

                $xmlAutorizado = $this->xmlStorage->decodificarGzipBase64(
                    (string) ($json['nfseXmlGZipB64'] ?? $json['xmlGZipB64'] ?? '')
                );

                // Se a API não devolveu o XML no POST, tenta GET pela chave.
                if ($xmlAutorizado === null && $chave && strlen($chave) === 50) {
                    $xmlAutorizado = $this->consultarXmlAutorizado($base, $chave, $arquivos);
                }

                if ($xmlAutorizado === null) {
                    $xmlAutorizado = $assinado;
                }

                $xmlPath = $this->xmlStorage->salvarAutorizado($documento, $xmlAutorizado, $chave);

                $documento->update([
                    'status' => DocumentoFiscal::STATUS_AUTORIZADO,
                    'chave' => $chave,
                    'protocolo' => $protocolo !== '' ? $protocolo : null,
                    'recibo' => $recibo !== '' ? $recibo : null,
                    'xml_protocolado' => $xmlAutorizado,
                    'xml_path' => $xmlPath,
                    'link' => $link !== '' ? $link : null,
                    'autorizado_em' => now(),
                    'retorno' => $json,
                    'mensagem_erro' => null,
                ]);
                $empresa->update(['numero_ultima_nfse' => $numero]);

                return $documento->fresh();
            }

            $motivo = $this->extrairMensagemErro($json, $response->body());
            $documento->update([
                'status' => DocumentoFiscal::STATUS_REJEITADO,
                'mensagem_erro' => "HTTP {$statusHttp}: ".substr($motivo, 0, 800),
                'retorno' => $json ?: ['body' => $response->body()],
            ]);

            throw new IntegrationException($documento->mensagem_erro, servico: 'nfse', respostaBruta: $json);
        } catch (IntegrationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $documento->update([
                'status' => DocumentoFiscal::STATUS_ERRO,
                'mensagem_erro' => $e->getMessage(),
            ]);
            throw new IntegrationException('Falha na transmissão da NFS-e: '.$e->getMessage(), servico: 'nfse');
        } finally {
            ($arquivos['limpar'])();
        }
    }

    public function consultarLoteMunicipal(Empresa $empresa, Unidade $unidade, DocumentoFiscal $documento): DocumentoFiscal
    {
        return $this->municipal->consultarLoteDocumento($empresa, $unidade, $documento);
    }

    /**
     * @param  array{cert:string,key:string,limpar:callable}  $arquivos
     */
    protected function consultarXmlAutorizado(string $base, string $chave, array $arquivos): ?string
    {
        try {
            $response = Http::timeout(45)
                ->withOptions([
                    'cert' => $arquivos['cert'],
                    'ssl_key' => [$arquivos['key'], ''],
                    'verify' => true,
                ])
                ->acceptJson()
                ->get($base.'nfse/'.$chave);

            if (! $response->successful()) {
                return null;
            }

            $json = $response->json() ?? [];

            return $this->xmlStorage->decodificarGzipBase64(
                (string) ($json['nfseXmlGZipB64'] ?? $json['xmlGZipB64'] ?? '')
            );
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $itemRef
     */
    protected function montarDpsXml(
        Empresa $empresa,
        Unidade $unidade,
        Hospedagem $hospedagem,
        string $cMun,
        int $serie,
        int $numero,
        float $valor,
        string $descricao,
        string $cTribNac,
        array $itemRef,
    ): string {
        $tpAmb = (int) ($empresa->ambiente_nfe ?: 2);
        $cnpj = preg_replace('/\D+/', '', (string) ($unidade->cnpj ?: $empresa->cnpj));
        $im = preg_replace('/\D+/', '', (string) $empresa->im);
        // A SEFIN rejeita a DPS (E0008) se o relógio do emissor estiver até
        // poucos segundos à frente do processamento. Usamos o fuso oficial
        // de Brasília e uma pequena margem, derivando a competência do mesmo
        // instante para permanecer consistente inclusive na virada do dia.
        $momentoEmissao = now(self::FUSO_EMISSAO)->subMinutes(self::MARGEM_RELOGIO_MINUTOS);
        $dhEmi = $momentoEmissao->format('Y-m-d\TH:i:sP');
        $dCompet = $momentoEmissao->format('Y-m-d');

        // Id (45): DPS + cLocEmi(7) + tpInscr(1) + CNPJ(14) + série(5) + nDPS(15)
        // Ex.: DPS270430222195708400012610000000000000000223
        $idDps = 'DPS'
            .$cMun
            .'2'
            .str_pad((string) $cnpj, 14, '0', STR_PAD_LEFT)
            .str_pad((string) $serie, 5, '0', STR_PAD_LEFT)
            .str_pad((string) $numero, 15, '0', STR_PAD_LEFT);

        [$opSimpNac, $regApTribSN] = $this->regimeTributarioNfse($empresa);
        $vServ = number_format($valor, 2, '.', '');
        $xDesc = $this->escaparXml(
            mb_substr($descricao !== '' ? $descricao : 'Servicos de hospedagem', 0, 2000, 'UTF-8')
        );
        $cTribMun = preg_replace('/\D+/', '', (string) (
            $itemRef['c_trib_mun']
            ?? $itemRef['codigo_tributacao_municipal']
            ?? $itemRef['codigo_tributacao_municipio']
            ?? $empresa->codigo_tributacao_municipal_hospedagem
            ?? ''
        ));
        $cTribMunTag = strlen((string) $cTribMun) > 0 ? '<cTribMun>'.$cTribMun.'</cTribMun>' : '';
        $cNbs = preg_replace('/\D+/', '', (string) ($itemRef['nbs'] ?? '')) ?: null;
        $nbsTag = $cNbs ? '<cNBS>'.$cNbs.'</cNBS>' : '';
        $imTag = $im ? '<IM>'.$im.'</IM>' : '';

        $aliq = (float) ($itemRef['aliq_iss'] ?? $empresa->aliquota_iss_hospedagem ?? 0);
        // No exemplo autorizado a pAliq não entra quando o município calcula; só envia se informada.
        $pAliqTag = $aliq > 0
            ? '<pAliq>'.number_format($aliq, 2, '.', '').'</pAliq>'
            : '';

        $regApTag = $regApTribSN !== null
            ? '<regApTribSN>'.$regApTribSN.'</regApTribSN>'
            : '';

        $toma = $this->montarTomadorXml($hospedagem);

        // Evita literais de abertura/fechamento de bloco PHP na declaracao XML.
        $decl = '<'.'?xml version="1.0" encoding="UTF-8"?'.'>';

        return $decl
            .'<DPS xmlns="http://www.sped.fazenda.gov.br/nfse" versao="1.00">'
            .'<infDPS Id="'.$idDps.'">'
            .'<tpAmb>'.$tpAmb.'</tpAmb>'
            .'<dhEmi>'.$dhEmi.'</dhEmi>'
            .'<verAplic>1.00</verAplic>'
            .'<serie>'.$serie.'</serie>'
            .'<nDPS>'.$numero.'</nDPS>'
            .'<dCompet>'.$dCompet.'</dCompet>'
            .'<tpEmit>1</tpEmit>'
            .'<cLocEmi>'.$cMun.'</cLocEmi>'
            .'<prest>'
            .'<CNPJ>'.$cnpj.'</CNPJ>'
            .$imTag
            .'<regTrib>'
            .'<opSimpNac>'.$opSimpNac.'</opSimpNac>'
            .$regApTag
            .'<regEspTrib>0</regEspTrib>'
            .'</regTrib>'
            .'</prest>'
            .$toma
            .'<serv>'
            .'<locPrest><cLocPrestacao>'.$cMun.'</cLocPrestacao></locPrest>'
            .'<cServ>'
            .'<cTribNac>'.$cTribNac.'</cTribNac>'
            .$cTribMunTag
            .'<xDescServ>'.$xDesc.'</xDescServ>'
            .$nbsTag
            .'</cServ>'
            .'</serv>'
            .'<valores>'
            .'<vServPrest><vServ>'.$vServ.'</vServ></vServPrest>'
            .'<trib>'
            .'<tribMun>'
            .'<tribISSQN>1</tribISSQN>'
            .'<tpRetISSQN>1</tpRetISSQN>'
            .$pAliqTag
            .'</tribMun>'
            .'<totTrib>'
            .'<vTotTrib>'
            .'<vTotTribFed>0.00</vTotTribFed>'
            .'<vTotTribEst>0.00</vTotTribEst>'
            .'<vTotTribMun>0.00</vTotTribMun>'
            .'</vTotTrib>'
            .'</totTrib>'
            .'</trib>'
            .'</valores>'
            .'</infDPS>'
            .'</DPS>';
    }

    /**
     * Layout nacional: opSimpNac 1=Não optante, 2=MEI, 3=ME/EPP.
     *
     * @return array{0:string,1:?string}
     */
    protected function regimeTributarioNfse(Empresa $empresa): array
    {
        return match ($empresa->regime_tributario) {
            Empresa::REGIME_MEI => ['2', '1'],
            Empresa::REGIME_SIMPLES, Empresa::REGIME_SIMPLES_EXCESSO => ['3', '1'],
            default => ['1', null],
        };
    }

    /**
     * cTribNac N6 — ex.: hotel 09.01.01 → 090101; pousada 09.01.05 → 090105.
     */
    protected function normalizarCTribNac(string $lc116, mixed $explicito = null): string
    {
        $explicitoDigits = preg_replace('/\D+/', '', (string) $explicito);
        if (is_string($explicitoDigits) && strlen($explicitoDigits) === 6) {
            return $explicitoDigits;
        }

        $limpo = trim($lc116);
        $soDigitos = preg_replace('/\D+/', '', $limpo) ?: '';
        if (strlen($soDigitos) === 6) {
            return $soDigitos;
        }

        $partes = preg_split('/[.\-\/]/', $limpo) ?: [];
        $item = str_pad(preg_replace('/\D+/', '', (string) ($partes[0] ?? '0')) ?: '0', 2, '0', STR_PAD_LEFT);
        $sub = str_pad(preg_replace('/\D+/', '', (string) ($partes[1] ?? '0')) ?: '0', 2, '0', STR_PAD_LEFT);
        $des = str_pad(preg_replace('/\D+/', '', (string) ($partes[2] ?? '0')) ?: '0', 2, '0', STR_PAD_LEFT);

        return substr($item.$sub.$des, 0, 6);
    }

    protected function montarTomadorXml(Hospedagem $hospedagem): string
    {
        $cliente = $hospedagem->cliente;
        if (! $cliente) {
            return '';
        }

        $doc = preg_replace('/\D+/', '', (string) ($cliente->cpf ?? ''));
        $docTag = '';
        if (strlen((string) $doc) === 11) {
            $docTag = '<CPF>'.$doc.'</CPF>';
        } elseif (strlen((string) $doc) === 14) {
            $docTag = '<CNPJ>'.$doc.'</CNPJ>';
        } else {
            return '';
        }

        $xNome = $this->escaparXml(mb_substr((string) $cliente->nome, 0, 150, 'UTF-8'));
        $end = '';
        $cep = preg_replace('/\D+/', '', (string) ($cliente->cep ?? ''));
        $cMunToma = preg_replace('/\D+/', '', (string) ($cliente->codigo_municipio_ibge ?? ''));
        // Sem código IBGE do tomador, ainda assim enviamos logradouro se houver CEP válido + endereço.
        if (strlen((string) $cep) === 8 && filled($cliente->endereco)) {
            $endNac = '';
            if (strlen((string) $cMunToma) === 7) {
                $endNac .= '<cMun>'.$cMunToma.'</cMun>';
            }
            $endNac .= '<CEP>'.$cep.'</CEP>';
            $end = '<end>'
                .'<endNac>'.$endNac.'</endNac>'
                .'<xLgr>'.$this->escaparXml(mb_substr((string) $cliente->endereco, 0, 255, 'UTF-8')).'</xLgr>'
                .'<nro>'.$this->escaparXml(mb_substr((string) ($cliente->numero ?: 'S/N'), 0, 60, 'UTF-8')).'</nro>'
                .(filled($cliente->bairro) ? '<xBairro>'.$this->escaparXml(mb_substr((string) $cliente->bairro, 0, 60, 'UTF-8')).'</xBairro>' : '')
                .'</end>';
        }

        return '<toma>'.$docTag.'<xNome>'.$xNome.'</xNome>'.$end.'</toma>';
    }

    protected function escaparXml(string $valor): string
    {
        return htmlspecialchars($valor, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    /**
     * @param  array<string, mixed>  $json
     */
    protected function extrairMensagemErro(array $json, string $bodyFallback): string
    {
        if (isset($json['erros']) && is_array($json['erros']) && $json['erros'] !== []) {
            $msgs = [];
            foreach ($json['erros'] as $erro) {
                if (! is_array($erro)) {
                    continue;
                }
                $cod = (string) ($erro['Codigo'] ?? $erro['codigo'] ?? '');
                $desc = (string) ($erro['Descricao'] ?? $erro['descricao'] ?? $erro['mensagem'] ?? '');
                $msgs[] = trim(($cod !== '' ? $cod.': ' : '').$desc);
            }
            if ($msgs !== []) {
                return implode(' | ', $msgs);
            }
        }

        return (string) ($json['mensagem'] ?? $json['message'] ?? $bodyFallback);
    }

    protected function assinarDps(Empresa $empresa, string $xml): string
    {
        $cert = $this->certificadoA1->carregar($empresa);
        $xml = $this->garantirXmlUtf8($xml);

        try {
            // NFePHP Signer devolve o XML com LIBXML_NOXMLDECL (sem declaracao XML).
            $assinado = Signer::sign($cert['certificate'], $xml, 'infDPS', 'Id');

            return $this->garantirXmlUtf8($assinado);
        } catch (\Throwable $e) {
            throw new NegocioException('Falha ao assinar a DPS da NFS-e: '.$e->getMessage());
        }
    }

    /**
     * SEFIN Nacional (E1229): XML precisa declarar encoding UTF-8.
     * O Signer do NFePHP remove a declaração; recolocamos sem alterar o conteúdo assinado.
     */
    protected function garantirXmlUtf8(string $xml): string
    {
        // Remove BOM se existir.
        $xml = preg_replace('/^\xEF\xBB\xBF/', '', $xml) ?? $xml;

        if (! mb_check_encoding($xml, 'UTF-8')) {
            $xml = mb_convert_encoding($xml, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
        }

        $trimmed = ltrim($xml);
        $decl = '<'.'?xml version="1.0" encoding="UTF-8"?'.'>';

        if (! str_starts_with($trimmed, '<'.'?xml')) {
            return $decl.$trimmed;
        }

        // Normaliza declaração existente para UTF-8 (aceita standalone="no" do exemplo).
        return preg_replace(
            '/^<\?xml[^?]*\?>/i',
            $decl,
            $trimmed,
            1
        ) ?? $trimmed;
    }
}
