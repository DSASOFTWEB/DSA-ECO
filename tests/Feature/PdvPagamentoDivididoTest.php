<?php

namespace Tests\Feature;

use App\Models\Caixa;
use App\Models\CaixaMovimentacao;
use App\Models\CobrancaPix;
use App\Models\DocumentoFiscal;
use App\Models\Empresa;
use App\Models\FormaPagamento;
use App\Models\Pagamento;
use App\Models\Produto;
use App\Models\TipoEntrada;
use App\Models\Unidade;
use App\Models\User;
use App\Models\Venda;
use App\Services\Fiscal\NfceEmissaoService;
use App\Services\Integrations\Pix\GatewayPix;
use App\Services\Integrations\Pix\GatewayPixResolver;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\XmlNfceExemplo;
use Tests\TestCase;

/**
 * PDV com mais de uma forma de pagamento na mesma venda
 * (ex.: parte em dinheiro, parte no cartão, parte no Pix).
 */
class PdvPagamentoDivididoTest extends TestCase
{
    use RefreshDatabase;
    use XmlNfceExemplo;

    protected object $emissor;

    protected Empresa $empresa;

    protected Unidade $unidade;

    protected User $operador;

    protected Caixa $caixa;

    protected Produto $produto;

    protected FormaPagamento $dinheiro;

    protected FormaPagamento $credito;

    protected FormaPagamento $pixQr;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->emissor = new class extends NfceEmissaoService
        {
            public array $chamadas = [];

            public string $xml = '';

            public function __construct() {}

            public function emitir($empresa, $unidade, $cliente, $formaPagamento, DocumentoFiscal $documento, array $itens, $terminal = null): DocumentoFiscal
            {
                $this->chamadas[] = compact('formaPagamento', 'itens');
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
            'ncm' => '22021000', 'preco_custo' => 2, 'preco_venda' => 10, 'controla_estoque' => false, 'ativo' => true,
        ]);

        $this->dinheiro = FormaPagamento::create(['empresa_id' => $this->empresa->id, 'nome' => 'Dinheiro', 'tipo' => 'dinheiro', 'ativo' => true]);
        $this->credito = FormaPagamento::create(['empresa_id' => $this->empresa->id, 'nome' => 'Crédito', 'tipo' => 'cartao_credito', 'ativo' => true]);
        $this->pixQr = FormaPagamento::create(['empresa_id' => $this->empresa->id, 'nome' => 'Pix QR', 'tipo' => 'pix', 'integrado' => true, 'ativo' => true]);
    }

    public function test_tela_do_pdv_tem_dividir_pagamento(): void
    {
        $this->get(route('vendas.create', ['caixa_id' => $this->caixa->id]))
            ->assertOk()
            ->assertSee('Dividir pagamento')
            ->assertSee('adicionarParcela(', false);
    }

    public function test_dinheiro_e_cartao_com_troco_no_dinheiro(): void
    {
        // Venda de 30,00: 20,00 no crédito + 50,00 em dinheiro → troco 40,00.
        $this->post(route('vendas.store'), $this->venda([
            ['forma_pagamento_id' => $this->credito->id, 'valor' => 20],
            ['forma_pagamento_id' => $this->dinheiro->id, 'valor' => 50],
        ]))->assertRedirect()->assertSessionHas('sucesso');

        $venda = Venda::latest('id')->firstOrFail();
        $this->assertSame('multiplo', $venda->forma_pagamento);
        $this->assertEquals(30, $venda->valor_total);
        $this->assertStringContainsString('Troco: R$ 40,00', $venda->observacao);

        $pagamentos = Pagamento::where('venda_id', $venda->id)->orderBy('id')->get();
        $this->assertSame(['cartao_credito', 'dinheiro'], $pagamentos->pluck('metodo_pagamento')->all());
        $this->assertEquals([20, 10], $pagamentos->pluck('valor')->map(fn ($v) => (float) $v)->all(), 'dinheiro entra líquido (sem o troco)');

        $movs = CaixaMovimentacao::where('caixa_id', $this->caixa->id)->orderBy('id')->get();
        $this->assertSame(['cartao_credito', 'dinheiro'], $movs->pluck('forma_pagamento')->all());
        $this->assertEquals(30, $movs->sum('valor'));
    }

    public function test_comprovante_mostra_cada_forma_e_o_troco(): void
    {
        $this->post(route('vendas.store'), $this->venda([
            ['forma_pagamento_id' => $this->credito->id, 'valor' => 20],
            ['forma_pagamento_id' => $this->dinheiro->id, 'valor' => 50],
        ]));
        $venda = Venda::latest('id')->firstOrFail();

        $this->get(route('vendas.comprovante', $venda))->assertOk()
            ->assertSeeInOrder(['PAGAMENTO', 'Cartão de crédito', 'R$ 20,00', 'Dinheiro', 'R$ 50,00', 'Troco', 'R$ 40,00'])
            ->assertDontSee('Multiplo')
            ->assertDontSee('OBSERVACOES');

        $bytes = $this->get(route('vendas.comprovante.escpos', $venda))->assertOk()->getContent();
        $texto = iconv('CP850', 'UTF-8', $bytes);
        $this->assertMatchesRegularExpression('/Cartão de crédito\s+R\$ 20,00/u', $texto);
        $this->assertMatchesRegularExpression('/Dinheiro\s+R\$ 50,00/u', $texto);
        $this->assertMatchesRegularExpression('/Troco\s+R\$ 40,00/u', $texto);
    }

    public function test_tres_formas_iguais_ao_total(): void
    {
        $manual = FormaPagamento::create(['empresa_id' => $this->empresa->id, 'nome' => 'Pix chave', 'tipo' => 'pix', 'ativo' => true]);

        $this->post(route('vendas.store'), $this->venda([
            ['forma_pagamento_id' => $this->dinheiro->id, 'valor' => 5],
            ['forma_pagamento_id' => $this->credito->id, 'valor' => 15],
            ['forma_pagamento_id' => $manual->id, 'valor' => 10],
        ]))->assertSessionHas('sucesso');

        $venda = Venda::latest('id')->firstOrFail();
        $this->assertSame(3, Pagamento::where('venda_id', $venda->id)->count());
        $this->assertDatabaseHas('pagamentos', ['venda_id' => $venda->id, 'metodo_pagamento' => 'pix', 'gateway' => 'pix_manual', 'valor' => 10]);
    }

    public function test_mesmo_tipo_dividido_grava_o_tipo_e_nao_multiplo(): void
    {
        $this->post(route('vendas.store'), $this->venda([
            ['forma_pagamento_id' => $this->credito->id, 'valor' => 10],
            ['forma_pagamento_id' => $this->credito->id, 'valor' => 20],
        ]))->assertSessionHas('sucesso');

        $venda = Venda::latest('id')->firstOrFail();
        $this->assertSame('cartao_credito', $venda->forma_pagamento);
        $this->assertSame(2, Pagamento::where('venda_id', $venda->id)->count());
    }

    public function test_recusa_quando_falta_valor(): void
    {
        $this->from(route('vendas.create'))->post(route('vendas.store'), $this->venda([
            ['forma_pagamento_id' => $this->dinheiro->id, 'valor' => 10],
            ['forma_pagamento_id' => $this->credito->id, 'valor' => 15],
        ]))->assertSessionHas('erro', fn ($e) => str_contains($e, 'Faltam R$ 5,00'));

        $this->assertDatabaseCount('vendas', 0);
        $this->assertDatabaseCount('caixa_movimentacoes', 0);
    }

    public function test_cartao_nao_pode_passar_do_total(): void
    {
        $this->post(route('vendas.store'), $this->venda([
            ['forma_pagamento_id' => $this->dinheiro->id, 'valor' => 5],
            ['forma_pagamento_id' => $this->credito->id, 'valor' => 40],
        ]))->assertSessionHas('erro', fn ($e) => str_contains($e, 'Só o dinheiro'));

        $this->assertDatabaseCount('vendas', 0);
    }

    public function test_forma_de_outra_empresa_e_dois_pix_qr_sao_recusados(): void
    {
        $outra = FormaPagamento::create(['empresa_id' => Empresa::factory()->create()->id, 'nome' => 'X', 'tipo' => 'dinheiro', 'ativo' => true]);

        $this->post(route('vendas.store'), $this->venda([
            ['forma_pagamento_id' => $outra->id, 'valor' => 30],
        ]))->assertSessionHasErrors('pagamentos.0.forma_pagamento_id');

        $this->post(route('vendas.store'), $this->venda([
            ['forma_pagamento_id' => $this->pixQr->id, 'valor' => 15],
            ['forma_pagamento_id' => $this->pixQr->id, 'valor' => 15],
        ]))->assertSessionHasErrors('pagamentos');

        $this->assertDatabaseCount('vendas', 0);
    }

    public function test_sem_divisao_continua_igual(): void
    {
        $this->post(route('vendas.store'), [
            'caixa_id' => $this->caixa->id, 'forma_pagamento_id' => $this->credito->id, 'forma_pagamento' => 'dinheiro',
            'itens' => [['produto_id' => $this->produto->id, 'quantidade' => 3]],
        ])->assertSessionHas('sucesso');

        $venda = Venda::latest('id')->firstOrFail();
        $this->assertSame('cartao_credito', $venda->forma_pagamento);
        $this->assertDatabaseHas('pagamentos', ['venda_id' => $venda->id, 'valor' => 30, 'metodo_pagamento' => 'cartao_credito']);
    }

    public function test_nfce_sai_com_uma_forma_por_parcela_proporcional_a_nota(): void
    {
        // Ingresso de 20 (fora da nota) + 30 de produto = 50; pago 25 dinheiro + 25 crédito.
        $tipo = TipoEntrada::create(['empresa_id' => $this->empresa->id, 'nome' => 'Adulto', 'valor' => 20, 'eh_plano' => false, 'ativo' => true]);

        $dados = $this->venda([
            ['forma_pagamento_id' => $this->dinheiro->id, 'valor' => 25],
            ['forma_pagamento_id' => $this->credito->id, 'valor' => 25],
        ]);
        $dados['emitir_nfce'] = 1;
        $dados['itens'][] = ['tipo_entrada_id' => $tipo->id, 'quantidade' => 1];

        $this->post(route('vendas.store'), $dados)->assertSessionHas('fiscal', fn ($f) => $f['ok']);

        $forma = $this->emissor->chamadas[0]['formaPagamento'];
        $this->assertIsArray($forma);
        $this->assertEqualsCanonicalizing(['dinheiro', 'cartao_credito'], array_column($forma, 'forma'));

        // Na nota (30,00) as formas são escaladas e somam exatamente o vNF.
        $det = NfceEmissaoService::detalhesPagamento($forma, 30.0);
        $this->assertEquals([['forma' => 'dinheiro', 'valor' => 15.0], ['forma' => 'cartao_credito', 'valor' => 15.0]], $det);
    }

    public function test_detalhes_pagamento_arredonda_na_ultima_forma(): void
    {
        $det = NfceEmissaoService::detalhesPagamento([
            ['forma' => 'dinheiro', 'valor' => 10],
            ['forma' => 'cartao_debito', 'valor' => 10],
            ['forma' => 'pix', 'valor' => 10],
        ], 10.0);

        $this->assertEquals([3.33, 3.33, 3.34], array_column($det, 'valor'));
        $this->assertSame([['forma' => 'pix', 'valor' => 10.0]], NfceEmissaoService::detalhesPagamento('pix', 10.0));
    }

    public function test_pix_qr_cobra_so_a_parcela_e_registra_a_venda_ao_confirmar(): void
    {
        $gateway = new class implements GatewayPix
        {
            public string $status = self::STATUS_PENDENTE;

            public array $valores = [];

            public function nome(): string { return 'fake'; }

            public function criarCobranca(float $valor, string $descricao): array
            {
                $this->valores[] = $valor;

                return ['txid' => 'TX'.uniqid(), 'copia_e_cola' => '000201PIX', 'expiracao_segundos' => 600, 'payload' => []];
            }

            public function consultarCobranca(string $txid): array
            {
                return ['status' => $this->status, 'e2eid' => 'E2E9', 'payload' => []];
            }

            public function cancelarCobranca(string $txid): void {}
        };
        $this->app->instance(GatewayPixResolver::class, new class($gateway) extends GatewayPixResolver
        {
            public function __construct(private GatewayPix $fake) {}

            public function paraEmpresa(Empresa $empresa): ?GatewayPix { return $this->fake; }

            public function porNome(Empresa $empresa, string $gateway): ?GatewayPix { return $this->fake; }
        });

        $id = $this->postJson(route('vendas.pix.store'), $this->venda([
            ['forma_pagamento_id' => $this->dinheiro->id, 'valor' => 12],
            ['forma_pagamento_id' => $this->pixQr->id, 'valor' => 18],
        ]))->assertCreated()->json('id');

        $this->assertSame([18.0], $gateway->valores, 'o QR cobra só a parte em Pix');
        $this->assertDatabaseCount('vendas', 0);

        $gateway->status = GatewayPix::STATUS_PAGA;
        $this->getJson(route('vendas.pix.status', $id))->assertJsonPath('status', 'paga');

        $venda = Venda::findOrFail(CobrancaPix::findOrFail($id)->venda_id);
        $this->assertSame('multiplo', $venda->forma_pagamento);
        $this->assertDatabaseHas('pagamentos', ['venda_id' => $venda->id, 'metodo_pagamento' => 'pix', 'gateway' => 'fake', 'gateway_payment_id' => 'E2E9', 'valor' => 18]);
        $this->assertDatabaseHas('pagamentos', ['venda_id' => $venda->id, 'metodo_pagamento' => 'dinheiro', 'gateway' => 'dinheiro', 'gateway_payment_id' => null, 'valor' => 12]);
    }

    protected function venda(array $pagamentos): array
    {
        return [
            'caixa_id' => $this->caixa->id,
            'forma_pagamento_id' => $pagamentos[0]['forma_pagamento_id'],
            'forma_pagamento' => 'dinheiro',
            'pagamentos' => $pagamentos,
            'itens' => [['produto_id' => $this->produto->id, 'quantidade' => 3]],
        ];
    }
}
