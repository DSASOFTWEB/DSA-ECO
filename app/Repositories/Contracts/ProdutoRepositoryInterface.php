<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;

interface ProdutoRepositoryInterface extends RepositoryInterface
{
    public function abaixoDoEstoqueMinimo(): Collection;
}
