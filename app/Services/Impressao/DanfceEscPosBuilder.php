<?php

namespace App\Services\Impressao;

/**
 * DANFE NFC-e (cupom fiscal) em ESC/POS para bobina 80mm, na ordem do
 * leiaute do Manual do DANFE NFC-e: emitente, itens, totais, pagamento,
 * consulta pela chave, consumidor, identificação/protocolo e QR Code da
 * SEFAZ (desenhado pela própria impressora).
 *
 * Recebe os dados já lidos do XML por App\Services\Fiscal\DanfceService.
 */
class DanfceEscPosBuilder extends EscPosCupomBuilder
{
    public function montarDanfce(array $d): string
    {
        $out = $this->cmdInit();
        $out .= $this->cmdAlign(1);
        $out .= $this->cmdBold(true);
        foreach ($this->quebrar($d['emitente']['razao']) as $linha) {
            $out .= $this->linha($linha);
        }
        $out .= $this->cmdBold(false);
        $out .= $this->linha('CNPJ: '.$d['emitente']['cnpj'].($d['emitente']['ie'] ? '  IE: '.$d['emitente']['ie'] : ''));
        foreach ($this->quebrar($d['emitente']['endereco'].' '.$d['emitente']['cidade']) as $linha) {
            $out .= $this->linha($linha);
        }
        $out .= $this->separadorFino();
        $out .= $this->linha('Documento Auxiliar da Nota Fiscal');
        $out .= $this->linha('de Consumidor Eletronica');
        $out .= $this->separadorFino();

        if ($d['homologacao']) {
            $out .= $this->cmdBold(true);
            $out .= $this->linha('EMITIDA EM AMBIENTE DE HOMOLOGACAO');
            $out .= $this->linha('SEM VALOR FISCAL');
            $out .= $this->cmdBold(false);
            $out .= $this->separadorFino();
        }

        // Itens
        $out .= $this->cmdAlign(0);
        $out .= $this->linha('CODIGO DESCRICAO');
        $out .= $this->linhaPar('QTD UN x VL UNIT', 'VL TOTAL');
        $out .= $this->separadorFino();
        foreach ($d['itens'] as $item) {
            foreach ($this->quebrar($item['codigo'].' '.$item['descricao']) as $linha) {
                $out .= $this->linha($linha);
            }
            $out .= $this->linhaPar(
                '  '.$this->quantidade($item['quantidade']).' '.$item['unidade'].' x '.$this->numero($item['valor_unitario']),
                $this->numero($item['valor_total'])
            );
            if ($item['desconto'] > 0) {
                $out .= $this->linhaPar('  desconto', '-'.$this->numero($item['desconto']));
            }
        }
        $out .= $this->separadorFino();

        // Totais
        $out .= $this->linhaPar('Qtd. total de itens', (string) $d['quantidade_itens']);
        $out .= $this->linhaPar('Valor total R$', $this->numero($d['total_produtos']));
        if ($d['desconto'] > 0) {
            $out .= $this->linhaPar('Desconto R$', '-'.$this->numero($d['desconto']));
        }
        $out .= $this->cmdBold(true);
        $out .= $this->linhaPar('Valor a pagar R$', $this->numero($d['total']));
        $out .= $this->cmdBold(false);
        $out .= $this->linhaPar('FORMA DE PAGAMENTO', 'VALOR PAGO R$');
        foreach ($d['pagamentos'] as $pag) {
            $out .= $this->linhaPar($this->truncar($pag['forma'], $this->colunas - 12), $this->numero($pag['valor']));
        }
        if ($d['troco'] > 0) {
            $out .= $this->linhaPar('Troco R$', $this->numero($d['troco']));
        }
        $out .= $this->separadorFino();

        // Consulta e consumidor
        $out .= $this->cmdAlign(1);
        $out .= $this->linha('Consulte pela Chave de Acesso em');
        foreach ($this->quebrar($d['url_consulta']) as $linha) {
            $out .= $this->linha($linha);
        }
        $out .= $this->cmdBold(true);
        foreach ($this->quebrar($d['chave_formatada']) as $linha) {
            $out .= $this->linha($linha);
        }
        $out .= $this->cmdBold(false);
        $out .= $this->separadorFino();
        if ($d['consumidor']) {
            $out .= $this->linha('CONSUMIDOR - CPF/CNPJ: '.$d['consumidor']['documento']);
            if ($d['consumidor']['nome']) {
                $out .= $this->linha($this->truncar($d['consumidor']['nome'], $this->colunas));
            }
        } else {
            $out .= $this->linha('CONSUMIDOR FINAL - NAO IDENTIFICADO');
        }
        $out .= $this->separadorFino();

        // Identificação e protocolo
        $out .= $this->cmdBold(true);
        $out .= $this->linha('NFC-e n '.$d['numero'].'  Serie '.$d['serie'].'  '.($d['emissao']?->format('d/m/Y H:i:s') ?? ''));
        $out .= $this->cmdBold(false);
        $out .= $this->linha('Protocolo de autorizacao: '.$d['protocolo']);
        if ($d['autorizacao']) {
            $out .= $this->linha('Data de autorizacao: '.$d['autorizacao']->format('d/m/Y H:i:s'));
        }
        $out .= "\n";

        if ($d['qr_code'] !== '') {
            $out .= $this->qrCode($d['qr_code'], 5);
            $out .= "\n";
        }

        if ($d['info_complementar'] !== '') {
            foreach ($this->quebrar($d['info_complementar']) as $linha) {
                $out .= $this->linha($linha);
            }
        }

        $out .= "\n\n";
        $out .= $this->cmdCut();

        return $out;
    }

    protected function numero(float $valor): string
    {
        return number_format($valor, 2, ',', '.');
    }

    protected function quantidade(float $qtd): string
    {
        return rtrim(rtrim(number_format($qtd, 3, ',', ''), '0'), ',');
    }
}
