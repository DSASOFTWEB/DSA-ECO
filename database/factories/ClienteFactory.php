<?php

namespace Database\Factories;

use App\Models\Empresa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cliente>
 */
class ClienteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'empresa_id' => Empresa::factory(),
            'nome' => fake()->name(),
            'cpf' => fake()->unique()->numerify('###.###.###-##'),
            'email' => fake()->unique()->safeEmail(),
            'telefone' => fake()->numerify('119########'),
            'whatsapp' => fake()->numerify('119########'),
            'status' => 'ativo',
        ];
    }
}
