<?php

namespace Tests\Feature;

use App\Models\Atendimento;
use App\Models\Caixa;
use App\Models\CobrancaPix;
use App\Models\Empresa;
use App\Models\FormaPagamento;
use App\Models\PontoAtendimento;
use App\Models\Produto;
use App\Models\Unidade;
use App\Models\User;
use App\Services\Food\AtendimentoService;
use App\Services\Integrations\Pix\GatewayPix;
use App\Services\Integrations\Pix\GatewayPixResolver;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class FormaPagamentoTest extends TestCase
{
    use RefreshDatabase;

    protected GatewayPix $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->gateway = new class implements GatewayPix
        {
            public string $statusAtual = self::STATUS_PENDENTE;

            public ?float $ultimoValor = null;

            public int $criadas = 0;

            public function nome(): string
            {
                return 'fake';
            }

            public function criarCobranca(float $valor, string $descricao): array
            {
                $this->ultimoValor = $valor;
                $this->criadas++;

                return [
                    'txid' => 'TX'.$this->criadas.uniqid(),
                    'copia_e_cola' => '00020126580014br.gov.bcb.pix0136teste5204000053039865802BR6304ABCD',
                    'expiracao_segundos' => 600,
                    'payload' => ['status' => 'ATIVA'],
                ];
            }

            public function consultarCobranca(string $txid): array
            {
                return [
                    'status' => $this->statusAtual,
                    'e2eid' => $this->statusAtual === self::STATUS_PAGA ? 'E2E999' : null,
                    'payload' => ['status' => $this->statusAtual],
                ];
            }

            public function cancelarCobranca(string $txid): void {}
        };

        $this->app->instance(GatewayPixResolver::class, new class($this->gateway) extends GatewayPixResolver
        {
            public function __construct(private GatewayPix $fake) {}

            public function paraEmpresa(Empresa $empresa): ?GatewayPix
            {
                return $this->fake;
            }

            public function porNome(Empresa $empresa, string $gateway): ?GatewayPix
            {
                return $this->fake;
            }
        });
    }

    public function test_cria_formas_padrao_sem_pix_integrado_quando_nao_ha_gateway(): void
    {
        [$empresa] = $this->cenario();

        $formas = FormaPagamento::ativasDaEmpresa($empresa->id);

        $this->assertSame(['dinheiro', 'pix', 'cartao_credito', 'cartao_debito'], $formas->pluck('tipo')->all());
        $this->assertFalse($formas->contains(fn ($f) => $f->integrado));

        // Chamar de novo não duplica.
        FormaPagamento::ativasDaEmpresa($empresa->id);
        $this->assertSame(4, FormaPagamento::withoutGlobalScopes()->where('empresa_id', $empresa->id)->count());
    }

    public function test_nao_permite_pix_integrado_sem_gateway_configurado(): void
    {
        [, $operador] = $this->cenario();

        $this->actingAs($operador)
            ->post(route('formas-pagamento.store'), ['nome' => 'Pix QR', 'tipo' => 'pix', 'integrado' => '1', 'ativo' => '1'])
            ->assertSessionHasErrors('integrado');

        $this->actingAs($operador)
            ->post(route('formas-pagamento.store'), ['nome' => 'Pix chave', 'tipo' => 'pix', 'ativo' => '1'])
            ->assertRedirect(route('formas-pagamento.index'));

        $this->assertDatabaseHas('formas_pagamento', ['nome' => 'Pix chave', 'tipo' => 'pix', 'integrado' => false, 'ativo' => true]);
    }

    public function test_integrado_e_ignorado_para_tipo_que_nao_e_pix(): void
    {
        [, $operador] = $this->cenario();

        $this->actingAs($operador)
            ->post(route('formas-pagamento.store'), ['nome' => 'Débito', 'tipo' => 'cartao_debito', 'integrado' => '1', 'ativo' => '1'])
            ->assertRedirect(route('formas-pagamento.index'));

        $this->assertDatabaseHas('formas_pagamento', ['nome' => 'Débito', 'integrado' => false]);
    }

    public function test_fecha_mesa_com_forma_cadastrada_gravando_o_tipo(): void
    {
        [$empresa, $operador, $caixa, $atendimento] = $this->cenarioMesa();
        $credito = FormaPagamento::create(['empresa_id' => $empresa->id, 'nome' => 'Visa crédito', 'tipo' => 'cartao_credito', 'ativo' => true]);

        $this->actingAs($operador)
            ->post(route('food.atendimentos.fechar', $atendimento), [
                'caixa_id' => $caixa->id,
                'percentual_servico' => 0,
                'pagamentos' => [['forma_pagamento_id' => $credito->id, 'valor' => 30]],
            ])
            ->assertRedirect();

        $atendimento->refresh();
        $this->assertSame('fechado', $atendimento->status);
        $this->assertDatabaseHas('vendas', ['id' => $atendimento->venda_id, 'forma_pagamento' => 'cartao_credito']);
        $this->assertDatabaseHas('pagamentos', ['venda_id' => $atendimento->venda_id, 'metodo_pagamento' => 'cartao_credito']);
    }

    public function test_pix_manual_na_mesa_nao_grava_gateway_mercadopago(): void
    {
        [$empresa, $operador, $caixa, $atendimento] = $this->cenarioMesa();
        $pix = FormaPagamento::create(['empresa_id' => $empresa->id, 'nome' => 'Pix chave', 'tipo' => 'pix', 'integrado' => false, 'ativo' => true]);

        $this->actingAs($operador)
            ->post(route('food.atendimentos.fechar', $atendimento), [
                'caixa_id' => $caixa->id,
                'percentual_servico' => 0,
                'pagamentos' => [['forma_pagamento_id' => $pix->id, 'valor' => 30]],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('pagamentos', ['venda_id' => $atendimento->fresh()->venda_id, 'gateway' => 'pix_manual', 'metodo_pagamento' => 'pix']);
        $this->assertSame(0, $this->gateway->criadas);
    }

    public function test_pix_integrado_na_mesa_so_fecha_quando_o_banco_confirma(): void
    {
        [$empresa, $operador, $caixa, $atendimento, $produto] = $this->cenarioMesa();
        $pixQr = FormaPagamento::create(['empresa_id' => $empresa->id, 'nome' => 'Pix QR', 'tipo' => 'pix', 'integrado' => true, 'ativo' => true]);
        $dados = [
            'caixa_id' => $caixa->id,
            'percentual_servico' => 10,
            'pagamentos' => [['forma_pagamento_id' => $pixQr->id, 'valor' => 33]],
        ];

        // Pelo POST normal não fecha: Pix integrado exige o QR Code.
        $this->actingAs($operador)
            ->post(route('food.atendimentos.fechar', $atendimento), $dados)
            ->assertSessionHas('erro');
        $this->assertSame('aberto', $atendimento->fresh()->status);

        $resposta = $this->postJson(route('food.atendimentos.pix', $atendimento), $dados)
            ->assertCreated()
            ->assertJsonPath('status', 'pendente')
            ->assertJsonStructure(['qr_code', 'copia_e_cola', 'status_url']);

        $this->assertSame(33.0, $this->gateway->ultimoValor);
        $cobranca = CobrancaPix::findOrFail($resposta->json('id'));
        $this->assertSame($atendimento->id, $cobranca->atendimento_id);

        // Reabrir o modal com o mesmo valor reaproveita o QR pendente.
        $this->postJson(route('food.atendimentos.pix', $atendimento), $dados)
            ->assertCreated()
            ->assertJsonPath('id', $cobranca->id);
        $this->assertSame(1, $this->gateway->criadas);

        $this->getJson(route('vendas.pix.status', $cobranca))->assertJsonPath('status', 'pendente');
        $this->assertSame('aberto', $atendimento->fresh()->status);

        $this->gateway->statusAtual = GatewayPix::STATUS_PAGA;
        $resposta = $this->getJson(route('vendas.pix.status', $cobranca))->assertJsonPath('status', 'paga');

        $atendimento->refresh();
        $this->assertSame('fechado', $atendimento->status);
        $this->assertSame($atendimento->venda_id, $cobranca->fresh()->venda_id);
        $resposta->assertJsonPath('redirect', route('vendas.show', $atendimento->venda_id));
        $this->assertSame(9, $produto->fresh()->estoque_atual);
        $this->assertDatabaseHas('vendas', ['id' => $atendimento->venda_id, 'forma_pagamento' => 'pix', 'valor_total' => 33]);
        $this->assertDatabaseHas('pagamentos', ['venda_id' => $atendimento->venda_id, 'gateway' => 'fake', 'gateway_payment_id' => 'E2E999']);
        $this->assertSame('livre', PontoAtendimento::find($atendimento->ponto_atendimento_id)->status);
    }

    public function test_mesa_rejeita_qr_para_forma_nao_integrada(): void
    {
        [$empresa, $operador, $caixa, $atendimento] = $this->cenarioMesa();
        $dinheiro = FormaPagamento::create(['empresa_id' => $empresa->id, 'nome' => 'Dinheiro', 'tipo' => 'dinheiro', 'ativo' => true]);

        $this->actingAs($operador)
            ->postJson(route('food.atendimentos.pix', $atendimento), [
                'caixa_id' => $caixa->id,
                'pagamentos' => [['forma_pagamento_id' => $dinheiro->id, 'valor' => 30]],
            ])
            ->assertStatus(422);

        $this->assertSame(0, $this->gateway->criadas);
    }

    public function test_pdv_grava_tipo_da_forma_cadastrada(): void
    {
        [$empresa, $operador, $caixa] = $this->cenario();
        $produto = $this->produto($empresa);
        $debito = FormaPagamento::create(['empresa_id' => $empresa->id, 'nome' => 'Débito Elo', 'tipo' => 'cartao_debito', 'ativo' => true]);

        $this->actingAs($operador)
            ->post(route('vendas.store'), [
                'caixa_id' => $caixa->id,
                'forma_pagamento_id' => $debito->id,
                'forma_pagamento' => 'dinheiro', // ignorado: vale o tipo da forma cadastrada
                'itens' => [['produto_id' => $produto->id, 'quantidade' => 1]],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('vendas', ['empresa_id' => $empresa->id, 'forma_pagamento' => 'cartao_debito']);
    }

    public function test_pdv_so_gera_qr_para_forma_pix_integrada(): void
    {
        [$empresa, $operador, $caixa] = $this->cenario();
        $produto = $this->produto($empresa);
        $pixManual = FormaPagamento::create(['empresa_id' => $empresa->id, 'nome' => 'Pix chave', 'tipo' => 'pix', 'integrado' => false, 'ativo' => true]);

        $this->actingAs($operador)
            ->postJson(route('vendas.pix.store'), [
                'caixa_id' => $caixa->id,
                'forma_pagamento_id' => $pixManual->id,
                'itens' => [['produto_id' => $produto->id, 'quantidade' => 1]],
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'A forma de pagamento escolhida não é Pix integrado.');

        $this->assertSame(0, $this->gateway->criadas);
    }

    public function test_checkout_hospedagem_grava_tipo_da_forma_cadastrada(): void
    {
        [$empresa, $operador, $caixa, $unidade] = $this->cenario();
        $hospedagem = $this->hospedagem($empresa, $unidade, $operador);
        $credito = FormaPagamento::create(['empresa_id' => $empresa->id, 'nome' => 'Crédito', 'tipo' => 'cartao_credito', 'ativo' => true]);

        $this->actingAs($operador)
            ->post(route('hospedagens.checkout.store', $hospedagem), ['caixa_id' => $caixa->id, 'forma_pagamento_id' => $credito->id])
            ->assertRedirect(route('hospedagens.show', $hospedagem));

        $this->assertSame('finalizado', $hospedagem->fresh()->status);
        $this->assertSame('cartao_credito', $hospedagem->fresh()->forma_pagamento);
    }

    public function test_pix_integrado_na_hospedagem_so_faz_checkout_quando_o_banco_confirma(): void
    {
        [$empresa, $operador, $caixa, $unidade] = $this->cenario();
        $hospedagem = $this->hospedagem($empresa, $unidade, $operador);
        $pixQr = FormaPagamento::create(['empresa_id' => $empresa->id, 'nome' => 'Pix QR', 'tipo' => 'pix', 'integrado' => true, 'ativo' => true]);
        $dados = ['caixa_id' => $caixa->id, 'forma_pagamento_id' => $pixQr->id, 'desconto' => 10];
        $total = app(\App\Services\HospedagemService::class)->calcularTotal($hospedagem, 10);

        $this->actingAs($operador)
            ->post(route('hospedagens.checkout.store', $hospedagem), $dados)
            ->assertSessionHas('erro');
        $this->assertSame('hospedado', $hospedagem->fresh()->status);

        $id = $this->postJson(route('hospedagens.checkout.pix', $hospedagem), $dados)
            ->assertCreated()
            ->assertJsonPath('status', 'pendente')
            ->json('id');
        $this->assertSame($total, $this->gateway->ultimoValor);

        $this->getJson(route('vendas.pix.status', $id))->assertJsonMissingPath('redirect');
        $this->assertSame('hospedado', $hospedagem->fresh()->status);

        $this->gateway->statusAtual = GatewayPix::STATUS_PAGA;
        $this->getJson(route('vendas.pix.status', $id))
            ->assertJsonPath('status', 'paga')
            ->assertJsonPath('redirect', route('hospedagens.show', $hospedagem))
            ->assertSessionHas('sucesso');

        $hospedagem->refresh();
        $this->assertSame('finalizado', $hospedagem->status);
        $this->assertSame('pix', $hospedagem->forma_pagamento);
        $this->assertDatabaseHas('caixa_movimentacoes', ['caixa_id' => $caixa->id, 'categoria' => 'hospedagem', 'forma_pagamento' => 'pix', 'valor' => $total]);

        // Polls repetidos não fazem check-out de novo.
        $this->getJson(route('vendas.pix.status', $id))->assertOk();
        $this->assertSame(1, \App\Models\CaixaMovimentacao::where('categoria', 'hospedagem')->count());
    }

    public function test_modal_e_pagina_de_checkout_da_hospedagem_listam_as_formas_cadastradas(): void
    {
        [$empresa, $operador, , $unidade] = $this->cenario();
        $hospedagem = $this->hospedagem($empresa, $unidade, $operador);
        FormaPagamento::create(['empresa_id' => $empresa->id, 'nome' => 'Pix Qrcode', 'tipo' => 'pix', 'integrado' => true, 'ativo' => true]);

        foreach ([route('hospedagens.show', $hospedagem), route('hospedagens.checkout', $hospedagem)] as $url) {
            $this->actingAs($operador)->get($url)
                ->assertOk()
                ->assertSee('Pix Qrcode (QR Code)')
                ->assertSee('name="forma_pagamento_id"', false)
                ->assertDontSee('<option value="cartao_credito">', false)
                ->assertSee(str_replace('/', '\/', route('hospedagens.checkout.pix', $hospedagem)), false)
                ->assertSee('function pixRecebimento', false);
        }
    }

    public function test_operador_de_mesa_sem_permissao_de_vendas_acompanha_o_pix_da_mesa(): void
    {
        $empresa = Empresa::factory()->create();
        $unidade = Unidade::factory()->create(['empresa_id' => $empresa->id]);
        $garcom = User::factory()->create(['empresa_id' => $empresa->id, 'unidade_id' => $unidade->id]);
        foreach (['food.visualizar', 'food.operar', 'food.fechar', 'vendas.criar'] as $nome) {
            Permission::findOrCreate($nome, 'web');
        }
        $garcom->givePermissionTo(['food.visualizar', 'food.operar', 'food.fechar']);
        $caixa = Caixa::create([
            'empresa_id' => $empresa->id, 'unidade_id' => $unidade->id, 'usuario_abertura_id' => $garcom->id,
            'data_abertura' => now(), 'valor_abertura' => 0, 'status' => 'aberto',
        ]);
        $mesa = PontoAtendimento::create(['empresa_id' => $empresa->id, 'unidade_id' => $unidade->id, 'tipo' => 'mesa', 'numero' => 1, 'status' => 'livre']);
        $this->actingAs($garcom);
        $service = app(AtendimentoService::class);
        $atendimento = $service->abrir($mesa, $garcom);
        $service->adicionarItem($atendimento, ['produto_id' => $this->produto($empresa, $unidade)->id, 'quantidade' => 1], $garcom);
        $pixQr = FormaPagamento::create(['empresa_id' => $empresa->id, 'nome' => 'Pix QR', 'tipo' => 'pix', 'integrado' => true, 'ativo' => true]);

        $id = $this->postJson(route('food.atendimentos.pix', $atendimento), [
            'caixa_id' => $caixa->id,
            'percentual_servico' => 0,
            'pagamentos' => [['forma_pagamento_id' => $pixQr->id, 'valor' => 30]],
        ])->assertCreated()->json('id');

        $this->getJson(route('vendas.pix.status', $id))->assertOk()->assertJsonPath('status', 'pendente');
    }

    protected function hospedagem(Empresa $empresa, Unidade $unidade, User $operador): \App\Models\Hospedagem
    {
        $cliente = \App\Models\Cliente::factory()->create(['empresa_id' => $empresa->id, 'unidade_id' => $unidade->id]);
        $quarto = \App\Models\Quarto::create([
            'empresa_id' => $empresa->id,
            'unidade_id' => $unidade->id,
            'numero' => '101',
            'capacidade_maxima' => 2,
            'valor_diaria' => 150,
            'status' => 'ativo',
        ]);

        return \App\Models\Hospedagem::create([
            'empresa_id' => $empresa->id,
            'unidade_id' => $unidade->id,
            'quarto_id' => $quarto->id,
            'cliente_id' => $cliente->id,
            'quantidade_hospedes' => 1,
            'quantidade_adultos' => 1,
            'quantidade_criancas' => 0,
            'quantidade_isentos' => 0,
            'valor_diaria' => 150,
            'data_checkin_prevista' => now()->toDateString(),
            'data_checkout_prevista' => now()->addDay()->toDateString(),
            'data_checkin_real' => now(),
            'status' => 'hospedado',
            'registrado_por_id' => $operador->id,
        ]);
    }

    protected function cenario(): array
    {
        $empresa = Empresa::factory()->create();
        $unidade = Unidade::factory()->create(['empresa_id' => $empresa->id]);
        $operador = User::factory()->create(['empresa_id' => $empresa->id, 'unidade_id' => $unidade->id]);
        $permissoes = ['vendas.criar', 'vendas.visualizar', 'empresa.gerenciar', 'food.visualizar', 'food.operar', 'food.fechar', 'pousada.checkout'];
        foreach ($permissoes as $nome) {
            Permission::findOrCreate($nome, 'web');
        }
        $operador->givePermissionTo($permissoes);
        Gate::before(fn () => true);

        $caixa = Caixa::create([
            'empresa_id' => $empresa->id,
            'unidade_id' => $unidade->id,
            'usuario_abertura_id' => $operador->id,
            'data_abertura' => now(),
            'valor_abertura' => 0,
            'status' => 'aberto',
        ]);

        return [$empresa, $operador, $caixa, $unidade];
    }

    /**
     * Mesa aberta com 1 item de R$ 30.
     */
    protected function cenarioMesa(): array
    {
        [$empresa, $operador, $caixa, $unidade] = $this->cenario();
        $produto = $this->produto($empresa, $unidade);
        $mesa = PontoAtendimento::create([
            'empresa_id' => $empresa->id,
            'unidade_id' => $unidade->id,
            'tipo' => 'mesa',
            'numero' => 1,
            'status' => 'livre',
        ]);

        $this->actingAs($operador);
        $service = app(AtendimentoService::class);
        $atendimento = $service->abrir($mesa, $operador);
        $service->adicionarItem($atendimento, ['produto_id' => $produto->id, 'quantidade' => 1], $operador);

        return [$empresa, $operador, $caixa, Atendimento::findOrFail($atendimento->id), $produto];
    }

    protected function produto(Empresa $empresa, ?Unidade $unidade = null): Produto
    {
        return Produto::create([
            'empresa_id' => $empresa->id,
            'unidade_id' => $unidade?->id,
            'nome' => 'Porção',
            'preco_custo' => 10,
            'preco_venda' => 30,
            'controla_estoque' => true,
            'estoque_atual' => 10,
            'estoque_minimo' => 0,
            'ativo' => true,
        ]);
    }
}
