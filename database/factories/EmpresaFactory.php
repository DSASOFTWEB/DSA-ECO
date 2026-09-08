<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Empresa>
 */
class EmpresaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nome' => fake()->company(),
            'cnpj' => fake()->unique()->numerify('##.###.###/####-##'),
            'plano_saas' => 'padrao',
            'status' => 'ativo',
        ];
    }
}
