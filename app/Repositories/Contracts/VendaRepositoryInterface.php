<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;

interface VendaRepositoryInterface extends RepositoryInterface
{
    public function porCaixa(int $caixaId): Collection;
}
