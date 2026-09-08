<?php

namespace App\Repositories\Contracts;

use App\Models\Cliente;
use Illuminate\Database\Eloquent\Collection;

interface ClienteRepositoryInterface extends RepositoryInterface
{
    public function buscarPorCpf(string $cpf): ?Cliente;

    public function buscarComContratosAtivos(): Collection;
}
