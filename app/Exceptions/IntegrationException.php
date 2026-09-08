<?php

namespace App\Exceptions;

use Exception;

/**
 * Lançada quando uma integração externa (Evolution API / Mercado Pago)
 * falha após esgotar as tentativas de retry configuradas.
 */
class IntegrationException extends Exception
{
    public function __construct(
        string $message,
        public readonly string $servico,
        public readonly ?array $respostaBruta = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
