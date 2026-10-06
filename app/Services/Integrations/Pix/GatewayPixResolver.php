<?php

namespace App\Services\Integrations\Pix;

use App\Models\Empresa;

/**
 * Escolhe o gateway Pix configurado na empresa. Registrado no container
 * para que os testes troquem por um gateway falso.
 */
class GatewayPixResolver
{
    /**
     * Gateway selecionado hoje na empresa — usado para gerar cobranças novas.
     */
    public function paraEmpresa(Empresa $empresa): ?GatewayPix
    {
        if (! $empresa->gatewayPixAtivo()) {
            return null;
        }

        return $this->porNome($empresa, $empresa->configuracaoGatewayPix()['provedor']);
    }

    /**
     * Gateway que gerou uma cobrança já existente — continua consultável
     * mesmo se a empresa trocou de provedor depois.
     */
    public function porNome(Empresa $empresa, string $gateway): ?GatewayPix
    {
        return match ($gateway) {
            Empresa::GATEWAY_PIX_ITAU => new ItauPixGateway($empresa),
            Empresa::GATEWAY_PIX_MERCADOPAGO => new MercadoPagoPixGateway($empresa),
            default => null,
        };
    }
}
