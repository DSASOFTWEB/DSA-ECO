<?php

namespace Tests\Feature;

use App\Models\Caixa;
use App\Models\CobrancaPix;
use App\Models\Empresa;
use App\Models\Produto;
use App\Models\Unidade;
use App\Models\User;
use App\Services\Integrations\Pix\GatewayPix;
use App\Services\Integrations\Pix\GatewayPixResolver;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PdvPixGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected FakeGatewayPix $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->gateway = new FakeGatewayPix;
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

    public function test_gera_cobranca_com_total_do_servidor_e_so_cria_venda_apos_pagamento(): void
    {
        [$empresa, $operador, $caixa, $produto] = $this->cenario();

        $resposta = $this->actingAs($operador)
            ->postJson(route('vendas.pix.store'), $this->carrinho($caixa, $produto, quantidade: 2, desconto: 5))
            ->assertCreated()
            ->assertJsonPath('status', 'pendente')
            ->assertJsonStructure(['qr_code', 'copia_e_cola', 'status_url', 'cancelar_url']);

        $this->assertEquals(55, $resposta->json('valor'));
        $this->assertSame(55.0, $this->gateway->ultimoValor);
        $this->assertDatabaseCount('vendas', 0);
        $this->assertSame(20, $produto->fresh()->estoque_atual);

        $cobranca = CobrancaPix::findOrFail($resposta->json('id'));

        $this->getJson(route('vendas.pix.status', $cobranca))
            ->assertOk()
            ->assertJsonPath('status', 'pendente')
            ->assertJsonMissingPath('redirect');

        $this->gateway->statusAtual = GatewayPix::STATUS_PAGA;

        $resposta = $this->getJson(route('vendas.pix.status', $cobranca))
            ->assertOk()
            ->assertJsonPath('status', 'paga');

        $cobranca->refresh();
        $this->assertNotNull($cobranca->venda_id);
        $resposta->assertJsonPath('redirect', route('vendas.comprovante', $cobranca->venda_id));
        $this->assertSame(18, $produto->fresh()->estoque_atual);
        $this->assertDatabaseHas('vendas', ['id' => $cobranca->venda_id, 'forma_pagamento' => 'pix', 'valor_total' => 55]);
        $this->assertDatabaseHas('pagamentos', [
            'venda_id' => $cobranca->venda_id,
            'gateway' => 'fake',
            'gateway_payment_id' => 'E2E123',
            'status' => 'aprovado',
        ]);

        // Polls repetidos não duplicam a venda.
        $this->getJson(route('vendas.pix.status', $cobranca))->assertOk();
        $this->assertDatabaseCount('vendas', 1);
    }

    public function test_cancelar_cobranca_pendente_nao_cria_venda(): void
    {
        [, $operador, $caixa, $produto] = $this->cenario();

        $id = $this->actingAs($operador)
            ->postJson(route('vendas.pix.store'), $this->carrinho($caixa, $produto))
            ->assertCreated()
            ->json('id');

        $this->postJson(route('vendas.pix.cancelar', $id))
            ->assertOk()
            ->assertJsonPath('status', 'cancelada');

        $this->assertTrue($this->gateway->cancelou);
        $this->assertDatabaseCount('vendas', 0);
    }

    public function test_falha_do_gateway_retorna_422_sem_gravar_cobranca(): void
    {
        [, $operador, $caixa, $produto] = $this->cenario();
        $this->gateway->falharAoCriar = true;

        $this->actingAs($operador)
            ->postJson(route('vendas.pix.store'), $this->carrinho($caixa, $produto))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Itaú indisponível.');

        $this->assertDatabaseCount('cobrancas_pix', 0);
    }

    public function test_nao_consulta_cobranca_de_outra_empresa(): void
    {
        [, $operador, $caixa, $produto] = $this->cenario();
        $id = $this->actingAs($operador)
            ->postJson(route('vendas.pix.store'), $this->carrinho($caixa, $produto))
            ->json('id');

        [, $intruso] = $this->cenario();

        $this->actingAs($intruso)->getJson(route('vendas.pix.status', $id))->assertNotFound();
        $this->actingAs($intruso)->postJson(route('vendas.pix.cancelar', $id))->assertNotFound();
        $this->assertFalse($this->gateway->cancelou);
    }

    public function test_empresa_salva_configuracao_itau_com_segredo_cifrado_e_certificado(): void
    {
        Storage::fake('local');
        [$empresa, $operador] = $this->cenario();
        [$certificado, $chave] = $this->parCertificado();

        $this->actingAs($operador)
            ->put(route('empresa.update'), $this->dadosEmpresa([
                'gateway_pix_provedor' => 'itau',
                'itau_client_id' => 'client-123',
                'itau_client_secret' => 'segredo-super',
                'itau_chave_pix' => '12345678000199',
                'gateway_pix_expiracao_minutos' => 15,
                'itau_certificado' => UploadedFile::fake()->createWithContent('itau.crt', $certificado),
                'itau_chave_privada' => UploadedFile::fake()->createWithContent('itau.key', $chave),
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('empresa.edit'));

        $empresa->refresh();
        $bruto = $empresa->configuracoes['gateway_pix']['itau']['client_secret'];
        $this->assertNotSame('segredo-super', $bruto);
        $this->assertSame('segredo-super', Crypt::decryptString($bruto));
        $this->assertSame(15, $empresa->configuracaoGatewayPix()['expiracao_minutos']);
        $this->assertTrue($empresa->gatewayPixAtivo());

        // Reenviar sem segredo/arquivos mantém o que já estava salvo.
        $this->put(route('empresa.update'), $this->dadosEmpresa([
            'gateway_pix_provedor' => 'itau',
            'itau_chave_pix' => 'pix@empresa.com',
        ]))->assertSessionHasNoErrors();

        $empresa->refresh();
        $this->assertSame('segredo-super', $empresa->configuracaoGatewayPix()['itau']['client_secret']);
        $this->assertSame('pix@empresa.com', $empresa->configuracaoGatewayPix()['itau']['chave_pix']);
        $this->assertTrue($empresa->gatewayPixAtivo());
    }

    public function test_telas_de_empresa_e_pdv_mostram_o_gateway_ativo(): void
    {
        Storage::fake('local');
        [$empresa, $operador] = $this->cenario();
        [$certificado, $chave] = $this->parCertificado();
        Storage::disk('local')->put($empresa->itauCertificadoCaminho(), $certificado);
        Storage::disk('local')->put($empresa->itauChavePrivadaCaminho(), $chave);
        $empresa->update(['configuracoes' => ['gateway_pix' => [
            'provedor' => 'itau',
            'itau' => ['client_id' => 'cli', 'client_secret' => Crypt::encryptString('seg'), 'chave_pix' => 'pix@x.com'],
        ]]]);

        $this->actingAs($operador)->get(route('empresa.edit'))
            ->assertOk()
            ->assertSee('Gateway de pagamento')
            ->assertSee('Ativo')
            ->assertDontSee('seg"', false);

        $this->get(route('vendas.create'))
            ->assertOk()
            ->assertSee('QR Code no caixa')
            ->assertSee('Recebimento Pix');
    }

    public function test_tela_da_empresa_separa_configuracoes_em_abas(): void
    {
        [, $operador] = $this->cenario();

        $this->actingAs($operador)->get(route('empresa.edit'))
            ->assertOk()
            ->assertSeeInOrder(['Geral', 'Fiscal', 'Financeiro', 'Gerencial'])
            ->assertSee('role="tablist"', false)
            ->assertSee("aba: 'geral'", false);

        $this->get(route('empresa.edit', ['aba' => 'fiscal']))
            ->assertSee("aba: 'fiscal'", false);

        $this->from(route('empresa.edit'))
            ->put(route('empresa.update'), $this->dadosEmpresa([
                '_aba' => 'gerencial',
                'gateway_pix_provedor' => 'itau',
            ]))
            ->assertSessionHasErrors('itau_client_id');

        $this->get(route('empresa.edit'))->assertSee("aba: 'financeiro'", false);

        $this->put(route('empresa.update'), $this->dadosEmpresa(['_aba' => 'gerencial']))
            ->assertRedirect(route('empresa.edit', ['aba' => 'gerencial']));
    }

    public function test_mercado_pago_no_mesmo_seletor_ativa_o_pix_do_pdv(): void
    {
        config(['mercadopago.access_token' => '']);
        [$empresa, $operador] = $this->cenario();

        $this->actingAs($operador)
            ->put(route('empresa.update'), $this->dadosEmpresa(['gateway_pix_provedor' => 'mercadopago']))
            ->assertSessionHasErrors('mercadopago_access_token');

        $this->put(route('empresa.update'), $this->dadosEmpresa([
            'gateway_pix_provedor' => 'mercadopago',
            'mercadopago_access_token' => 'APP_USR-token-da-empresa',
            'gateway_pix_expiracao_minutos' => 5,
        ]))->assertSessionHasNoErrors();

        $empresa->refresh();
        $this->assertSame('mercadopago', $empresa->configuracaoGatewayPix()['provedor']);
        $this->assertSame(5, $empresa->configuracaoGatewayPix()['expiracao_minutos']);
        $this->assertSame('APP_USR-token-da-empresa', $empresa->credenciaisMercadoPago()['access_token']);
        $this->assertTrue($empresa->gatewayPixAtivo());
        $this->assertInstanceOf(
            \App\Services\Integrations\Pix\MercadoPagoPixGateway::class,
            (new GatewayPixResolver)->paraEmpresa($empresa),
        );

        // Trocar para "nenhum" desliga o gateway, mas mantém o token salvo
        // (ele continua servindo ao checkout online e às mensalidades).
        $this->put(route('empresa.update'), $this->dadosEmpresa(['gateway_pix_provedor' => 'nenhum']))
            ->assertSessionHasNoErrors();

        $empresa->refresh();
        $this->assertFalse($empresa->gatewayPixAtivo());
        $this->assertSame('APP_USR-token-da-empresa', $empresa->credenciaisMercadoPago()['access_token']);
    }

    public function test_ativar_itau_exige_credenciais_e_certificado(): void
    {
        Storage::fake('local');
        [$empresa, $operador] = $this->cenario();

        $this->actingAs($operador)
            ->put(route('empresa.update'), $this->dadosEmpresa(['gateway_pix_provedor' => 'itau']))
            ->assertSessionHasErrors(['itau_client_id', 'itau_client_secret', 'itau_chave_pix', 'itau_certificado']);

        $this->assertFalse($empresa->fresh()->gatewayPixAtivo());
    }

    public function test_rejeita_chave_privada_que_nao_pertence_ao_certificado(): void
    {
        Storage::fake('local');
        [, $operador] = $this->cenario();
        [$certificado] = $this->parCertificado();
        [, $outraChave] = $this->parCertificado();

        $this->actingAs($operador)
            ->put(route('empresa.update'), $this->dadosEmpresa([
                'gateway_pix_provedor' => 'itau',
                'itau_client_id' => 'client-123',
                'itau_client_secret' => 'segredo',
                'itau_chave_pix' => '12345678000199',
                'itau_certificado' => UploadedFile::fake()->createWithContent('itau.crt', $certificado),
                'itau_chave_privada' => UploadedFile::fake()->createWithContent('itau.key', $outraChave),
            ]))
            ->assertSessionHasErrors('itau_chave_privada');
    }

    protected function cenario(): array
    {
        $empresa = Empresa::factory()->create();
        $unidade = Unidade::factory()->create(['empresa_id' => $empresa->id]);
        $operador = User::factory()->create(['empresa_id' => $empresa->id, 'unidade_id' => $unidade->id]);
        foreach (['vendas.criar', 'vendas.visualizar', 'empresa.gerenciar'] as $nome) {
            Permission::findOrCreate($nome, 'web');
        }
        $operador->givePermissionTo(['vendas.criar', 'vendas.visualizar', 'empresa.gerenciar']);
        Gate::before(fn () => true);

        $caixa = Caixa::create([
            'empresa_id' => $empresa->id,
            'unidade_id' => $unidade->id,
            'usuario_abertura_id' => $operador->id,
            'data_abertura' => now(),
            'valor_abertura' => 0,
            'status' => 'aberto',
        ]);

        $produto = Produto::create([
            'empresa_id' => $empresa->id,
            'unidade_id' => $unidade->id,
            'nome' => 'Picolé',
            'preco_custo' => 10,
            'preco_venda' => 30,
            'controla_estoque' => true,
            'estoque_atual' => 20,
            'estoque_minimo' => 2,
            'ativo' => true,
        ]);

        return [$empresa, $operador, $caixa, $produto];
    }

    protected function carrinho(Caixa $caixa, Produto $produto, int $quantidade = 1, float $desconto = 0): array
    {
        return [
            'caixa_id' => $caixa->id,
            'forma_pagamento' => 'pix',
            'itens' => [['produto_id' => $produto->id, 'quantidade' => $quantidade, 'desconto' => $desconto]],
        ];
    }

    protected function dadosEmpresa(array $extra): array
    {
        return array_merge([
            'nome' => 'Parque Teste',
            'regime_tributario' => 'simples',
            'impressao_modo' => 'dom',
            'impressao_colunas' => 48,
            'ambiente_nfe' => 2,
            'numero_serie_nfe' => 1,
            'numero_serie_nfce' => 1,
            'numero_serie_nfse' => 1,
            'numero_ultima_nfe_producao' => 0,
            'numero_ultima_nfe_homologacao' => 0,
            'numero_ultima_nfce_producao' => 0,
            'numero_ultima_nfce_homologacao' => 0,
            'numero_ultima_nfse' => 0,
            'nfse_provider' => 'nacional_gov',
            'nfse_auth_mode' => 'certificado',
        ], $extra);
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function parCertificado(): array
    {
        $chave = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $csr = openssl_csr_new(['commonName' => 'itau-teste'], $chave);
        $x509 = openssl_csr_sign($csr, null, $chave, 1);

        openssl_x509_export($x509, $certificadoPem);
        openssl_pkey_export($chave, $chavePem);

        return [$certificadoPem, $chavePem];
    }
}

class FakeGatewayPix implements GatewayPix
{
    public string $statusAtual = self::STATUS_PENDENTE;

    public ?float $ultimoValor = null;

    public bool $cancelou = false;

    public bool $falharAoCriar = false;

    private int $sequencia = 0;

    public function nome(): string
    {
        return 'fake';
    }

    public function criarCobranca(float $valor, string $descricao): array
    {
        if ($this->falharAoCriar) {
            throw new \App\Exceptions\IntegrationException('Itaú indisponível.', 'fake');
        }

        $this->ultimoValor = $valor;
        $this->sequencia++;

        return [
            'txid' => 'TX'.$this->sequencia.uniqid(),
            'copia_e_cola' => '00020126580014br.gov.bcb.pix0136teste5204000053039865802BR6304ABCD',
            'expiracao_segundos' => 600,
            'payload' => ['status' => 'ATIVA'],
        ];
    }

    public function consultarCobranca(string $txid): array
    {
        return [
            'status' => $this->statusAtual,
            'e2eid' => $this->statusAtual === self::STATUS_PAGA ? 'E2E123' : null,
            'payload' => ['status' => $this->statusAtual],
        ];
    }

    public function cancelarCobranca(string $txid): void
    {
        $this->cancelou = true;
        $this->statusAtual = self::STATUS_CANCELADA;
    }
}
