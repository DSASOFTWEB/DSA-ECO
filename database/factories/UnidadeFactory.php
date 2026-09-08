<?php

namespace Database\Factories;

use App\Models\Empresa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Unidade>
 */
class UnidadeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'empresa_id' => Empresa::factory(),
            'nome' => 'Unidade '.fake()->city(),
            'cidade' => fake()->city(),
            'uf' => fake()->stateAbbr(),
            'status' => 'ativo',
            'capacidade_maxima' => 1000,
        ];
    }
}
