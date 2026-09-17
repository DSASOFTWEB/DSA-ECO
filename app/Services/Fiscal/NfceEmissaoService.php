<?php

namespace App\Services\Fiscal;

use App\Exceptions\IntegrationException;
use App\Exceptions\NegocioException;
use App\Models\DocumentoFiscal;
use App\Models\Empresa;
use App\Models\Hospedagem;
use App\Models\Unidade;
use NFePHP\NFe\Common\Standardize;
use NFePHP\NFe\Complements;
use NFePHP\NFe\Make;
use NFePHP\NFe\Tools;
use stdClass;

/**
 * Monta e transmite NFC-e (modelo 65) dos consumos de produto da hospedagem.
 */
class NfceEmissaoService
{
    public function __construct(
        protected CertificadoA1Service $certificadoA1,
        protected FiscalXmlStorageService $xmlStorage,
    ) {}

    /**
     * @param  list<array{descricao:string, quantidade:float|int, valor_unitario:float, ncm?:?string, cfop?:?string, cst_icms?:?string, csosn?:?string, origem?:?int, unidade?:string, ean?:?string}>  $itens
     */
    public function emitir(Empresa $empresa, Unidade $unidade, Hospedagem $hospedagem, DocumentoFiscal $documento, array $itens): DocumentoFiscal
    {
        if ($itens === []) {
            throw new NegocioException('Não há consumos de produto para emitir NFC-e nesta hospedagem.');
        }

        if (! filled($empresa->csc) || ! filled($empresa->csc_id)) {
            throw new NegocioException('Configure CSC e CSC ID da NFC-e em Dados da empresa.');
        }

        $cMun = preg_replace('/\D+/', '', (string) $empresa->codigo_municipio_ibge);
        if (strlen((string) $cMun) !== 7) {
            throw new NegocioException('Informe o código IBGE do município (7 dígitos) em Dados da empresa.');
        }

        $cert = $this->certificadoA1->carregar($empresa);
        $config = $this->configTools($empresa, $unidade);
        $tools = new Tools(json_encode($config), $cert['certificate']);
        $tools->model(65);

        $numero = $this->proximoNumero($empresa);
        $serie = (int) ($empresa->numero_serie_nfce ?: 1);

        $xml = $this->montarXml($empresa, $unidade, $hospedagem, $itens, $serie, $numero, (string) $cMun);
        $assinado = $tools->signNFe($xml);
        $xmlEnvioPath = $this->xmlStorage->salvarEnvio($documento, $assinado);

        $documento->update([
            'status' => DocumentoFiscal::STATUS_PROCESSANDO,
            'serie' => $serie,
            'numero' => $numero,
            'xml' => $assinado,
            'xml_envio_path' => $xmlEnvioPath,
            'itens' => $itens,
            'valor_total' => collect($itens)->sum(fn ($i) => round(((float) $i['quantidade']) * ((float) $i['valor_unitario']), 2)),
        ]);

        try {
            $idLote = str_pad((string) random_int(1, 999999999999999), 15, '0', STR_PAD_LEFT);
            $resposta = $tools->sefazEnviaLote([$assinado], $idLote, 1);
            $std = (new Standardize($resposta))->toStd();

            $cStat = (string) ($std->cStat ?? $std->protNFe->infProt->cStat ?? '');
            $protocolo = (string) ($std->protNFe->infProt->nProt ?? $std->nProt ?? '');
            $recibo = (string) ($std->infRec->nRec ?? $std->nRec ?? '');
            $chave = preg_replace('/\D+/', '', (string) ($std->protNFe->infProt->chNFe ?? '')) ?: null;

            if (! in_array($cStat, ['100', '150'], true)) {
                $motivo = (string) ($std->xMotivo ?? $std->protNFe->infProt->xMotivo ?? 'Rejeição SEFAZ');
                $documento->update([
                    'status' => DocumentoFiscal::STATUS_REJEITADO,
                    'recibo' => $recibo !== '' ? $recibo : null,
                    'mensagem_erro' => "cStat {$cStat}: {$motivo}",
                    'retorno' => json_decode(json_encode($std), true),
                ]);

                throw new IntegrationException($documento->mensagem_erro, servico: 'nfce', respostaBruta: ['cStat' => $cStat]);
            }

            try {
                $xmlProt = Complements::toAuthorize($assinado, $resposta);
            } catch (\Throwable) {
                $xmlProt = $assinado;
            }

            $xmlPath = $this->xmlStorage->salvarAutorizado($documento, $xmlProt, $chave);

            $documento->update([
                'status' => DocumentoFiscal::STATUS_AUTORIZADO,
                'chave' => $chave !== null && $chave !== '' ? $chave : $documento->chave,
                'protocolo' => $protocolo !== '' ? $protocolo : null,
                'recibo' => $recibo !== '' ? $recibo : null,
                'xml_protocolado' => $xmlProt,
                'xml_path' => $xmlPath,
                'autorizado_em' => now(),
                'mensagem_erro' => null,
                'retorno' => json_decode(json_encode($std), true),
            ]);

            $this->atualizarNumeracao($empresa, $numero);

            return $documento->fresh();
        } catch (IntegrationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $documento->update([
                'status' => DocumentoFiscal::STATUS_ERRO,
                'mensagem_erro' => $e->getMessage(),
            ]);
            throw new IntegrationException('Falha na transmissão da NFC-e: '.$e->getMessage(), servico: 'nfce');
        }
    }

    protected function configTools(Empresa $empresa, Unidade $unidade): array
    {
        $cnpj = preg_replace('/\D+/', '', (string) ($unidade->cnpj ?: $empresa->cnpj));

        return [
            'atualizacao' => date('Y-m-d H:i:s'),
            'tpAmb' => (int) ($empresa->ambiente_nfe ?: 2),
            'razaosocial' => (string) ($empresa->razao_social ?: $empresa->nome),
            'siglaUF' => strtoupper((string) ($unidade->uf ?: 'SP')),
            'cnpj' => $cnpj,
            'schemes' => 'PL_009_V4',
            'versao' => '4.00',
            'tokenIBPT' => (string) ($empresa->token_ibpt ?: config('parque.token_ibpt', '')),
            'CSC' => (string) $empresa->csc,
            'CSCid' => (string) $empresa->csc_id,
        ];
    }

    protected function proximoNumero(Empresa $empresa): int
    {
        $campo = ((int) $empresa->ambiente_nfe === Empresa::AMBIENTE_PRODUCAO)
            ? 'numero_ultima_nfce_producao'
            : 'numero_ultima_nfce_homologacao';

        return ((int) $empresa->{$campo}) + 1;
    }

    protected function atualizarNumeracao(Empresa $empresa, int $numero): void
    {
        $campo = ((int) $empresa->ambiente_nfe === Empresa::AMBIENTE_PRODUCAO)
            ? 'numero_ultima_nfce_producao'
            : 'numero_ultima_nfce_homologacao';

        $empresa->update([$campo => $numero]);
    }

    /**
     * @param  list<array<string, mixed>>  $itens
     */
    protected function montarXml(
        Empresa $empresa,
        Unidade $unidade,
        Hospedagem $hospedagem,
        array $itens,
        int $serie,
        int $numero,
        string $cMun,
    ): string {
        $make = new Make();
        $cnpj = preg_replace('/\D+/', '', (string) ($unidade->cnpj ?: $empresa->cnpj));
        $dhEmi = now()->format('Y-m-d\TH:i:sP');
        $tpAmb = (int) ($empresa->ambiente_nfe ?: 2);
        $cNF = str_pad((string) random_int(1, 99999999), 8, '0', STR_PAD_LEFT);

        $std = new stdClass;
        $std->versao = '4.00';
        $make->taginfNFe($std);

        $ide = new stdClass;
        $ide->cUF = $this->codigoUf(strtoupper((string) ($unidade->uf ?: 'SP')));
        $ide->cNF = $cNF;
        $ide->natOp = 'VENDA';
        $ide->mod = 65;
        $ide->serie = $serie;
        $ide->nNF = $numero;
        $ide->dhEmi = $dhEmi;
        $ide->tpNF = 1;
        $ide->idDest = 1;
        $ide->cMunFG = $cMun;
        $ide->tpImp = 4;
        $ide->tpEmis = 1;
        $ide->cDV = '0';
        $ide->tpAmb = $tpAmb;
        $ide->finNFe = 1;
        $ide->indFinal = 1;
        $ide->indPres = 1;
        $ide->procEmi = 0;
        $ide->verProc = 'ParqueAquaticoSaaS 1.0';
        $make->tagide($ide);

        $emit = new stdClass;
        $emit->xNome = substr((string) ($empresa->razao_social ?: $empresa->nome), 0, 60);
        $emit->xFant = substr((string) $empresa->nome, 0, 60);
        $emit->IE = preg_replace('/\D+/', '', (string) $empresa->ie) ?: null;
        $emit->CRT = $empresa->crt();
        $emit->CNPJ = $cnpj;
        $make->tagemit($emit);

        $ender = new stdClass;
        $ender->xLgr = substr((string) ($unidade->endereco ?: 'NAO INFORMADO'), 0, 60);
        $ender->nro = substr((string) ($unidade->numero ?: 'S/N'), 0, 60);
        $ender->xBairro = substr((string) ($unidade->bairro ?: 'CENTRO'), 0, 60);
        $ender->cMun = $cMun;
        $ender->xMun = substr((string) ($unidade->cidade ?: 'MUNICIPIO'), 0, 60);
        $ender->UF = strtoupper((string) ($unidade->uf ?: 'SP'));
        $ender->CEP = preg_replace('/\D+/', '', (string) ($unidade->cep ?: '00000000'));
        $ender->cPais = '1058';
        $ender->xPais = 'BRASIL';
        $make->tagenderEmit($ender);

        $cliente = $hospedagem->cliente;
        $cpf = preg_replace('/\D+/', '', (string) ($cliente?->cpf ?? ''));
        if (strlen((string) $cpf) === 11) {
            $dest = new stdClass;
            $dest->xNome = substr((string) $cliente->nome, 0, 60);
            $dest->CPF = $cpf;
            $dest->indIEDest = 9;
            $make->tagdest($dest);
        }

        $vProd = 0.0;
        foreach ($itens as $i => $item) {
            $nItem = $i + 1;
            $qCom = (float) $item['quantidade'];
            $vUn = round((float) $item['valor_unitario'], 2);
            $vItem = round($qCom * $vUn, 2);
            $vProd += $vItem;

            $prod = new stdClass;
            $prod->item = $nItem;
            $prod->cProd = (string) ($item['codigo'] ?? $nItem);
            $prod->cEAN = filled($item['ean'] ?? null) ? $item['ean'] : 'SEM GTIN';
            $prod->xProd = substr((string) $item['descricao'], 0, 120);
            $prod->NCM = (string) ($item['ncm'] ?? '00000000');
            $prod->CFOP = (string) ($item['cfop'] ?? '5102');
            $prod->uCom = (string) ($item['unidade'] ?? 'UN');
            $prod->qCom = number_format($qCom, 4, '.', '');
            $prod->vUnCom = number_format($vUn, 2, '.', '');
            $prod->vProd = number_format($vItem, 2, '.', '');
            $prod->cEANTrib = $prod->cEAN;
            $prod->uTrib = $prod->uCom;
            $prod->qTrib = $prod->qCom;
            $prod->vUnTrib = $prod->vUnCom;
            $prod->indTot = 1;
            $make->tagprod($prod);

            $imposto = new stdClass;
            $imposto->item = $nItem;
            $make->tagimposto($imposto);

            if ($empresa->crt() === 3) {
                $icms = new stdClass;
                $icms->item = $nItem;
                $icms->orig = (int) ($item['origem'] ?? 0);
                $icms->CST = (string) ($item['cst_icms'] ?? '00');
                $icms->modBC = 3;
                $icms->vBC = number_format($vItem, 2, '.', '');
                $icms->pICMS = number_format((float) ($item['aliq_icms'] ?? 0), 2, '.', '');
                $icms->vICMS = '0.00';
                $make->tagICMS($icms);
            } else {
                $icms = new stdClass;
                $icms->item = $nItem;
                $icms->orig = (int) ($item['origem'] ?? 0);
                $icms->CSOSN = (string) ($item['csosn'] ?? '102');
                $make->tagICMSSN($icms);
            }

            $pis = new stdClass;
            $pis->item = $nItem;
            $pis->CST = (string) ($item['cst_pis'] ?? '49');
            $pis->vBC = '0.00';
            $pis->pPIS = '0.00';
            $pis->vPIS = '0.00';
            $make->tagPIS($pis);

            $cofins = new stdClass;
            $cofins->item = $nItem;
            $cofins->CST = (string) ($item['cst_cofins'] ?? '49');
            $cofins->vBC = '0.00';
            $cofins->pCOFINS = '0.00';
            $cofins->vCOFINS = '0.00';
            $make->tagCOFINS($cofins);
        }

        $total = new stdClass;
        $total->vBC = '0.00';
        $total->vICMS = '0.00';
        $total->vICMSDeson = '0.00';
        $total->vFCP = '0.00';
        $total->vBCST = '0.00';
        $total->vST = '0.00';
        $total->vFCPST = '0.00';
        $total->vFCPSTRet = '0.00';
        $total->vProd = number_format($vProd, 2, '.', '');
        $total->vFrete = '0.00';
        $total->vSeg = '0.00';
        $total->vDesc = '0.00';
        $total->vII = '0.00';
        $total->vIPI = '0.00';
        $total->vIPIDevol = '0.00';
        $total->vPIS = '0.00';
        $total->vCOFINS = '0.00';
        $total->vOutro = '0.00';
        $total->vNF = number_format($vProd, 2, '.', '');
        $make->tagICMSTot($total);

        $transp = new stdClass;
        $transp->modFrete = 9;
        $make->tagtransp($transp);

        $pag = new stdClass;
        $pag->vTroco = null;
        $make->tagpag($pag);

        $detPag = new stdClass;
        $detPag->tPag = $this->mapaFormaPagamento($hospedagem->forma_pagamento);
        $detPag->vPag = number_format($vProd, 2, '.', '');
        $make->tagdetPag($detPag);

        if (filled($empresa->observacao_padrao_nfce)) {
            $inf = new stdClass;
            $inf->infCpl = (string) $empresa->observacao_padrao_nfce;
            $make->taginfAdic($inf);
        }

        $erros = $make->getErrors();
        if ($erros) {
            throw new NegocioException('XML NFC-e inválido: '.implode('; ', $erros));
        }

        return $make->getXML();
    }

    protected function mapaFormaPagamento(?string $forma): string
    {
        return \App\Support\Financeiro::codigoFiscalFormaPagamento($forma);
    }

    protected function codigoUf(string $uf): int
    {
        return match ($uf) {
            'RO' => 11, 'AC' => 12, 'AM' => 13, 'RR' => 14, 'PA' => 15, 'AP' => 16, 'TO' => 17,
            'MA' => 21, 'PI' => 22, 'CE' => 23, 'RN' => 24, 'PB' => 25, 'PE' => 26, 'AL' => 27, 'SE' => 28, 'BA' => 29,
            'MG' => 31, 'ES' => 32, 'RJ' => 33, 'SP' => 35,
            'PR' => 41, 'SC' => 42, 'RS' => 43,
            'MS' => 50, 'MT' => 51, 'GO' => 52, 'DF' => 53,
            default => 35,
        };
    }
}
