<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;

interface ContratoRepositoryInterface extends RepositoryInterface
{
    /**
     * Contratos ativos cujo dia de vencimento é o informado — usado pelo
     * job mensal que gera as mensalidades do mês corrente.
     */
    public function ativosComVencimentoNoDia(int $dia): Collection;

    public function ativosPorCliente(int $clienteId): Collection;
}
