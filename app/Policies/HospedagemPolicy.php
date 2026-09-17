<?php

namespace App\Policies;

use App\Models\Hospedagem;
use App\Models\User;

class HospedagemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('pousada.visualizar');
    }

    public function view(User $user, Hospedagem $hospedagem): bool
    {
        return $user->can('pousada.visualizar') && $user->empresa_id === $hospedagem->empresa_id;
    }

    public function create(User $user): bool
    {
        return $user->can('pousada.reservar');
    }

    public function checkin(User $user, Hospedagem $hospedagem): bool
    {
        return $user->can('pousada.checkin') && $user->empresa_id === $hospedagem->empresa_id;
    }

    public function consumos(User $user, Hospedagem $hospedagem): bool
    {
        return $user->can('pousada.consumos') && $user->empresa_id === $hospedagem->empresa_id;
    }

    public function checkout(User $user, Hospedagem $hospedagem): bool
    {
        return $user->can('pousada.checkout') && $user->empresa_id === $hospedagem->empresa_id;
    }

    public function emitirFiscal(User $user, Hospedagem $hospedagem): bool
    {
        return $user->can('pousada.checkout') && $user->empresa_id === $hospedagem->empresa_id;
    }

    public function cancelar(User $user, Hospedagem $hospedagem): bool
    {
        return $user->can('pousada.reservar') && $user->empresa_id === $hospedagem->empresa_id;
    }
}
