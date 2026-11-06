<?php

namespace Tests\Feature;

use App\Models\Caixa;
use App\Models\CaixaMovimentacao;
use App\Models\Empresa;
use App\Models\Unidade;
use App\Models\User;
use App\Services\CaixaService;
use App\Services\Impressao\EscPosCupomBuilder;
use App\Services\Relatorios\CaixaFechamentoResumo;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Sangria e suprimento de caixa: sempre em dinheiro, sangria limitada ao
 * dinheiro da gaveta, comprovante 80mm com assinaturas.
 */
class SangriaSuprimentoTest extends TestCase
{
    use RefreshDatabase;

    protected Caixa $caixa;

    protected User $operador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $empresa = Empresa::factory()->create(['nome' => 'Eco Clube Catolé']);
        $unidade = Unidade::factory()->create(['empresa_id' => $empresa->id]);
        $this->operador = User::factory()->create(['empresa_id' => $empresa->id, 'unidade_id' => $unidade->id, 'name' => 'Maria Caixa']);
        Gate::before(fn () => true);
        $this->actingAs($this->operador);

        // Gaveta: 100 de troco + 200 em dinheiro + 80 no cartão (cartão não conta).
        $this->caixa = Caixa::create([
            'empresa_id' => $empresa->id, 'unidade_id' => $unidade->id, 'usuario_abertura_id' => $this->operador->id,
            'data_abertura' => now(), 'valor_abertura' => 100, 'status' => 'aberto',
        ]);
        $this->mov('entrada', 'venda', 'dinheiro', 200);
        $this->mov('entrada', 'venda', 'cartao_debito', 80);
    }

    public function test_sangria_em_dinheiro_limitada_a_gaveta_com_comprovante(): void
    {
        $this->getJson(route('caixas.gaveta', $this->caixa))->assertOk()->assertJsonPath('dinheiro', 300);

        // Mais que a gaveta: recusa (o cartão não está na gaveta).
        $this->postJson(route('caixas.sangria', $this->caixa), ['valor' => 350, 'motivo' => 'Cofre'])
            ->assertStatus(422)->assertJsonPath('message', 'Sangria maior que o dinheiro na gaveta (R$ 300,00).');

        // Sem motivo: recusa.
        $this->postJson(route('caixas.sangria', $this->caixa), ['valor' => 50])->assertStatus(422)->assertJsonValidationErrors('motivo');

        $resposta = $this->postJson(route('caixas.sangria', $this->caixa), ['valor' => 250, 'motivo' => 'Recolhimento para o cofre'])
            ->assertCreated()
            ->assertJsonPath('dinheiro', 50);

        $mov = CaixaMovimentacao::where('categoria', 'sangria')->firstOrFail();
        $this->assertSame('saida', $mov->tipo);
        $this->assertSame('dinheiro', $mov->forma_pagamento, 'sempre em dinheiro');
        $this->assertEquals(250, $mov->valor);
        $this->assertSame($resposta->json('comprovante_url'), route('caixas.movimentacoes.comprovante', [$this->caixa, $mov]));

        $this->get($resposta->json('comprovante_url'))->assertOk()
            ->assertSee('SANGRIA DE CAIXA')
            ->assertSeeInOrder(['VALOR RETIRADO', 'R$ 250,00', 'Dinheiro', 'Recolhimento para o cofre'])
            ->assertSee('Entregue por (operador do caixa)')
            ->assertSee('Recebido por (gerente / cofre)');

        $bytes = $this->get(route('caixas.movimentacoes.comprovante.escpos', [$this->caixa, $mov]))->assertOk()->getContent();
        $texto = iconv('CP850', 'UTF-8', $bytes);
        $this->assertStringContainsString('SANGRIA DE CAIXA', $texto);
        $this->assertMatchesRegularExpression('/VALOR RETIRADO\s+R\$ 250,00/', $texto);
        $this->assertStringContainsString('Recebido por (gerente / cofre)', $texto);
        $this->assertStringEndsWith(EscPosCupomBuilder::CORTE, $bytes);

        // Fechamento: a gaveta já desconta a sangria.
        $this->assertEquals(50, app(CaixaFechamentoResumo::class)->montar($this->caixa->fresh())['dinheiro_gaveta']);
    }

    public function test_suprimento_soma_na_gaveta_e_motivo_e_opcional(): void
    {
        $this->postJson(route('caixas.suprimento', $this->caixa), ['valor' => 70])->assertCreated()->assertJsonPath('dinheiro', 370);

        $mov = CaixaMovimentacao::where('categoria', 'suprimento')->firstOrFail();
        $this->assertSame(['entrada', 'dinheiro', 'Reforço de troco'], [$mov->tipo, $mov->forma_pagamento, $mov->descricao]);
        $this->get(route('caixas.movimentacoes.comprovante', [$this->caixa, $mov]))->assertSee('SUPRIMENTO DE CAIXA')->assertSee('VALOR COLOCADO');
    }

    public function test_formulario_generico_segue_as_mesmas_regras(): void
    {
        // Sangria pelo formulário antigo, sem forma de pagamento: vira dinheiro e respeita a gaveta.
        $this->post(route('caixas.movimentar', $this->caixa), ['tipo' => 'saida', 'categoria' => 'sangria', 'valor' => 999])
            ->assertSessionHas('erro', fn ($e) => str_contains($e, 'maior que o dinheiro na gaveta'));

        $this->post(route('caixas.movimentar', $this->caixa), ['tipo' => 'saida', 'categoria' => 'sangria', 'valor' => 30, 'descricao' => 'Cofre'])
            ->assertSessionHas('sucesso');
        $this->assertDatabaseHas('caixa_movimentacoes', ['categoria' => 'sangria', 'forma_pagamento' => 'dinheiro', 'valor' => 30]);
    }

    public function test_estorno_devolve_o_dinheiro_a_gaveta(): void
    {
        $mov = app(CaixaService::class)->sangria($this->caixa, 100, 'Cofre', $this->operador);
        app(CaixaService::class)->estornar($mov, $this->operador);

        $this->assertEquals(300, app(CaixaService::class)->dinheiroEmGaveta($this->caixa));
        $this->get(route('caixas.movimentacoes.comprovante', [$this->caixa, $mov]))->assertSee('ESTORNADO EM');
    }

    public function test_botoes_na_tela_do_caixa_e_no_pdv(): void
    {
        // @js gera JSON.parse('...') e escapa cada barra da URL como \\\/.
        $js = fn (string $url) => str_replace('/', '\\\\\\/', $url);

        $this->get(route('caixas.show', $this->caixa))->assertOk()
            ->assertSee('− Sangria')->assertSee('+ Suprimento')
            ->assertSee($js(route('caixas.sangria', $this->caixa)), false);

        $this->get(route('vendas.create', ['caixa_id' => $this->caixa->id]))->assertOk()
            ->assertSee('Sangria / Suprimento')
            ->assertSee('F9')
            ->assertSee($js(route('caixas.suprimento', $this->caixa)), false);
    }

    public function test_resumo_do_caixa_por_forma_com_grafico(): void
    {
        $this->mov('entrada', 'venda', 'pix', 20);
        app(CaixaService::class)->suprimento($this->caixa, 50, null, $this->operador);
        app(CaixaService::class)->sangria($this->caixa, 30, 'Cofre', $this->operador);

        $r = app(CaixaFechamentoResumo::class)->montar($this->caixa->fresh());

        // Suprimento não é recebimento: dinheiro 200, débito 80, pix 20 = 300.
        $this->assertSame(300.0, $r['recebido_total']);
        $this->assertSame(['dinheiro', 'cartao_debito', 'pix'], array_column($r['recebimentos'], 'chave'));
        $this->assertEquals([66.7, 26.7, 6.7], array_column($r['recebimentos'], 'percentual'));
        $this->assertSame(320.0, $r['dinheiro_gaveta'], '100 + 200 + 50 suprimento − 30 sangria');
        $this->assertSame('conic-gradient(#16a34a 0% 66.7%, #7c3aed 66.7% 93.4%, #0d9488 93.4% 100%)', CaixaFechamentoResumo::gradientePizza($r['recebimentos']));

        $this->get(route('caixas.show', $this->caixa))->assertOk()
            ->assertSee('conic-gradient(', false)
            ->assertSeeInOrder(['Recebido', 'R$ 300,00', 'Dinheiro', 'R$ 200,00', '67%', 'Cartão de débito', 'R$ 80,00', 'Pix', 'R$ 20,00'])
            ->assertSeeInOrder(['Dinheiro na gaveta', 'R$ 320,00']);
    }

    public function test_caixa_fechado_ou_de_outra_empresa_nao_movimenta(): void
    {
        app(CaixaService::class)->fechar($this->caixa, $this->operador, 380);
        $this->postJson(route('caixas.sangria', $this->caixa), ['valor' => 10, 'motivo' => 'x'])
            ->assertStatus(422)->assertJsonPath('message', 'Não é possível movimentar um caixa fechado.');
        $this->assertDatabaseMissing('caixa_movimentacoes', ['categoria' => 'sangria']);

        $outro = User::factory()->create(['empresa_id' => Empresa::factory()->create()->id]);
        $this->actingAs($outro)->getJson(route('caixas.gaveta', $this->caixa))->assertNotFound();
    }

    protected function mov(string $tipo, string $categoria, string $forma, float $valor): void
    {
        CaixaMovimentacao::create([
            'caixa_id' => $this->caixa->id, 'tipo' => $tipo, 'categoria' => $categoria, 'descricao' => 'Venda',
            'valor' => $valor, 'forma_pagamento' => $forma, 'usuario_id' => $this->operador->id,
        ]);
    }
}
