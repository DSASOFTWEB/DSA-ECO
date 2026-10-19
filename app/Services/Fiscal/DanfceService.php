<?php

namespace App\Services\Fiscal;

use App\Exceptions\NegocioException;
use App\Models\DocumentoFiscal;
use App\Support\FiscalTabelas;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Carbon;

/**
 * Lê o XML autorizado da NFC-e (modelo 65) e devolve tudo que o DANFE NFC-e
 * (cupom fiscal 80mm) precisa: emitente, itens, totais, pagamentos,
 * consumidor, chave, protocolo e o QR Code da SEFAZ (infNFeSupl, gravado
 * na assinatura). A mesma estrutura serve a impressão DOM e a ESC/POS.
 */
class DanfceService
{
    public function dados(DocumentoFiscal $documento): array
    {
        if ($documento->modelo !== DocumentoFiscal::MODELO_NFCE) {
            throw new NegocioException('Este documento não é uma NFC-e.');
        }

        if ($documento->status !== DocumentoFiscal::STATUS_AUTORIZADO) {
            throw new NegocioException('Só é possível imprimir o DANFE de NFC-e autorizada.');
        }

        $xml = $documento->xml_protocolado ?: $documento->xml;
        if (! filled($xml)) {
            throw new NegocioException('XML da NFC-e não encontrado.');
        }

        $dom = new DOMDocument;
        if (! @$dom->loadXML($xml)) {
            throw new NegocioException('XML da NFC-e inválido.');
        }

        $infNFe = $dom->getElementsByTagName('infNFe')->item(0);
        if (! $infNFe instanceof DOMElement) {
            throw new NegocioException('XML da NFC-e sem infNFe.');
        }

        $ide = $this->no($infNFe, 'ide');
        $emit = $this->no($infNFe, 'emit');
        $ender = $emit ? $this->no($emit, 'enderEmit') : null;
        $dest = $this->no($infNFe, 'dest');
        $tot = $dom->getElementsByTagName('ICMSTot')->item(0);
        $prot = $dom->getElementsByTagName('infProt')->item(0);

        $itens = [];
        foreach ($infNFe->getElementsByTagName('det') as $det) {
            $prod = $this->no($det, 'prod');
            $itens[] = [
                'codigo' => $this->txt($prod, 'cProd'),
                'descricao' => $this->txt($prod, 'xProd'),
                'quantidade' => (float) $this->txt($prod, 'qCom'),
                'unidade' => $this->txt($prod, 'uCom'),
                'valor_unitario' => (float) $this->txt($prod, 'vUnCom'),
                'valor_total' => (float) $this->txt($prod, 'vProd'),
                'desconto' => (float) $this->txt($prod, 'vDesc'),
            ];
        }

        $pagamentos = [];
        foreach ($dom->getElementsByTagName('detPag') as $detPag) {
            $codigo = $this->txt($detPag, 'tPag');
            $pagamentos[] = [
                // "17 — Pagamento Instantâneo (PIX)" → "Pagamento Instantâneo (PIX)"
                'forma' => preg_replace('/^\d+\s*\S\s*/u', '', FiscalTabelas::formasPagamento()[$codigo] ?? 'Outros'),
                'valor' => (float) $this->txt($detPag, 'vPag'),
            ];
        }

        $chave = substr((string) $infNFe->getAttribute('Id'), 3) ?: (string) $documento->chave;
        $documentoDest = $dest ? ($this->txt($dest, 'CPF') ?: $this->txt($dest, 'CNPJ')) : '';

        return [
            'emitente' => [
                'razao' => $this->txt($emit, 'xNome'),
                'fantasia' => $this->txt($emit, 'xFant'),
                'cnpj' => $this->formatarCnpj($this->txt($emit, 'CNPJ')),
                'ie' => $this->txt($emit, 'IE'),
                'endereco' => trim($this->txt($ender, 'xLgr').', '.$this->txt($ender, 'nro').' - '.$this->txt($ender, 'xBairro'), ' ,-'),
                'cidade' => trim($this->txt($ender, 'xMun').' - '.$this->txt($ender, 'UF'), ' -'),
            ],
            'itens' => $itens,
            'quantidade_itens' => count($itens),
            'total_produtos' => (float) $this->txt($tot, 'vProd'),
            'desconto' => (float) $this->txt($tot, 'vDesc'),
            'total' => (float) $this->txt($tot, 'vNF'),
            'pagamentos' => $pagamentos,
            'troco' => (float) $this->txt($dom->getElementsByTagName('pag')->item(0), 'vTroco'),
            'consumidor' => $documentoDest !== ''
                ? ['documento' => strlen($documentoDest) === 11 ? $this->formatarCpf($documentoDest) : $this->formatarCnpj($documentoDest), 'nome' => $this->txt($dest, 'xNome')]
                : null,
            'numero' => $this->txt($ide, 'nNF'),
            'serie' => $this->txt($ide, 'serie'),
            'emissao' => ($dh = $this->txt($ide, 'dhEmi')) !== '' ? Carbon::parse($dh)->timezone('America/Sao_Paulo') : null,
            'homologacao' => $this->txt($ide, 'tpAmb') === '2',
            'chave' => $chave,
            'chave_formatada' => trim(chunk_split($chave, 4, ' ')),
            'protocolo' => $prot ? $this->txt($prot, 'nProt') : (string) $documento->protocolo,
            'autorizacao' => $prot && ($dh = $this->txt($prot, 'dhRecbto')) !== '' ? Carbon::parse($dh)->timezone('America/Sao_Paulo') : $documento->autorizado_em,
            'qr_code' => trim((string) ($dom->getElementsByTagName('qrCode')->item(0)?->nodeValue ?? '')),
            'url_consulta' => trim((string) ($dom->getElementsByTagName('urlChave')->item(0)?->nodeValue ?? '')),
            'info_complementar' => trim((string) ($dom->getElementsByTagName('infCpl')->item(0)?->nodeValue ?? '')),
        ];
    }

    protected function no(?DOMElement $pai, string $tag): ?DOMElement
    {
        $no = $pai?->getElementsByTagName($tag)->item(0);

        return $no instanceof DOMElement ? $no : null;
    }

    protected function txt(?\DOMNode $pai, string $tag): string
    {
        if (! $pai instanceof DOMElement && ! $pai instanceof DOMDocument) {
            return '';
        }

        return trim((string) ($pai->getElementsByTagName($tag)->item(0)?->nodeValue ?? ''));
    }

    protected function formatarCnpj(string $cnpj): string
    {
        $d = preg_replace('/\D+/', '', $cnpj);

        return strlen($d) === 14 ? vsprintf('%s.%s.%s/%s-%s', [substr($d, 0, 2), substr($d, 2, 3), substr($d, 5, 3), substr($d, 8, 4), substr($d, 12)]) : $cnpj;
    }

    protected function formatarCpf(string $cpf): string
    {
        $d = preg_replace('/\D+/', '', $cpf);

        return strlen($d) === 11 ? vsprintf('%s.%s.%s-%s', [substr($d, 0, 3), substr($d, 3, 3), substr($d, 6, 3), substr($d, 9)]) : $cpf;
    }
}
