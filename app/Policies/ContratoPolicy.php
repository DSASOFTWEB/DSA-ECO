<?php

namespace App\Policies;

use App\Models\Contrato;
use App\Models\User;

class ContratoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('contratos.visualizar');
    }

    public function view(User $user, Contrato $contrato): bool
    {
        return $user->can('contratos.visualizar') && $user->empresa_id === $contrato->empresa_id;
    }

    public function create(User $user): bool
    {
        return $user->can('contratos.criar');
    }

    public function update(User $user, Contrato $contrato): bool
    {
        return $user->can('contratos.editar') && $user->empresa_id === $contrato->empresa_id;
    }

    /**
     * Cancelar contrato é uma ação sensível (afeta cobrança futura):
     * permissão específica, separada de "editar".
     */
    public function cancelar(User $user, Contrato $contrato): bool
    {
        return $user->can('contratos.cancelar') && $user->empresa_id === $contrato->empresa_id;
    }

    /**
     * Reativar é o inverso de cancelar — mesma sensibilidade (volta a
     * cobrar o cliente), então usa a mesma permissão.
     */
    public function reativar(User $user, Contrato $contrato): bool
    {
        return $user->can('contratos.cancelar') && $user->empresa_id === $contrato->empresa_id;
    }

    public function delete(User $user, Contrato $contrato): bool
    {
        return $user->hasRole('admin') && $user->empresa_id === $contrato->empresa_id;
    }
}
