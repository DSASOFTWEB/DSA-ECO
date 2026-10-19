<?php

namespace Tests\Feature;

use App\Models\Caixa;
use App\Models\Cliente;
use App\Models\CobrancaPix;
use App\Models\DocumentoFiscal;
use App\Models\Empresa;
use App\Models\FormaPagamento;
use App\Models\PontoAtendimento;
use App\Models\Produto;
use App\Models\TipoEntrada;
use App\Models\Unidade;
use App\Models\User;
use App\Models\Venda;
use App\Services\Fiscal\NfceEmissaoService;
use App\Services\Fiscal\VendaFiscalService;
use App\Services\Food\AtendimentoService;
use App\Services\Integrations\Pix\GatewayPix;
use App\Services\Integrations\Pix\GatewayPixResolver;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\XmlNfceExemplo;
use Tests\TestCase;

/**
 * "F4 emitir NFC-e / F2 apenas pedido" na finalização do PDV e da mesa.
 * A SEFAZ é simulada: o fake registra o que receberia e autoriza.
 */
class VendaNfceTest extends TestCase
{
    use RefreshDatabase;
    use XmlNfceExemplo;

    protected object $emissor;

    protected Empresa $empresa;

    protected Unidade $unidade;

    protected User $operador;

    protected Caixa $caixa;

    protected Produto $produto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->emissor = new class extends NfceEmissaoService
        {
            public array $chamadas = [];

            public bool $rejeitar = false;

            public string $xml = '';

            public function __construct() {}

            public function emitir($empresa, $unidade, $cliente, $formaPagamento, DocumentoFiscal $documento, array $itens): DocumentoFiscal
            {
                if ($this->rejeitar) {
                    $documento->update(['status' => DocumentoFiscal::STATUS_REJEITADO, 'mensagem_erro' => 'cStat 999: Rejeição de teste']);
                    throw new \App\Exceptions\IntegrationException('cStat 999: Rejeição de teste', servico: 'nfce');
                }

                $this->chamadas[] = compact('cliente', 'formaPagamento', 'itens');
                $documento->update(['status' => DocumentoFiscal::STATUS_AUTORIZADO, 'numero' => 900 + count($this->chamadas), 'serie' => 1, 'xml_protocolado' => $this->xml]);

                return $documento->fresh();
            }
        };
        $this->emissor->xml = $this->xmlNfceAutorizada();
        $this->app->instance(NfceEmissaoService::class, $this->emissor);

        $this->empresa = Empresa::factory()->create(['csc' => 'CSC123', 'csc_id' => '000001', 'certificado_arquivo' => 'pfx-de-teste']);
        $this->unidade = Unidade::factory()->create(['empresa_id' => $this->empresa->id]);
        $this->operador = User::factory()->create(['empresa_id' => $this->empresa->id, 'unidade_id' => $this->unidade->id]);
        Gate::before(fn () => true);
        $this->actingAs($this->operador);

        $this->caixa = Caixa::create([
            'empresa_id' => $this->empresa->id, 'unidade_id' => $this->unidade->id, 'usuario_abertura_id' => $this->operador->id,
            'data_abertura' => now(), 'valor_abertura' => 0, 'status' => 'aberto',
        ]);
        $this->produto = Produto::create([
            'empresa_id' => $this->empresa->id, 'unidade_id' => $this->unidade->id, 'nome' => 'Refrigerante', 'tipo_item' => 'produto',
            'ncm' => '22021000', 'preco_custo' => 2, 'preco_venda' => 8, 'controla_estoque' => false, 'ativo' => true,
        ]);
    }

    public function test_pdv_com_f4_emite_nfce_e_mostra_no_comprovante(): void
    {
        $resposta = $this->post(route('vendas.store'), $this->carrinho(emitir: true, desconto: 1));

        $venda = Venda::latest('id')->firstOrFail();
        $documento = DocumentoFiscal::where('venda_id', $venda->id)->firstOrFail();
        $resposta->assertRedirect(route('fiscal.danfce', $documento))
            ->assertSessionHas('fiscal', fn ($f) => $f['ok'] && str_contains($f['mensagem'], 'NFC-e nº 901'));

        $this->assertDatabaseHas('documentos_fiscais', ['venda_id' => $venda->id, 'modelo' => DocumentoFiscal::MODELO_NFCE, 'status' => DocumentoFiscal::STATUS_AUTORIZADO, 'origem' => 'venda_pdv']);
        $item = $this->emissor->chamadas[0]['itens'][0];
        $this->assertSame('Refrigerante', $item['descricao']);
        $this->assertEquals(2, $item['quantidade']);
        $this->assertEquals(1, $item['desconto']);
        $this->assertSame('dinheiro', $this->emissor->chamadas[0]['formaPagamento']);

        $this->get(route('fiscal.danfce', $documento))->assertOk()->assertSee('REFRIGERANTE LATA');
        $this->get(route('vendas.comprovante', $venda))->assertOk()->assertSee('Imprimir NFC-e');
        $this->get(route('vendas.show', $venda))->assertOk()->assertSee('Imprimir NFC-e');
    }

    public function test_pdv_com_f2_apenas_pedido_nao_emite(): void
    {
        $this->post(route('vendas.store'), $this->carrinho(emitir: false))->assertRedirect()->assertSessionMissing('fiscal');

        $this->assertDatabaseCount('documentos_fiscais', 0);
        $this->assertSame([], $this->emissor->chamadas);
    }

    public function test_ingresso_nao_entra_na_nfce(): void
    {
        $tipo = TipoEntrada::create(['empresa_id' => $this->empresa->id, 'nome' => 'Adulto', 'valor' => 50, 'eh_plano' => false, 'ativo' => true]);

        $this->post(route('vendas.store'), [
            'caixa_id' => $this->caixa->id, 'forma_pagamento' => 'dinheiro', 'emitir_nfce' => 1,
            'itens' => [['tipo_entrada_id' => $tipo->id, 'quantidade' => 1], ['produto_id' => $this->produto->id, 'quantidade' => 1]],
        ])->assertSessionHas('fiscal', fn ($f) => $f['ok']);

        $itens = $this->emissor->chamadas[0]['itens'];
        $this->assertCount(1, $itens);
        $this->assertSame('Refrigerante', $itens[0]['descricao']);

        // Só ingresso: não há o que emitir — a venda fica registrada e o operador é avisado.
        $this->post(route('vendas.store'), [
            'caixa_id' => $this->caixa->id, 'forma_pagamento' => 'dinheiro', 'emitir_nfce' => 1,
            'itens' => [['tipo_entrada_id' => $tipo->id, 'quantidade' => 1]],
        ])->assertSessionHas('fiscal', fn ($f) => ! $f['ok'] && str_contains($f['mensagem'], 'não tem produtos'));
        $this->assertSame(2, Venda::where('status', 'pago')->count());
    }

    public function test_rejeicao_da_sefaz_nao_desfaz_a_venda(): void
    {
        $this->emissor->rejeitar = true;

        $this->post(route('vendas.store'), $this->carrinho(emitir: true))
            ->assertSessionHas('fiscal', fn ($f) => ! $f['ok'] && str_contains($f['mensagem'], 'Rejeição de teste'));

        $this->assertSame(1, Venda::where('status', 'pago')->count());
    }

    public function test_mesa_com_f4_emite_nfce_rateando_o_desconto_da_conta(): void
    {
        $mesa = PontoAtendimento::create(['empresa_id' => $this->empresa->id, 'unidade_id' => $this->unidade->id, 'tipo' => 'mesa', 'numero' => 3, 'status' => 'livre']);
        $food = app(AtendimentoService::class);
        $atendimento = $food->abrir($mesa, $this->operador);
        $food->adicionarItem($atendimento, ['produto_id' => $this->produto->id, 'quantidade' => 3], $this->operador); // 24,00
        $dinheiro = FormaPagamento::create(['empresa_id' => $this->empresa->id, 'nome' => 'Dinheiro', 'tipo' => 'dinheiro', 'ativo' => true]);

        $this->get(route('food.atendimentos.show', $atendimento))->assertOk()->assertSee('Emitir NFC-e')->assertSee('perguntarNfce: true', false);

        // 24 + 10% serviço (2,40) − 4,00 de desconto = 22,40 cobrado
        $this->post(route('food.atendimentos.fechar', $atendimento), [
            'caixa_id' => $this->caixa->id, 'desconto' => 4, 'percentual_servico' => 10, 'emitir_nfce' => 1,
            'pagamentos' => [['forma_pagamento_id' => $dinheiro->id, 'valor' => 22.40]],
        ])->assertRedirect(route('fiscal.danfce', DocumentoFiscal::where('origem', 'venda_mesa')->value('id') ?? 0))->assertSessionHas('fiscal', fn ($f) => $f['ok']);

        $item = $this->emissor->chamadas[0]['itens'][0];
        $this->assertEquals(24, $item['quantidade'] * $item['valor_unitario']);
        $this->assertEquals(4, $item['desconto'], 'desconto da conta rateado nos produtos');
        $this->assertDatabaseHas('documentos_fiscais', ['origem' => 'venda_mesa', 'status' => DocumentoFiscal::STATUS_AUTORIZADO]);
    }

    public function test_nao_pergunta_quando_a_empresa_nao_emite_nfce(): void
    {
        $this->empresa->update(['csc' => null]);

        $this->get(route('vendas.create', ['caixa_id' => $this->caixa->id]))->assertOk()->assertSee('nfceHabilitada: false', false);

        $mesa = PontoAtendimento::create(['empresa_id' => $this->empresa->id, 'unidade_id' => $this->unidade->id, 'tipo' => 'mesa', 'numero' => 4, 'status' => 'livre']);
        $atendimento = app(AtendimentoService::class)->abrir($mesa, $this->operador);
        app(AtendimentoService::class)->adicionarItem($atendimento, ['produto_id' => $this->produto->id, 'quantidade' => 1], $this->operador);
        $this->get(route('food.atendimentos.show', $atendimento))->assertSee('perguntarNfce: false', false);
    }

    public function test_pix_integrado_emite_nfce_depois_que_o_banco_confirma(): void
    {
        $gateway = new class implements GatewayPix
        {
            public string $status = self::STATUS_PENDENTE;

            public function nome(): string { return 'fake'; }

            public function criarCobranca(float $valor, string $descricao): array
            {
                return ['txid' => 'TX'.uniqid(), 'copia_e_cola' => '000201PIX', 'expiracao_segundos' => 600, 'payload' => []];
            }

            public function consultarCobranca(string $txid): array
            {
                return ['status' => $this->status, 'e2eid' => 'E2E1', 'payload' => []];
            }

            public function cancelarCobranca(string $txid): void {}
        };
        $this->app->instance(GatewayPixResolver::class, new class($gateway) extends GatewayPixResolver
        {
            public function __construct(private GatewayPix $fake) {}

            public function paraEmpresa(Empresa $empresa): ?GatewayPix { return $this->fake; }

            public function porNome(Empresa $empresa, string $gateway): ?GatewayPix { return $this->fake; }
        });

        $id = $this->postJson(route('vendas.pix.store'), $this->carrinho(emitir: true) + ['forma_pagamento' => 'pix'])->assertCreated()->json('id');
        $this->assertSame([], $this->emissor->chamadas, 'nada é emitido antes do pagamento');

        $gateway->status = GatewayPix::STATUS_PAGA;
        $status = $this->getJson(route('vendas.pix.status', $id))->assertJsonPath('status', 'paga')->assertSessionHas('fiscal', fn ($f) => $f['ok']);
        $status->assertJsonPath('redirect', route('fiscal.danfce', DocumentoFiscal::latest('id')->value('id')));

        $venda = Venda::findOrFail(CobrancaPix::findOrFail($id)->venda_id);
        $this->assertSame('pix', $this->emissor->chamadas[0]['formaPagamento']);
        $this->assertDatabaseHas('documentos_fiscais', ['venda_id' => $venda->id, 'status' => DocumentoFiscal::STATUS_AUTORIZADO]);

        // Polls seguintes não emitem de novo.
        $this->getJson(route('vendas.pix.status', $id))->assertOk();
        $this->assertCount(1, $this->emissor->chamadas);
    }

    public function test_nao_emite_duas_vezes_a_mesma_venda(): void
    {
        $this->post(route('vendas.store'), $this->carrinho(emitir: true));
        $venda = Venda::latest('id')->firstOrFail();

        $resultado = app(VendaFiscalService::class)->emitirAposVenda($venda, $this->operador);

        $this->assertFalse($resultado['ok']);
        $this->assertStringContainsString('já tem NFC-e autorizada', $resultado['mensagem']);
    }

    protected function carrinho(bool $emitir, float $desconto = 0): array
    {
        return [
            'caixa_id' => $this->caixa->id,
            'cliente_id' => Cliente::factory()->create(['empresa_id' => $this->empresa->id, 'unidade_id' => $this->unidade->id])->id,
            'forma_pagamento' => 'dinheiro',
            'emitir_nfce' => $emitir ? 1 : 0,
            'itens' => [['produto_id' => $this->produto->id, 'quantidade' => 2, 'desconto' => $desconto]],
        ];
    }
}
