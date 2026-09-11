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
        return $user->can('usuarios.visualizar') && $this->mesmaEmpresa($user, $alvo);
    }

    public function create(User $user): bool
    {
        return $user->can('usuarios.criar');
    }

    public function update(User $user, User $alvo): bool
    {
        // Alvo ainda não persistido (binding quebrado) nunca deve passar —
        // evita 403 ambíguo; o controller responde 404 antes.
        if (! $alvo->exists) {
            return false;
        }

        return $user->can('usuarios.editar') && $this->mesmaEmpresa($user, $alvo);
    }

    public function delete(User $user, User $alvo): bool
    {
        // Ninguém pode se autoexcluir e apenas admin remove outros usuários.
        return $user->can('usuarios.excluir')
            && $this->mesmaEmpresa($user, $alvo)
            && $user->id !== $alvo->id;
    }

    public function gerenciarPapeis(User $user, User $alvo): bool
    {
        return $user->hasRole('admin') && $this->mesmaEmpresa($user, $alvo);
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

    /**
     * Compara empresa_id com cast para int — PDO/MySQL pode devolver string
     * e o === estrito falhava com 403 falso-positivo no mesmo tenant.
     */
    private function mesmaEmpresa(User $user, User $alvo): bool
    {
        if ($user->empresa_id === null || $alvo->empresa_id === null) {
            return false;
        }

        return (int) $user->empresa_id === (int) $alvo->empresa_id;
    }
}
