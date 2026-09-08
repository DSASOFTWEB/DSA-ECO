<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('usuarios.visualizar');
    }

    public function view(User $user, User $alvo): bool
    {
        return $user->can('usuarios.visualizar') && $user->empresa_id === $alvo->empresa_id;
    }

    public function create(User $user): bool
    {
        return $user->can('usuarios.criar');
    }

    public function update(User $user, User $alvo): bool
    {
        return $user->can('usuarios.editar') && $user->empresa_id === $alvo->empresa_id;
    }

    public function delete(User $user, User $alvo): bool
    {
        // Ninguém pode se autoexcluir e apenas admin remove outros usuários.
        return $user->can('usuarios.excluir')
            && $user->empresa_id === $alvo->empresa_id
            && $user->id !== $alvo->id;
    }

    public function gerenciarPapeis(User $user, User $alvo): bool
    {
        return $user->hasRole('admin') && $user->empresa_id === $alvo->empresa_id;
    }

    /**
     * Só um admin do tenant pode conceder o papel "admin" a alguém — evita
     * que um papel com "usuarios.criar"/"usuarios.editar" mas sem ser admin
     * (ex.: gerente) escale a própria conta ou a de terceiros.
     */
    public function atribuirAdmin(User $user): bool
    {
        return $user->hasRole('admin');
    }
}
