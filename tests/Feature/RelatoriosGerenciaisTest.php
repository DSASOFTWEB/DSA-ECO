<?php

namespace Tests\Feature;

use App\Models\Caixa;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Hospedagem;
use App\Models\PontoAtendimento;
use App\Models\Produto;
use App\Models\Quarto;
use App\Models\TipoEntrada;
use App\Models\Unidade;
use App\Models\User;
use App\Models\Venda;
use App\Services\Food\AtendimentoService;
use App\Services\HospedagemService;
use App\Services\Relatorios\RelatorioGerencialService;
use App\Services\VendaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class RelatoriosGerenciaisTest extends TestCase
{
    use RefreshDatabase;

    protected Empresa $empresa;

    protected Unidade $unidade;

    protected User $operador;

    protected Caixa $caixa;

    protected Produto $produto;

    protected TipoEntrada $tipo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = Empresa::factory()->create();
        $this->unidade = Unidade::factory()->create(['empresa_id' => $this->empresa->id]);
        $this->operador = User::factory()->create(['empresa_id' => $this->empresa->id, 'unidade_id' => $this->unidade->id, 'name' => 'Ana Caixa']);
        Gate::before(fn () => true);
        $this->actingAs($this->operador);

        $this->caixa = Caixa::create([
            'empresa_id' => $this->empresa->id, 'unidade_id' => $this->unidade->id, 'usuario_abertura_id' => $this->operador->id,
            'data_abertura' => now(), 'valor_abertura' => 100, 'status' => 'aberto',
        ]);
        $this->produto = Produto::create([
            'empresa_id' => $this->empresa->id, 'unidade_id' => $this->unidade->id, 'nome' => 'Picolé', 'preco_custo' => 2,
            'preco_venda' => 10, 'controla_estoque' => true, 'estoque_atual' => 100, 'estoque_minimo' => 0, 'ativo' => true,
        ]);
        $this->tipo = TipoEntrada::create(['empresa_id' => $this->empresa->id, 'nome' => 'Adulto avulso', 'valor' => 50, 'eh_plano' => false, 'ativo' => true]);
    }

    public function test_venda_grava_a_origem_de_cada_canal(): void
    {
        [$pdv, $mesa, $online] = $this->cenarioVendas();

        $this->assertSame(Venda::ORIGEM_PDV, $pdv->fresh()->origem);
        $this->assertSame(Venda::ORIGEM_MESA, $mesa->fresh()->origem);
        $this->assertSame(Venda::ORIGEM_ONLINE, $online->fresh()->origem);
    }

    public function test_numeros_dos_relatorios_batem_com_as_vendas(): void
    {
        $this->cenarioVendas();
        $this->hospedagemFinalizada(300);
        $service = app(RelatorioGerencialService::class)->periodo(now()->startOfMonth(), now());

        // PDV: 2 picolés (20) + 1 adulto (50) = 70 | Mesa: 3 picolés = 30 | Online: 2 adultos = 100 | Hospedagem 300
        $fat = $service->gerar('faturamento');
        $this->assertEqualsWithDelta(500, $fat['resumo'][0][1], 0.01);
        $this->assertEqualsWithDelta(500, $fat['totais']['total'], 0.01);

        $produtos = $service->gerar('produtos');
        $this->assertSame('Picolé', $produtos['linhas'][0]['produto']);
        $this->assertEquals(5, $produtos['linhas'][0]['qtd']);
        $this->assertEqualsWithDelta(50, $produtos['linhas'][0]['total'], 0.01);

        $tipos = $service->gerar('tipos-entrada');
        $this->assertEquals(1, $tipos['linhas'][0]['qtd_pdv']);
        $this->assertEquals(2, $tipos['linhas'][0]['qtd_online']);

        $vendedores = $service->gerar('vendedores');
        $this->assertSame('Ana Caixa', $vendedores['linhas'][0]['vendedor']);
        $this->assertEquals(2, $vendedores['linhas'][0]['vendas']);

        $mesas = $service->gerar('mesas');
        $this->assertEquals(1, $mesas['linhas'][0]['contas']);
        $this->assertEqualsWithDelta(30, $mesas['linhas'][0]['total'], 0.01);

        $quartos = $service->gerar('quartos');
        $this->assertEqualsWithDelta(300, $quartos['totais']['total'], 0.01);

        $link = $service->gerar('link-externo');
        $this->assertEqualsWithDelta(100, $link['resumo'][0][1], 0.01);
        $this->assertCount(1, $link['linhas']);

        $ticket = $service->gerar('ticket-medio');
        $this->assertEqualsWithDelta(500 / 4, $ticket['resumo'][0][1], 0.01); // 4 vendas/contas

        $caixas = $service->gerar('caixas');
        $this->assertCount(1, $caixas['linhas']);
        $this->assertGreaterThan(0, $caixas['linhas'][0]['entradas']);
    }

    public function test_todas_as_telas_e_excel_abrem(): void
    {
        $this->cenarioVendas();
        $this->hospedagemFinalizada(300);

        $this->get(route('relatorios.index'))->assertOk()->assertSee('Relatórios gerenciais')->assertSee('Vendas por produto');

        foreach (array_keys(RelatorioGerencialService::RELATORIOS) as $relatorio) {
            $this->get(route('relatorios.gerencial', $relatorio))->assertOk()->assertSee(RelatorioGerencialService::RELATORIOS[$relatorio]['titulo']);
            $this->get(route('relatorios.gerencial', ['relatorio' => $relatorio, 'imprimir' => 1]))->assertOk()->assertSee('Imprimir / salvar PDF');
            $this->get(route('relatorios.gerencial.excel', $relatorio))->assertOk()->assertDownload();
        }

        $this->get(route('relatorios.gerencial', 'nao-existe'))->assertNotFound();
    }

    public function test_relatorio_nao_mostra_dados_de_outra_empresa(): void
    {
        $this->cenarioVendas();

        $outra = Empresa::factory()->create();
        $intruso = User::factory()->create(['empresa_id' => $outra->id]);

        $this->actingAs($intruso)
            ->get(route('relatorios.gerencial', 'produtos'))
            ->assertOk()
            ->assertDontSee('Picolé')
            ->assertSee('Sem dados no período.');
    }

    /** @return array{0: Venda, 1: Venda, 2: Venda} */
    protected function cenarioVendas(): array
    {
        $vendas = app(VendaService::class);
        $pdv = $vendas->criar([
            'forma_pagamento' => 'dinheiro',
            'itens' => [['produto_id' => $this->produto->id, 'quantidade' => 2], ['tipo_entrada_id' => $this->tipo->id, 'quantidade' => 1]],
        ], $this->operador, $this->caixa);

        $mesa = PontoAtendimento::create(['empresa_id' => $this->empresa->id, 'unidade_id' => $this->unidade->id, 'tipo' => 'mesa', 'numero' => 7, 'status' => 'livre']);
        $food = app(AtendimentoService::class);
        $atendimento = $food->abrir($mesa, $this->operador, 2);
        $food->adicionarItem($atendimento, ['produto_id' => $this->produto->id, 'quantidade' => 3], $this->operador);
        $atendimento = $food->fechar($atendimento, [
            'caixa_id' => $this->caixa->id, 'percentual_servico' => 0,
            'pagamentos' => [['forma' => 'pix', 'valor' => 30]],
        ], $this->operador);

        $online = $vendas->criarVendaOnlinePendente($this->unidade, $this->tipo, 2);
        $online->update(['status' => 'pago']);

        return [$pdv, Venda::findOrFail($atendimento->venda_id), $online];
    }

    protected function hospedagemFinalizada(float $valor): Hospedagem
    {
        $quarto = Quarto::create(['empresa_id' => $this->empresa->id, 'unidade_id' => $this->unidade->id, 'numero' => '101', 'capacidade_maxima' => 2, 'valor_diaria' => $valor, 'status' => 'ativo']);
        $hospedagem = Hospedagem::create([
            'empresa_id' => $this->empresa->id, 'unidade_id' => $this->unidade->id, 'quarto_id' => $quarto->id,
            'cliente_id' => Cliente::factory()->create(['empresa_id' => $this->empresa->id, 'unidade_id' => $this->unidade->id])->id,
            'quantidade_hospedes' => 2, 'quantidade_adultos' => 2, 'quantidade_criancas' => 0, 'quantidade_isentos' => 0,
            'valor_diaria' => $valor, 'data_checkin_prevista' => now()->toDateString(), 'data_checkout_prevista' => now()->addDay()->toDateString(),
            'data_checkin_real' => now(), 'status' => 'hospedado', 'registrado_por_id' => $this->operador->id,
        ]);

        return app(HospedagemService::class)->checkout($hospedagem, $this->caixa, $this->operador, 'pix');
    }
}
