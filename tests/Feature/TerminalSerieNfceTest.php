<?php

namespace Tests\Feature;

use App\Models\Caixa;
use App\Models\DocumentoFiscal;
use App\Models\Empresa;
use App\Models\Produto;
use App\Models\Terminal;
use App\Models\Unidade;
use App\Models\User;
use App\Services\Fiscal\NfceEmissaoService;
use App\Services\Fiscal\VendaFiscalService;
use App\Services\VendaService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Série de NFC-e por terminal: cada caixa com numeração própria; sem série
 * o terminal usa a série padrão da empresa.
 */
class TerminalSerieNfceTest extends TestCase
{
    use RefreshDatabase;

    protected Empresa $empresa;

    protected Unidade $unidade;

    protected User $operador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->empresa = Empresa::factory()->create(['numero_serie_nfce' => 1, 'ambiente_nfe' => 2, 'numero_ultima_nfce_homologacao' => 40]);
        $this->unidade = Unidade::factory()->create(['empresa_id' => $this->empresa->id]);
        $this->operador = User::factory()->create(['empresa_id' => $this->empresa->id, 'unidade_id' => $this->unidade->id]);
        Gate::before(fn () => true);
        $this->actingAs($this->operador);
    }

    public function test_cadastra_serie_do_terminal_e_impede_repetir(): void
    {
        $this->post(route('terminais.store'), $this->dados('Caixa 1', serie: 2, ultimo: 150))->assertRedirect(route('terminais.index'));
        $this->assertDatabaseHas('terminais', ['nome' => 'Caixa 1', 'serie_nfce' => 2, 'numero_ultima_nfce_homologacao' => 150]);

        // Mesma série em outro terminal: bloqueado.
        $this->post(route('terminais.store'), $this->dados('Caixa 2', serie: 2))->assertSessionHasErrors('serie_nfce');

        // Série igual à padrão da empresa: bloqueado.
        $this->post(route('terminais.store'), $this->dados('Caixa 3', serie: 1))->assertSessionHasErrors('serie_nfce');

        // Em branco: usa a série padrão da empresa.
        $this->post(route('terminais.store'), $this->dados('Caixa 4', serie: null))->assertRedirect(route('terminais.index'));
        $this->assertDatabaseHas('terminais', ['nome' => 'Caixa 4', 'serie_nfce' => null]);

        // Editar o próprio terminal mantendo a série é permitido.
        $caixa1 = Terminal::where('nome', 'Caixa 1')->firstOrFail();
        $this->put(route('terminais.update', $caixa1), $this->dados('Caixa 1 - Bar', serie: 2, ultimo: 150))->assertRedirect(route('terminais.index'));

        $this->get(route('terminais.index'))->assertOk()->assertSee('Série 2')->assertSee('Padrão da empresa (série 1)');
        $this->get(route('terminais.edit', $caixa1))->assertOk()->assertSee('NFC-e deste terminal');
    }

    public function test_numeracao_usa_a_serie_do_terminal_ou_a_da_empresa(): void
    {
        $comSerie = Terminal::create(['empresa_id' => $this->empresa->id, 'unidade_id' => $this->unidade->id, 'nome' => 'Bar', 'status' => 'ativo', 'serie_nfce' => 2, 'numero_ultima_nfce_homologacao' => 150]);
        $semSerie = Terminal::create(['empresa_id' => $this->empresa->id, 'unidade_id' => $this->unidade->id, 'nome' => 'Recepção', 'status' => 'ativo']);
        $numeracao = new \ReflectionMethod(NfceEmissaoService::class, 'numeracao');
        $servico = app(NfceEmissaoService::class);

        $doTerminal = $numeracao->invoke($servico, $this->empresa, $comSerie);
        $this->assertSame(2, $doTerminal['serie']);
        $this->assertSame(151, $doTerminal['numero']);
        $this->assertInstanceOf(Terminal::class, $doTerminal['dono']);

        $daEmpresa = $numeracao->invoke($servico, $this->empresa, $semSerie);
        $this->assertSame(1, $daEmpresa['serie']);
        $this->assertSame(41, $daEmpresa['numero']);
        $this->assertInstanceOf(Empresa::class, $daEmpresa['dono']);

        $this->assertSame(41, $numeracao->invoke($servico, $this->empresa, null)['numero'], 'venda sem caixa/terminal usa a série padrão');

        // Produção usa o contador de produção do terminal.
        $this->empresa->update(['ambiente_nfe' => 1]);
        $comSerie->update(['numero_ultima_nfce_producao' => 7]);
        $this->assertSame(8, $numeracao->invoke($servico, $this->empresa->fresh(), $comSerie)['numero']);
    }

    public function test_nfce_da_venda_usa_o_terminal_do_caixa(): void
    {
        $terminal = Terminal::create(['empresa_id' => $this->empresa->id, 'unidade_id' => $this->unidade->id, 'nome' => 'Bar', 'status' => 'ativo', 'serie_nfce' => 3]);
        $caixa = Caixa::create([
            'empresa_id' => $this->empresa->id, 'unidade_id' => $this->unidade->id, 'terminal_id' => $terminal->id,
            'usuario_abertura_id' => $this->operador->id, 'data_abertura' => now(), 'valor_abertura' => 0, 'status' => 'aberto',
        ]);
        $produto = Produto::create([
            'empresa_id' => $this->empresa->id, 'unidade_id' => $this->unidade->id, 'nome' => 'Suco', 'tipo_item' => 'produto',
            'preco_custo' => 1, 'preco_venda' => 6, 'controla_estoque' => false, 'ativo' => true,
        ]);
        $this->empresa->update(['csc' => 'CSC', 'csc_id' => '000001', 'certificado_arquivo' => 'pfx']);

        $emissor = new class extends NfceEmissaoService
        {
            public ?Terminal $terminalRecebido = null;

            public function __construct() {}

            public function emitir($empresa, $unidade, $cliente, $formaPagamento, DocumentoFiscal $documento, array $itens, $terminal = null): DocumentoFiscal
            {
                $this->terminalRecebido = $terminal;
                $documento->update(['status' => DocumentoFiscal::STATUS_AUTORIZADO, 'numero' => 1, 'serie' => $terminal?->serie_nfce]);

                return $documento->fresh();
            }
        };
        $this->app->instance(NfceEmissaoService::class, $emissor);

        $venda = app(VendaService::class)->criar(['forma_pagamento' => 'dinheiro', 'itens' => [['produto_id' => $produto->id, 'quantidade' => 1]]], $this->operador, $caixa);
        $resultado = app(VendaFiscalService::class)->emitirAposVenda($venda, $this->operador);

        $this->assertTrue($resultado['ok'], $resultado['mensagem']);
        $this->assertSame($terminal->id, $emissor->terminalRecebido?->id);
        $this->assertDatabaseHas('documentos_fiscais', ['venda_id' => $venda->id, 'serie' => 3]);
    }

    protected function dados(string $nome, ?int $serie, int $ultimo = 0): array
    {
        return [
            'nome' => $nome, 'unidade_id' => $this->unidade->id, 'status' => 'ativo',
            'serie_nfce' => $serie, 'numero_ultima_nfce_producao' => 0, 'numero_ultima_nfce_homologacao' => $ultimo,
        ];
    }
}
