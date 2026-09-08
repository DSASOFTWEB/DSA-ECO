<?php

namespace App\Policies;

use App\Models\Carteirinha;
use App\Models\User;

class CarteirinhaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('carteirinhas.visualizar');
    }

    public function view(User $user, Carteirinha $carteirinha): bool
    {
        return $user->can('carteirinhas.visualizar') && $user->empresa_id === $this->empresaId($carteirinha);
    }

    public function emitir(User $user): bool
    {
        return $user->can('carteirinhas.emitir');
    }

    public function bloquear(User $user, Carteirinha $carteirinha): bool
    {
        return $user->can('carteirinhas.bloquear') && $user->empresa_id === $this->empresaId($carteirinha);
    }

    public function validarAcesso(User $user): bool
    {
        // Permissão usada pelo terminal/totem de catraca (usuário de serviço).
        return $user->can('acessos.validar');
    }

    /**
     * Carteirinha não tem empresa_id próprio — pertence à empresa do
     * titular (cliente direto, ou o cliente do dependente).
     */
    protected function empresaId(Carteirinha $carteirinha): ?int
    {
        return $carteirinha->cliente?->empresa_id ?? $carteirinha->dependente?->cliente?->empresa_id;
    }
}
