<?php

namespace App\Services;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;

/**
 * Geração de QR Code em memória (data URI base64), sem gravar arquivo em
 * disco — usado nos PDFs de voucher/carteirinha, que precisam da imagem já
 * embutida (dompdf não pode ir buscar uma rota HTTP durante a renderização).
 */
class QrCodeService
{
    public function gerarDataUri(string $conteudo, int $tamanho = 200): string
    {
        $result = Builder::create()
            ->writer(new PngWriter())
            ->data($conteudo)
            ->size($tamanho)
            ->margin(6)
            ->build();

        return 'data:image/png;base64,'.base64_encode($result->getString());
    }
}
