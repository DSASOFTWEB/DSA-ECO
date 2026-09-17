<?php

namespace App\Services\Fiscal\NfseMunicipal\Xml;

use App\Models\Empresa;
use App\Models\Hospedagem;
use App\Models\Unidade;
use App\Services\Fiscal\NfseMunicipal\MunicipioNfseCatalog;

/**
 * Monta EnviarLoteRpsEnvio / ConsultarLoteRpsEnvio no layout ABRASF 2.04 (GISS).
 */
class AbrasfV2RpsBuilder
{
    private const NS_TIPOS = 'http://www.giss.com.br/tipos-v2_04.xsd';

    private const NS_ENVIO = 'http://www.giss.com.br/enviar-lote-rps-envio-v2_04.xsd';

    private const NS_CONSULTA = 'http://www.giss.com.br/consultar-lote-rps-envio-v2_04.xsd';

    private const NS_CABEC = 'http://www.giss.com.br/cabecalho-v2_04.xsd';

    public function __construct(protected MunicipioNfseCatalog $catalog) {}

    public function cabecalho(): string
    {
        return '<cabecalho xmlns="'.self::NS_CABEC.'" versao="2.04">'
            .'<versaoDados>2.04</versaoDados>'
            .'</cabecalho>';
    }

    /**
     * @param  array{ibge:string,params:list<string>}  $municipio
     * @param  list<array<string, mixed>>  $itens
     * @return array{rps:string,id_inf:string}
     */
    public function montarRps(
        Empresa $empresa,
        Unidade $unidade,
        Hospedagem $hospedagem,
        array $municipio,
        array $itens,
        int $serie,
        int $numeroRps,
    ): array {
        $cMun = $municipio['ibge'];
        $cnpj = preg_replace('/\D+/', '', (string) ($unidade->cnpj ?: $empresa->cnpj));
        $im = preg_replace('/\D+/', '', (string) $empresa->im);
        $dividir100 = $this->catalog->temParam($municipio, 'Dividir100');

        $valor = round(collect($itens)->sum(fn ($i) => ((float) $i['quantidade']) * ((float) $i['valor_unitario'])), 2);
        $descricao = collect($itens)->map(fn ($i) => (string) ($i['descricao'] ?? ''))->filter()->implode('; ');
        if ($descricao === '') {
            $descricao = 'Servicos de hospedagem';
        }

        $itemLista = $this->itemListaServico((string) ($itens[0]['codigo_servico_lc116'] ?? $empresa->codigo_servico_hospedagem_lc116 ?: '09.01'));
        $codTribMun = preg_replace('/\D+/', '', (string) (
            $itens[0]['codigo_tributacao_municipal']
            ?? $empresa->codigo_tributacao_municipal_hospedagem
            ?? ''
        ));
        $aliq = (float) ($itens[0]['aliq_iss'] ?? $empresa->aliquota_iss_hospedagem ?? 0);
        if ($dividir100 && $aliq > 0) {
            // ACBr Params=Dividir100: alíquota percentual (ex. 5) vira 0.05 no XML.
            $aliqXml = $aliq / 100;
        } else {
            $aliqXml = $aliq;
        }

        $dh = now('America/Sao_Paulo')->subMinutes(2);
        $dataEmissao = $dh->format('Y-m-d');
        $competencia = $dh->format('Y-m-d');
        $idInf = 'rps'.$numeroRps;

        $optanteSn = in_array($empresa->regime_tributario, [Empresa::REGIME_SIMPLES, Empresa::REGIME_SIMPLES_EXCESSO, Empresa::REGIME_MEI], true) ? '1' : '2';

        $valores = '<Valores>'
            .'<ValorServicos>'.$this->money($valor).'</ValorServicos>'
            .($aliqXml > 0 ? '<Aliquota>'.$this->money($aliqXml, 4).'</Aliquota>' : '')
            .'</Valores>';

        $servico = '<Servico>'
            .$valores
            .'<IssRetido>2</IssRetido>'
            .'<ItemListaServico>'.$this->esc($itemLista).'</ItemListaServico>'
            .($codTribMun !== '' ? '<CodigoTributacaoMunicipio>'.$this->esc($codTribMun).'</CodigoTributacaoMunicipio>' : '')
            .'<Discriminacao>'.$this->esc(mb_substr($descricao, 0, 2000, 'UTF-8')).'</Discriminacao>'
            .'<CodigoMunicipio>'.$cMun.'</CodigoMunicipio>'
            .'<ExigibilidadeISS>1</ExigibilidadeISS>'
            .'<MunicipioIncidencia>'.$cMun.'</MunicipioIncidencia>'
            .'</Servico>';

        $prestador = '<Prestador>'
            .'<CpfCnpj><Cnpj>'.$cnpj.'</Cnpj></CpfCnpj>'
            .($im !== '' ? '<InscricaoMunicipal>'.$im.'</InscricaoMunicipal>' : '')
            .'</Prestador>';

        $tomador = $this->montarTomador($hospedagem, $empresa, $unidade, $cMun);

        $inf = '<InfDeclaracaoPrestacaoServico Id="'.$idInf.'" xmlns="'.self::NS_TIPOS.'">'
            .'<Rps>'
            .'<IdentificacaoRps>'
            .'<Numero>'.$numeroRps.'</Numero>'
            .'<Serie>'.$this->esc((string) $serie).'</Serie>'
            .'<Tipo>1</Tipo>'
            .'</IdentificacaoRps>'
            .'<DataEmissao>'.$dataEmissao.'</DataEmissao>'
            .'<Status>1</Status>'
            .'</Rps>'
            .'<Competencia>'.$competencia.'</Competencia>'
            .$servico
            .$prestador
            .$tomador
            .'<OptanteSimplesNacional>'.$optanteSn.'</OptanteSimplesNacional>'
            .'<IncentivoFiscal>2</IncentivoFiscal>'
            .'</InfDeclaracaoPrestacaoServico>';

        $rps = '<Rps xmlns="'.self::NS_TIPOS.'">'.$inf.'</Rps>';

        return ['rps' => $rps, 'id_inf' => $idInf];
    }

    /**
     * @param  array{ibge:string}  $municipio
     */
    public function montarLoteEnvio(
        Empresa $empresa,
        Unidade $unidade,
        array $municipio,
        string $numeroLote,
        string $rpsAssinadoInner,
    ): string {
        $cnpj = preg_replace('/\D+/', '', (string) ($unidade->cnpj ?: $empresa->cnpj));
        $im = preg_replace('/\D+/', '', (string) $empresa->im);
        $idLote = 'lote'.$numeroLote;

        $prestador = '<Prestador xmlns="'.self::NS_TIPOS.'">'
            .'<CpfCnpj><Cnpj>'.$cnpj.'</Cnpj></CpfCnpj>'
            .($im !== '' ? '<InscricaoMunicipal>'.$im.'</InscricaoMunicipal>' : '')
            .'</Prestador>';

        return '<EnviarLoteRpsEnvio xmlns="'.self::NS_ENVIO.'">'
            .'<LoteRps Id="'.$idLote.'" versao="2.04">'
            .'<NumeroLote xmlns="'.self::NS_TIPOS.'">'.$this->esc($numeroLote).'</NumeroLote>'
            .$prestador
            .'<QuantidadeRps xmlns="'.self::NS_TIPOS.'">1</QuantidadeRps>'
            .'<ListaRps xmlns="'.self::NS_TIPOS.'">'.$rpsAssinadoInner.'</ListaRps>'
            .'</LoteRps>'
            .'</EnviarLoteRpsEnvio>';
    }

    public function montarConsultaLote(Empresa $empresa, Unidade $unidade, string $protocolo): string
    {
        $cnpj = preg_replace('/\D+/', '', (string) ($unidade->cnpj ?: $empresa->cnpj));
        $im = preg_replace('/\D+/', '', (string) $empresa->im);

        return '<ConsultarLoteRpsEnvio xmlns="'.self::NS_CONSULTA.'">'
            .'<Prestador>'
            .'<CpfCnpj xmlns="'.self::NS_TIPOS.'"><Cnpj>'.$cnpj.'</Cnpj></CpfCnpj>'
            .($im !== '' ? '<InscricaoMunicipal xmlns="'.self::NS_TIPOS.'">'.$im.'</InscricaoMunicipal>' : '')
            .'</Prestador>'
            .'<Protocolo>'.$this->esc($protocolo).'</Protocolo>'
            .'</ConsultarLoteRpsEnvio>';
    }

    /**
     * Extrai o nó Rps (já assinado) para embutir em ListaRps.
     */
    public function extrairRpsParaLote(string $rpsAssinado): string
    {
        if (preg_match('/<Rps\b[^>]*>.*<\/Rps>/is', $rpsAssinado, $m)) {
            return $m[0];
        }

        return $rpsAssinado;
    }

    protected function montarTomador(Hospedagem $hospedagem, Empresa $empresa, Unidade $unidade, string $cMunEmpresa): string
    {
        $cliente = $hospedagem->cliente;
        if (! $cliente) {
            return '';
        }

        $doc = preg_replace('/\D+/', '', (string) ($cliente->cpf ?? ''));
        if (strlen((string) $doc) === 11) {
            $docTag = '<Cpf>'.$doc.'</Cpf>';
        } elseif (strlen((string) $doc) === 14) {
            $docTag = '<Cnpj>'.$doc.'</Cnpj>';
        } else {
            return '';
        }

        // tcEndereco GISS exige: Endereco, Numero, Bairro, CodigoMunicipio, Uf, Cep.
        // Sem todos os campos, omite o endereço (opcional em tcDadosTomador).
        $end = '';
        $logradouro = trim((string) ($cliente->endereco ?? ''));
        $numero = trim((string) ($cliente->numero ?: 'S/N'));
        $bairro = trim((string) ($cliente->bairro ?? ''));
        $cep = preg_replace('/\D+/', '', (string) ($cliente->cep ?? ''));
        $uf = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string) ($cliente->uf ?? $unidade->uf ?? '')), 0, 2));
        $cMun = preg_replace('/\D+/', '', (string) ($cliente->codigo_municipio_ibge ?? ''));
        if (strlen((string) $cMun) !== 7) {
            $cMun = $cMunEmpresa;
        }

        if (
            $logradouro !== ''
            && $numero !== ''
            && $bairro !== ''
            && strlen((string) $cep) === 8
            && strlen((string) $cMun) === 7
            && strlen($uf) === 2
        ) {
            $end = '<Endereco>'
                .'<Endereco>'.$this->esc(mb_substr($logradouro, 0, 125, 'UTF-8')).'</Endereco>'
                .'<Numero>'.$this->esc(mb_substr($numero, 0, 10, 'UTF-8')).'</Numero>'
                .(filled($cliente->complemento) ? '<Complemento>'.$this->esc(mb_substr((string) $cliente->complemento, 0, 60, 'UTF-8')).'</Complemento>' : '')
                .'<Bairro>'.$this->esc(mb_substr($bairro, 0, 60, 'UTF-8')).'</Bairro>'
                .'<CodigoMunicipio>'.$cMun.'</CodigoMunicipio>'
                .'<Uf>'.$uf.'</Uf>'
                .'<Cep>'.$cep.'</Cep>'
                .'</Endereco>';
        }

        return '<TomadorServico>'
            .'<IdentificacaoTomador><CpfCnpj>'.$docTag.'</CpfCnpj></IdentificacaoTomador>'
            .'<RazaoSocial>'.$this->esc(mb_substr((string) $cliente->nome, 0, 150, 'UTF-8')).'</RazaoSocial>'
            .$end
            .'</TomadorServico>';
    }

    /**
     * LC 116 → ItemListaServico ABRASF (ex.: 09.01.05 → 901, ou 09.01).
     */
    protected function itemListaServico(string $lc116): string
    {
        $limpo = trim($lc116);
        $partes = preg_split('/[.\-\/]/', $limpo) ?: [];
        $item = str_pad(preg_replace('/\D+/', '', (string) ($partes[0] ?? '0')) ?: '0', 2, '0', STR_PAD_LEFT);
        $sub = str_pad(preg_replace('/\D+/', '', (string) ($partes[1] ?? '0')) ?: '0', 2, '0', STR_PAD_LEFT);

        // Formato clássico ABRASF: "9.01" ou "901"
        return ltrim($item, '0').'.'.$sub;
    }

    protected function money(float $valor, int $casas = 2): string
    {
        return number_format($valor, $casas, '.', '');
    }

    protected function esc(string $valor): string
    {
        return htmlspecialchars($valor, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }
}
