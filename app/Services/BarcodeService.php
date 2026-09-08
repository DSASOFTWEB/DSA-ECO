<?php

namespace App\Services;

use Picqer\Barcode\BarcodeGeneratorPNG;

/**
 * Geração de código de barras (Code 128) em memória (data URI base64) —
 * mesmo motivo do QrCodeService: dompdf/bobina precisam da imagem já
 * embutida, não podem buscar uma rota HTTP durante a renderização.
 */
class BarcodeService
{
    public function gerarDataUri(string $conteudo, int $altura = 40, int $largura = 2): string
    {
        $gerador = new BarcodeGeneratorPNG();
        $png = $gerador->getBarcode($conteudo, BarcodeGeneratorPNG::TYPE_CODE_128, $largura, $altura);

        return 'data:image/png;base64,'.base64_encode($png);
    }
}
