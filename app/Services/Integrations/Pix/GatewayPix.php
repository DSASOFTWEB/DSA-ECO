<?php

namespace App\Services\Integrations\Pix;

use App\Exceptions\IntegrationException;

/**
 * Contrato mínimo de um PSP que gera Pix dinâmico (cob imediata do padrão
 * BACEN) para a tela de recebimento do PDV.
 */
interface GatewayPix
{
    public const STATUS_PENDENTE = 'pendente';

    public const STATUS_PAGA = 'paga';

    public const STATUS_CANCELADA = 'cancelada';

    public function nome(): string;

    /**
     * @return array{txid: string, copia_e_cola: string, expiracao_segundos: int, payload: array}
     *
     * @throws IntegrationException
     */
    public function criarCobranca(float $valor, string $descricao): array;

    /**
     * @return array{status: string, e2eid: ?string, payload: array}
     *
     * @throws IntegrationException
     */
    public function consultarCobranca(string $txid): array;

    /**
     * @throws IntegrationException
     */
    public function cancelarCobranca(string $txid): void;
}
