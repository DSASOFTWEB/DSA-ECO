<?php

namespace App\Services\Integrations\Pix;

/**
 * Certificado mTLS do Pix Itaú (CSR emitido em sts.itau.com.br).
 * Toda a guarda banco/disco está em CertificadoPixService.
 */
class ItauCertificadoService extends CertificadoPixService
{
    public function provedor(): string
    {
        return 'itau';
    }

    protected function rotulo(): string
    {
        return 'Itaú';
    }
}
