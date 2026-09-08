<?php

namespace App\Exceptions;

use Exception;

/**
 * Exceção lançada quando uma regra de negócio impede a operação
 * (ex: "estoque insuficiente", "contrato já cancelado", "carteirinha bloqueada").
 * Controllers capturam esta exceção e devolvem a mensagem amigável ao usuário,
 * sem expor detalhes internos.
 */
class NegocioException extends Exception
{
    //
}
