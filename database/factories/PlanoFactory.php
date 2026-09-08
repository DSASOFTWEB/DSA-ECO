<?php

namespace Database\Factories;

use App\Models\Empresa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Plano>
 */
class PlanoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'empresa_id' => Empresa::factory(),
            'nome' => 'Plano '.fake()->word(),
            'valor' => 129.90,
            'periodicidade' => 'mensal',
            'max_dependentes' => 2,
            'dias_acesso_semana' => 7,
            'ativo' => true,
        ];
    }
}
