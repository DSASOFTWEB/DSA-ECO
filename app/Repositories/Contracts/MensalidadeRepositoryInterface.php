<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;

interface MensalidadeRepositoryInterface extends RepositoryInterface
{
    public function pendentesVencendoEm(\DateTimeInterface $data): Collection;

    public function atrasadas(): Collection;

    public function porContratoECompetencia(int $contratoId, \DateTimeInterface $competencia, string $tipo = 'mensalidade'): ?\App\Models\Mensalidade;
}
