<?php

namespace Tests\Feature;

use App\Models\ContaReceber;
use App\Models\Empresa;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class FinanceiroEstornoLimpezaTest extends TestCase
{
    use RefreshDatabase;

    public function test_pode_estornar_conta_a_receber_ja_recebida(): void
    {
        [$user] = $this->criarOperadorFinanceiro();

        $conta = ContaReceber::create([
            'empresa_id' => $user->empresa_id,
            'unidade_id' => $user->unidade_id,
            'pagador' => 'Cliente teste',
            'descricao' => 'Patrocínio',
            'valor' => 150.00,
            'data_vencimento' => now()->addDays(5)->toDateString(),
            'status' => 'recebido',
            'data_recebimento' => now()->toDateString(),
            'forma_pagamento' => 'pix',
            'criado_por_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->post(route('contas-receber.estornar', $conta))
            ->assertRedirect();

        $conta->refresh();
        $this->assertSame('pendente', $conta->status);
        $this->assertNull($conta->data_recebimento);
        $this->assertNull($conta->forma_pagamento);
    }

    public function test_limpar_financeiro_na_empresa_remove_contas_avulsas(): void
    {
        [$user] = $this->criarOperadorFinanceiro(comEmpresaGerenciar: true);

        ContaReceber::create([
            'empresa_id' => $user->empresa_id,
            'unidade_id' => $user->unidade_id,
            'pagador' => 'A',
            'descricao' => 'Receber 1',
            'valor' => 10,
            'data_vencimento' => now()->toDateString(),
            'status' => 'pendente',
            'criado_por_id' => $user->id,
        ]);
        ContaReceber::create([
            'empresa_id' => $user->empresa_id,
            'unidade_id' => $user->unidade_id,
            'pagador' => 'B',
            'descricao' => 'Receber 2',
            'valor' => 20,
            'data_vencimento' => now()->toDateString(),
            'status' => 'recebido',
            'data_recebimento' => now()->toDateString(),
            'forma_pagamento' => 'dinheiro',
            'criado_por_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->post(route('empresa.limpar-financeiro'), ['confirmacao' => 'LIMPAR'])
            ->assertRedirect(route('empresa.edit'));

        $this->assertSame(0, ContaReceber::query()->count());
        $this->assertSame(2, ContaReceber::withTrashed()->count());
    }

    public function test_limpar_financeiro_exige_confirmacao(): void
    {
        [$user] = $this->criarOperadorFinanceiro(comEmpresaGerenciar: true);

        $this->actingAs($user)
            ->from(route('empresa.edit'))
            ->post(route('empresa.limpar-financeiro'), ['confirmacao' => 'nao'])
            ->assertRedirect(route('empresa.edit'))
            ->assertSessionHasErrors('confirmacao');
    }

    /**
     * @return array{0: User}
     */
    private function criarOperadorFinanceiro(bool $comEmpresaGerenciar = false): array
    {
        $perms = ['contas_receber.visualizar', 'contas_receber.gerenciar', 'contas_pagar.visualizar', 'contas_pagar.gerenciar'];
        if ($comEmpresaGerenciar) {
            $perms[] = 'empresa.gerenciar';
        }
        foreach ($perms as $nome) {
            Permission::findOrCreate($nome, 'web');
        }

        $empresa = Empresa::factory()->create();
        $unidade = Unidade::factory()->create(['empresa_id' => $empresa->id]);
        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'unidade_id' => $unidade->id,
        ]);
        $user->givePermissionTo($perms);

        return [$user->fresh()];
    }
}
