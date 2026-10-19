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
        $out .= $this->qrCode((string) $cobranca->pix_copia_e_cola, self::TAMANHO_MODULO);
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
}
