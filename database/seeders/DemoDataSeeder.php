<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\Plano;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Dados mínimos para o sistema ficar utilizável logo após o
 * `php artisan migrate --seed`: uma empresa, uma unidade, o usuário
 * administrador e alguns planos de exemplo. NÃO é dado fictício de teste
 * de carga — é o "onboarding" inicial de uma nova instalação do SaaS.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $empresa = Empresa::firstOrCreate(
            ['cnpj' => '00.000.000/0001-00'],
            ['nome' => 'Parque Aquático Exemplo', 'plano_saas' => 'padrao', 'status' => 'ativo']
        );

        $unidade = Unidade::firstOrCreate(
            ['empresa_id' => $empresa->id, 'nome' => 'Unidade Sede'],
            ['cidade' => 'São Paulo', 'uf' => 'SP', 'status' => 'ativo', 'capacidade_maxima' => 2000]
        );

        $admin = User::firstOrCreate(
            ['email' => 'admin@parqueaquatico.com.br'],
            [
                'empresa_id' => $empresa->id,
                'unidade_id' => $unidade->id,
                'name' => 'Administrador',
                'password' => Hash::make('trocar@123'),
                'status' => 'ativo',
            ]
        );
        $admin->syncRoles(['admin']);

        // Conta operacional da equipe do SaaS (bypass via Gate::before).
        // Sem empresa — enxerga todos os tenants. Não aparece no cadastro
        // público de empresa nem no formulário de papéis do tenant.
        $super = User::withoutGlobalScopes()->firstOrCreate(
            ['email' => 'super@parqueaquatico.com.br'],
            [
                'empresa_id' => null,
                'unidade_id' => null,
                'name' => 'Super Admin',
                'password' => Hash::make('trocar@123'),
                'status' => 'ativo',
            ]
        );
        $super->syncRoles(['super_admin']);

        collect([
            ['nome' => 'Plano Individual', 'valor' => 129.90, 'max_dependentes' => 0],
            ['nome' => 'Plano Família (até 4)', 'valor' => 349.90, 'max_dependentes' => 3],
            ['nome' => 'Plano Anual Individual', 'valor' => 1299.00, 'periodicidade' => 'anual', 'max_dependentes' => 0],
        ])->each(fn (array $dados) => Plano::firstOrCreate(
            ['empresa_id' => $empresa->id, 'nome' => $dados['nome']],
            [
                'valor' => $dados['valor'],
                'periodicidade' => $dados['periodicidade'] ?? 'mensal',
                'max_dependentes' => $dados['max_dependentes'],
                'dias_acesso_semana' => 7,
                'ativo' => true,
            ]
        ));

        $this->command?->info('Login do administrador: admin@parqueaquatico.com.br / trocar@123 (altere após o primeiro acesso).');
        $this->command?->info('Login super_admin (SaaS): super@parqueaquatico.com.br / trocar@123');
    }
}
