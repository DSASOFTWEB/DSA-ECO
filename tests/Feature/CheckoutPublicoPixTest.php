<?php

namespace Tests\Feature;

use App\Models\Acesso;
use App\Models\Empresa;
use App\Models\Pagamento;
use App\Models\TipoEntrada;
use App\Models\Unidade;
use App\Models\Venda;
use App\Services\Integrations\Pix\GatewayPix;
use App\Services\Integrations\Pix\GatewayPixResolver;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class CheckoutPublicoPixTest extends TestCase
{
    use RefreshDatabase;

    protected CheckoutGatewayItauFake $gateway;

    protected bool $gatewayAtivo = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->gateway = new CheckoutGatewayItauFake;
        $teste = $this;
        $this->app->instance(GatewayPixResolver::class, new class($this->gateway, $teste) extends GatewayPixResolver
        {
            public function __construct(private GatewayPix $fake, private CheckoutPublicoPixTest $teste) {}

            public function paraEmpresa(Empresa $empresa): ?GatewayPix
            {
                return $this->teste->gatewayLigado() ? $this->fake : null;
            }

            public function porNome(Empresa $empresa, string $gateway): ?GatewayPix
            {
                return $this->fake;
            }
        });
    }

    public function gatewayLigado(): bool
    {
        return $this->gatewayAtivo;
    }

    public function test_sem_gateway_configurado_bloqueia_compra_com_pix(): void
    {
        $this->gatewayAtivo = false;
        [$unidade, $tipo] = $this->cenario();

        $this->get(route('checkout.index', $unidade))
            ->assertOk()
            ->assertSee('indisponível no momento')
            ->assertDontSee('Gerar QR Code Pix');

        $this->post(route('checkout.store', $unidade), ['tipo_entrada_id' => $tipo->id, 'quantidade' => 2])
            ->assertRedirect()
            ->assertSessionHas('erro');

        $this->assertDatabaseCount('vendas', 0);
    }

    public function test_itau_gera_qr_e_confirma_venda_pela_consulta_de_status(): void
    {
        [$unidade, $tipo] = $this->cenario();

        $this->post(route('checkout.store', $unidade), ['tipo_entrada_id' => $tipo->id, 'quantidade' => 2])
            ->assertRedirect();

        $venda = Venda::withoutGlobalScopes()->firstOrFail();
        $pagamento = Pagamento::withoutGlobalScopes()->where('venda_id', $venda->id)->firstOrFail();
        $this->assertSame('pendente', $venda->status);
        $this->assertSame('itau', $pagamento->gateway);
        $this->assertSame(60.0, $this->gateway->ultimoValor);

        $this->get(URL::signedRoute('checkout.pedido', ['venda' => $venda->id]))
            ->assertOk()
            ->assertSee('data:image/png;base64,', false)
            ->assertSee($this->gateway->copiaECola);

        $statusUrl = URL::signedRoute('checkout.status', ['venda' => $venda->id]);
        $this->getJson($statusUrl)->assertJsonPath('status', 'pendente');

        $this->gateway->statusAtual = GatewayPix::STATUS_PAGA;
        Cache::flush();

        $this->getJson($statusUrl)->assertJsonPath('status', 'pago');

        $this->assertSame('pago', $venda->fresh()->status);
        $this->assertSame('aprovado', $pagamento->fresh()->status);
        $this->assertSame('E2E-CHECKOUT', $pagamento->fresh()->gateway_payment_id);
        $this->assertSame(2, Acesso::withoutGlobalScopes()->where('venda_id', $venda->id)->count());
    }

    public function test_itau_cancelado_no_banco_cancela_o_pedido(): void
    {
        [$unidade, $tipo] = $this->cenario();

        $this->post(route('checkout.store', $unidade), ['tipo_entrada_id' => $tipo->id, 'quantidade' => 1]);
        $venda = Venda::withoutGlobalScopes()->firstOrFail();

        $this->gateway->statusAtual = GatewayPix::STATUS_CANCELADA;

        $this->getJson(URL::signedRoute('checkout.status', ['venda' => $venda->id]))
            ->assertJsonPath('status', 'cancelado');

        $this->assertSame(0, Acesso::withoutGlobalScopes()->where('venda_id', $venda->id)->count());
    }

    /**
     * @return array{0: Unidade, 1: TipoEntrada}
     */
    protected function cenario(): array
    {
        $empresa = Empresa::factory()->create();
        $unidade = Unidade::factory()->create(['empresa_id' => $empresa->id]);
        $tipo = TipoEntrada::create([
            'empresa_id' => $empresa->id,
            'nome' => 'Inteira',
            'valor' => 30,
            'eh_plano' => false,
            'ativo' => true,
        ]);

        if ($this->gatewayAtivo) {
            $empresa->update(['configuracoes' => ['gateway_pix' => ['provedor' => 'itau']]]);
        }

        return [$unidade, $tipo];
    }
}

class CheckoutGatewayItauFake implements GatewayPix
{
    public string $statusAtual = self::STATUS_PENDENTE;

    public ?float $ultimoValor = null;

    public string $copiaECola = '00020126580014br.gov.bcb.pix0136checkout5204000053039865802BR6304ABCD';

    public function nome(): string
    {
        return Empresa::GATEWAY_PIX_ITAU;
    }

    public function criarCobranca(float $valor, string $descricao): array
    {
        $this->ultimoValor = $valor;

        return [
            'txid' => 'TXCHECKOUT'.uniqid(),
            'copia_e_cola' => $this->copiaECola,
            'expiracao_segundos' => 600,
            'payload' => ['status' => 'ATIVA'],
        ];
    }

    public function consultarCobranca(string $txid): array
    {
        return [
            'status' => $this->statusAtual,
            'e2eid' => $this->statusAtual === self::STATUS_PAGA ? 'E2E-CHECKOUT' : null,
            'payload' => ['status' => $this->statusAtual],
        ];
    }

    public function cancelarCobranca(string $txid): void
    {
        $this->statusAtual = self::STATUS_CANCELADA;
    }
}
