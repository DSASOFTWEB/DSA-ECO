<?php

namespace Tests\Feature;

use App\Models\Caixa;
use App\Models\CaixaMovimentacao;
use App\Models\Empresa;
use App\Models\Unidade;
use App\Models\User;
use App\Services\CaixaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Relatório "Resumo dos caixas": todos os caixas juntos por forma de pagamento.
 */
class ResumoCaixasRelatorioTest extends TestCase
{
    use RefreshDatabase;

    protected Empresa $empresa;

    protected User $operador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empresa = Empresa::factory()->create();
        $this->operador = User::factory()->create(['empresa_id' => $this->empresa->id, 'name' => 'Maria Caixa']);
        Gate::before(fn () => true);
        $this->actingAs($this->operador);
    }

    public function test_soma_todos_os_caixas_por_forma_com_grafico(): void
    {
        $unidade = Unidade::factory()->create(['empresa_id' => $this->empresa->id]);
        $portaria = $this->caixa($unidade, 50);
        $restaurante = $this->caixa($unidade, 0, 'fechado');

        $this->mov($portaria, 'entrada', 'venda', 'dinheiro', 300);
        $this->mov($portaria, 'entrada', 'venda', 'pix', 100);
        $this->mov($restaurante, 'entrada', 'venda', 'cartao_credito', 150);
        $this->mov($restaurante, 'entrada', 'venda', 'cartao_debito', 50);
        $this->mov($restaurante, 'entrada', 'venda', 'dinheiro', 100);
        app(CaixaService::class)->suprimento($portaria, 40, null, $this->operador);   // não é recebimento
        $sangria = app(CaixaService::class)->sangria($portaria, 200, 'Cofre', $this->operador);

        // Outra empresa não entra.
        $outra = Empresa::factory()->create();
        $caixaOutra = Caixa::create(['empresa_id' => $outra->id, 'unidade_id' => Unidade::factory()->create(['empresa_id' => $outra->id])->id,
            'usuario_abertura_id' => $this->operador->id, 'data_abertura' => now(), 'valor_abertura' => 0, 'status' => 'aberto']);
        $this->mov($caixaOutra, 'entrada', 'venda', 'pix', 9999);

        $resposta = $this->get(route('relatorios.gerencial', ['relatorio' => 'resumo-caixas', 'inicio' => now()->toDateString(), 'fim' => now()->toDateString()]))
            ->assertOk()
            ->assertSee('Resumo dos caixas')
            ->assertSee('conic-gradient(', false)
            ->assertSeeInOrder(['Recebido no período', 'R$ 700,00', 'Dinheiro', 'R$ 400,00', 'Pix', 'R$ 100,00', 'Cartões', 'R$ 200,00'])
            ->assertSeeInOrder(['Recebido por forma de pagamento', 'Dinheiro', 'R$ 400,00', '57,1%', 'Cartão de crédito', 'R$ 150,00'])
            ->assertDontSee('9.999');

        $linhas = $resposta->viewData('relatorio')['linhas'];
        $this->assertCount(2, $linhas);
        $p = collect($linhas)->firstWhere('caixa', '#'.$portaria->id);
        $this->assertSame([300.0, 100.0, 0.0, 400.0, 200.0, 40.0, 190.0], [$p['dinheiro'], $p['pix'], $p['cartao'], $p['recebido'], $p['sangria'], $p['suprimento'], $p['gaveta']]);

        // Estorno da sangria: volta o dinheiro sem contar em dobro (no outro relatório também).
        app(CaixaService::class)->estornar($sangria, $this->operador);
        $caixas = $this->get(route('relatorios.gerencial', ['relatorio' => 'caixas']))->viewData('relatorio')['linhas'];
        $this->assertEquals(50 + 440 + 200 - 200, collect($caixas)->firstWhere('caixa', '#'.$portaria->id)['saldo'], 'sangria e estorno se anulam');

        $this->get(route('relatorios.index'))->assertSee('Resumo dos caixas');
        $this->get(route('relatorios.gerencial.excel', ['relatorio' => 'resumo-caixas']))->assertOk();
    }

    protected function caixa(Unidade $unidade, float $abertura, string $status = 'aberto'): Caixa
    {
        return Caixa::create([
            'empresa_id' => $this->empresa->id, 'unidade_id' => $unidade->id, 'usuario_abertura_id' => $this->operador->id,
            'data_abertura' => now(), 'valor_abertura' => $abertura, 'status' => $status,
        ]);
    }

    protected function mov(Caixa $caixa, string $tipo, string $categoria, string $forma, float $valor): void
    {
        CaixaMovimentacao::create([
            'caixa_id' => $caixa->id, 'tipo' => $tipo, 'categoria' => $categoria, 'descricao' => 'x',
            'valor' => $valor, 'forma_pagamento' => $forma, 'usuario_id' => $this->operador->id,
        ]);
    }
}
