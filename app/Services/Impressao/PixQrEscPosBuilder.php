<?php

namespace App\Services\Impressao;

use App\Models\CobrancaPix;

/**
 * Comprovante de cobrança Pix (QR Code para o cliente pagar) em ESC/POS.
 * O QR é desenhado pela própria impressora (GS ( k, modelo 2), então sai
 * nítido em qualquer bobina 80mm sem precisar gerar imagem.
 */
class PixQrEscPosBuilder extends EscPosCupomBuilder
{
    /**
     * Tamanho do módulo do QR (1-16 pontos). Copia-e-cola Pix tem ~150-250
     * caracteres (QR versão 8-10); 6 pontos cabe com folga nos 576 pontos da
     * bobina 80mm e fica fácil de ler na câmera.
     */
    public const TAMANHO_MODULO = 6;

    public function montarCobranca(CobrancaPix $cobranca, string $empresaNome, string $referencia): string
    {
        $out = $this->cmdInit();
        $out .= $this->cmdAlign(1);
        $out .= $this->cmdBold(true);
        $out .= $this->linha($empresaNome);
        $out .= $this->cmdBold(false);
        $out .= $this->separadorFino();
        $out .= $this->linha('PAGUE COM PIX');
        $out .= $this->linha($referencia);
        $out .= "\n";
        $out .= $this->cmdTamanhoTexto(2);
        $out .= $this->linha($this->moeda($cobranca->valor));
        $out .= $this->cmdTamanhoTexto(1);
        $out .= "\n";
        $out .= $this->qrCode((string) $cobranca->pix_copia_e_cola);
        $out .= "\n";
        $out .= $this->linha('Abra o app do seu banco e leia o QR Code');

        if ($cobranca->expira_em) {
            $out .= $this->linha('Valido ate '.$cobranca->expira_em->timezone('America/Sao_Paulo')->format('d/m/Y H:i'));
        }

        $out .= $this->separadorFino();
        $out .= $this->cmdAlign(0);
        $out .= $this->linha('Pix copia e cola:');
        foreach (str_split((string) $cobranca->pix_copia_e_cola, $this->colunas) as $trecho) {
            $out .= $this->linha($trecho);
        }
        $out .= $this->cmdAlign(1);
        $out .= $this->separadorFino();
        $out .= $this->linha('*** NAO E COMPROVANTE DE PAGAMENTO ***');
        $out .= "\n\n";
        $out .= $this->cmdCut();

        return $out;
    }

    /**
     * QR Code nativo (GS ( k): modelo 2, tamanho do módulo, correção M,
     * grava os dados e imprime.
     */
    protected function qrCode(string $dados): string
    {
        $tamanho = strlen($dados) + 3;

        return "\x1D(k\x04\x00\x31\x41\x32\x00"                       // modelo 2
            ."\x1D(k\x03\x00\x31\x43".chr(self::TAMANHO_MODULO)       // tamanho do módulo
            ."\x1D(k\x03\x00\x31\x45\x31"                             // correção de erro M
            ."\x1D(k".chr($tamanho % 256).chr(intdiv($tamanho, 256))."\x31\x50\x30".$dados // grava
            ."\x1D(k\x03\x00\x31\x51\x30";                            // imprime
    }

    /** GS ! — 1 = normal, 2 = altura e largura dobradas. */
    protected function cmdTamanhoTexto(int $fator): string
    {
        $n = max(0, min(7, $fator - 1));

        return "\x1D\x21".chr(($n << 4) | $n);
    }
}
