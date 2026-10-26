<?php

namespace App\Support;

class Telefone
{
    /**
     * Número no formato do WhatsApp (só dígitos, com DDI 55): "(83) 99999-1234"
     * vira "5583999991234". Devolve null se não tiver DDD + número.
     */
    public static function paraWhatsapp(?string $telefone): ?string
    {
        $numero = preg_replace('/\D+/', '', (string) $telefone) ?? '';

        if (strlen($numero) === 10 || strlen($numero) === 11) {
            $numero = '55'.$numero;
        }

        return strlen($numero) >= 12 && strlen($numero) <= 13 ? $numero : null;
    }
}
