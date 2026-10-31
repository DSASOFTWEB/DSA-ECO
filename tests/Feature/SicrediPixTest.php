<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\User;
use App\Services\Integrations\Pix\GatewayPix;
use App\Services\Integrations\Pix\GatewayPixResolver;
use App\Services\Integrations\Pix\SicrediCertificadoService;
use App\Services\Integrations\Pix\SicrediPixGateway;
use App\Exceptions\IntegrationException;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Gateway Pix Sicredi: CSR gerado no sistema, certificado assinado enviado
 * depois (DER ou PEM), credenciais e a API (token mTLS + cob) simulada.
 */
class SicrediPixTest extends TestCase
{
    use RefreshDatabase;

    protected Empresa $empresa;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        Storage::fake('local');

        $this->empresa = Empresa::factory()->create(['nome' => 'Parque Catolé', 'cnpj' => '21.957.084/0001-26']);
        $this->admin = User::factory()->create(['empresa_id' => $this->empresa->id]);
        Gate::before(fn () => true);
        $this->actingAs($this->admin);
    }

    public function test_gera_csr_no_padrao_sicredi_e_permite_baixar(): void
    {
        $this->post(route('empresa.pix.sicredi.csr'), ['nome_parceiro' => 'Parque Catolé!'])
            ->assertRedirect(route('empresa.edit', ['aba' => 'financeiro']))
            ->assertSessionHas('sucesso');

        $csr = app(SicrediCertificadoService::class)->csrPendente($this->empresa->fresh());
        $assunto = openssl_csr_get_subject($csr);
        $this->assertSame('api-parceiros-parquecatole.sicredi.net', $assunto['CN']);
        $this->assertSame('urn:cpfcnpj:21957084000126', $assunto['serialNumber']);
        $this->assertSame('API Pix Sicredi', $assunto['OU']);
        $this->assertSame('Confederacao Interestadual das Cooperativas Ligadas ao Sicredi', $assunto['O']);
        $this->assertSame('BR', $assunto['jurisdictionC']);

        // A chave fica guardada cifrada (não em texto puro) e casa com o CSR.
        $bruto = $this->empresa->fresh()->configuracoes['gateway_pix']['sicredi']['csr_chave_privada_pem'];
        $this->assertStringNotContainsString('PRIVATE KEY', $bruto);

        $this->get(route('empresa.pix.sicredi.csr.download'))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="sicredi-pix-21957084000126.csr"')
            ->assertSee('BEGIN CERTIFICATE REQUEST');
    }

    public function test_csr_exige_cnpj(): void
    {
        $this->empresa->update(['cnpj' => null]);

        $this->post(route('empresa.pix.sicredi.csr'))->assertSessionHas('erro', fn ($e) => str_contains($e, 'CNPJ'));
    }

    public function test_certificado_der_do_sicredi_casa_com_a_chave_do_csr_e_ativa(): void
    {
        $this->post(route('empresa.pix.sicredi.csr'));
        $der = $this->assinarComoSicredi(app(SicrediCertificadoService::class)->csrPendente($this->empresa->fresh()), der: true);

        $this->put(route('empresa.update'), $this->dadosEmpresa([
            'gateway_pix_provedor' => 'sicredi',
            'sicredi_client_id' => 'cli-sicredi',
            'sicredi_client_secret' => 'segredo-sicredi',
            'sicredi_tipo_chave' => 'cnpj',
            'sicredi_chave_pix' => '21.957.084/0001-26',
            'sicredi_ambiente' => 'producao',
            'sicredi_certificado' => UploadedFile::fake()->createWithContent('certificado.cer', $der),
        ]))->assertSessionHasNoErrors();

        $empresa = $this->empresa->fresh();
        $config = $empresa->configuracaoGatewayPix();
        $this->assertSame('sicredi', $config['provedor']);
        $this->assertTrue($empresa->gatewayPixAtivo());
        $this->assertTrue($config['sicredi']['tem_certificado']);
        $this->assertSame('21957084000126', $config['sicredi']['chave_pix']);
        $this->assertSame('segredo-sicredi', $config['sicredi']['client_secret']);
        $this->assertNotSame('segredo-sicredi', $empresa->configuracoes['gateway_pix']['sicredi']['client_secret']);
        $this->assertSame('api-parceiros-parquecatole.sicredi.net', $config['sicredi']['certificado_assunto']);
        $this->assertNull(app(SicrediCertificadoService::class)->csrPendente($empresa), 'CSR pendente concluído');

        // PEM convertido no disco, pronto para o mTLS.
        $sicredi = app(SicrediCertificadoService::class);
        $this->assertStringContainsString('BEGIN CERTIFICATE', Storage::disk('local')->get($sicredi->caminhoCertificado($empresa)));
        $this->assertInstanceOf(SicrediPixGateway::class, app(GatewayPixResolver::class)->paraEmpresa($empresa));

        // A tela mostra o Sicredi configurado.
        $this->get(route('empresa.edit', ['aba' => 'financeiro']))->assertOk()
            ->assertSee('Sicredi — API Pix')
            ->assertSee('Certificado mTLS ativo')
            ->assertSee('Testar conexão com o Sicredi');

        // Salvar de novo sem segredo/arquivo mantém tudo.
        $this->put(route('empresa.update'), $this->dadosEmpresa(['gateway_pix_provedor' => 'sicredi', 'sicredi_ambiente' => 'producao']))
            ->assertSessionHasNoErrors();
        $this->assertTrue($this->empresa->fresh()->gatewayPixAtivo());
    }

    public function test_certificado_de_outro_csr_e_recusado(): void
    {
        $this->post(route('empresa.pix.sicredi.csr'));
        $chaveAlheia = openssl_pkey_new(['private_key_bits' => 2048]);
        openssl_csr_export(openssl_csr_new(['commonName' => 'outro'], $chaveAlheia), $csrAlheio);

        $this->put(route('empresa.update'), $this->dadosEmpresa([
            'gateway_pix_provedor' => 'sicredi',
            'sicredi_client_id' => 'cli', 'sicredi_client_secret' => 'seg', 'sicredi_chave_pix' => 'pix@parque.com',
            'sicredi_certificado' => UploadedFile::fake()->createWithContent('c.pem', $this->assinarComoSicredi($csrAlheio)),
        ]))->assertSessionHasErrors(['sicredi_certificado' => 'Este certificado não corresponde ao último CSR gerado aqui. Envie o certificado do CSR mais recente (ou a .key usada para gerá-lo).']);

        $this->assertFalse($this->empresa->fresh()->gatewayPixAtivo());
    }

    public function test_ativar_sem_dados_aponta_o_que_falta(): void
    {
        $this->put(route('empresa.update'), $this->dadosEmpresa([
            'gateway_pix_provedor' => 'sicredi',
            'sicredi_ambiente' => 'homologacao',
        ]))->assertSessionHasErrors(['sicredi_client_id', 'sicredi_client_secret', 'sicredi_chave_pix', 'sicredi_certificado', 'sicredi_url_homologacao']);
    }

    public function test_salvar_itau_nao_apaga_o_sicredi(): void
    {
        $this->configurarSicredi();

        $this->put(route('empresa.update'), $this->dadosEmpresa(['gateway_pix_provedor' => 'nenhum']))->assertSessionHasNoErrors();

        $config = $this->empresa->fresh()->configuracaoGatewayPix();
        $this->assertSame('cli-sicredi', $config['sicredi']['client_id']);
        $this->assertTrue($config['sicredi']['tem_certificado']);
    }

    public function test_cobranca_consulta_e_cancelamento_na_api_reaproveitando_o_token(): void
    {
        $this->configurarSicredi();
        $base = SicrediPixGateway::URL_PRODUCAO;
        $status = 'ATIVA';

        Http::fake([
            "{$base}/oauth/token" => Http::response(['access_token' => 'TOKEN-1', 'expires_in' => 3600, 'scope' => 'cob.write cob.read']),
            "{$base}/api/v2/cob/*" => function (Request $r) use (&$status) {
                $txid = basename(parse_url($r->url(), PHP_URL_PATH));

                return match ($r->method()) {
                    'PUT' => Http::response(['txid' => $txid, 'status' => 'ATIVA', 'calendario' => ['expiracao' => 600], 'pixCopiaECola' => '00020101021226SICREDI'], 201),
                    'GET' => Http::response(['txid' => $txid, 'status' => $status, 'pix' => $status === 'CONCLUIDA' ? [['endToEndId' => 'E7482']] : []]),
                    'PATCH' => Http::response(['txid' => $txid, 'status' => 'REMOVIDA_PELO_USUARIO_RECEBEDOR']),
                };
            },
        ]);

        $gateway = app(GatewayPixResolver::class)->paraEmpresa($this->empresa->fresh());
        $cobranca = $gateway->criarCobranca(12.5, 'Venda PDV - caixa #3');

        $this->assertSame('00020101021226SICREDI', $cobranca['copia_e_cola']);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{26,35}$/', $cobranca['txid']);
        $this->assertSame(600, $cobranca['expiracao_segundos']);

        $this->assertSame(GatewayPix::STATUS_PENDENTE, $gateway->consultarCobranca($cobranca['txid'])['status']);
        $status = 'CONCLUIDA';
        $pago = $gateway->consultarCobranca($cobranca['txid']);
        $this->assertSame(GatewayPix::STATUS_PAGA, $pago['status']);
        $this->assertSame('E7482', $pago['e2eid']);
        $gateway->cancelarCobranca($cobranca['txid']);

        Http::assertSent(fn (Request $r) => $r->url() === "{$base}/oauth/token"
            && $r->hasHeader('Authorization', 'Basic '.base64_encode('cli-sicredi:segredo-sicredi'))
            && $r['grant_type'] === 'client_credentials'
            && $r['scope'] === 'cob.write cob.read');
        Http::assertSent(fn (Request $r) => $r->method() === 'PUT'
            && $r->hasHeader('Authorization', 'Bearer TOKEN-1')
            && $r['valor'] === ['original' => '12.50', 'modalidadeAlteracao' => 0]
            && $r['chave'] === '21957084000126'
            && $r['calendario']['expiracao'] === 600
            && $r['solicitacaoPagador'] === 'Venda PDV - caixa #3');
        Http::assertSent(fn (Request $r) => $r->method() === 'PATCH' && $r['status'] === 'REMOVIDA_PELO_USUARIO_RECEBEDOR');

        // Token pedido uma vez só (o Sicredi bloqueia geração excessiva).
        $this->assertCount(1, collect(Http::recorded())->filter(fn ($par) => str_ends_with($par[0]->url(), '/oauth/token')));
    }

    public function test_token_expirado_e_renovado_uma_vez(): void
    {
        $this->configurarSicredi();
        $base = SicrediPixGateway::URL_PRODUCAO;

        Http::fake([
            "{$base}/oauth/token" => Http::sequence()
                ->push(['access_token' => 'VELHO', 'expires_in' => 3600])
                ->push(['access_token' => 'NOVO', 'expires_in' => 3600]),
            "{$base}/api/v2/cob/*" => fn (Request $r) => $r->hasHeader('Authorization', 'Bearer VELHO')
                ? Http::response(['title' => 'Não autorizado'], 401)
                : Http::response(['status' => 'ATIVA']),
        ]);

        $this->assertSame(GatewayPix::STATUS_PENDENTE, (new SicrediPixGateway($this->empresa->fresh()))->consultarCobranca(str_repeat('A', 30))['status']);
    }

    public function test_erro_da_api_vira_mensagem_legivel(): void
    {
        $this->configurarSicredi();
        $base = SicrediPixGateway::URL_PRODUCAO;

        Http::fake([
            "{$base}/oauth/token" => Http::response(['access_token' => 'T', 'expires_in' => 3600]),
            "{$base}/api/v2/cob/*" => Http::response([
                'title' => 'Cobrança inválida.', 'status' => 400, 'detail' => 'A requisição que busca alterar ou criar uma cobrança não respeita o schema.',
                'violacoes' => [['razao' => 'A chave informada não pertence ao recebedor.', 'propriedade' => 'cob.chave']],
            ], 400),
        ]);

        try {
            (new SicrediPixGateway($this->empresa->fresh()))->criarCobranca(10, 'Teste');
            $this->fail('Deveria falhar');
        } catch (IntegrationException $e) {
            $this->assertStringContainsString('A chave informada não pertence ao recebedor.', $e->getMessage());
        }
    }

    public function test_botao_testar_conexao(): void
    {
        $this->configurarSicredi();
        Http::fake([SicrediPixGateway::URL_PRODUCAO.'/oauth/token' => Http::sequence()
            ->push(['access_token' => 'T', 'expires_in' => 3600, 'scope' => 'cob.write cob.read'])
            ->push(['error' => 'invalid_client'], 401)]);

        $this->post(route('empresa.pix.sicredi.testar'))
            ->assertSessionHas('sucesso', fn ($m) => str_contains($m, 'Conexão com o Sicredi (produção) OK'));

        // O teste sempre pede token novo (não usa o guardado).
        $this->post(route('empresa.pix.sicredi.testar'))
            ->assertSessionHas('erro', fn ($m) => str_contains($m, 'Não foi possível autenticar'));
    }

    public function test_homologacao_usa_a_url_informada(): void
    {
        $this->configurarSicredi(['ambiente' => 'homologacao', 'url_homologacao' => 'https://api-pix-h.sicredi.com.br']);
        Http::fake(['https://api-pix-h.sicredi.com.br/oauth/token' => Http::response(['access_token' => 'T', 'expires_in' => 60])]);

        $this->assertSame('homologacao', (new SicrediPixGateway($this->empresa->fresh()))->testarConexao()['ambiente']);
        Http::assertSent(fn (Request $r) => $r->url() === 'https://api-pix-h.sicredi.com.br/oauth/token');
    }

    public function test_passo_a_passo_em_pdf(): void
    {
        $resposta = $this->get(route('empresa.pix.sicredi.passo-a-passo'))->assertOk();

        $this->assertSame('application/pdf', $resposta->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $resposta->getContent());

        $html = view('empresa.pdf.sicredi-passo-a-passo', [
            'empresa' => $this->empresa, 'nomeParceiro' => 'parquecatole', 'urlSistema' => '',
        ])->render();
        $this->assertStringContainsString('21.957.084/0001-26', $html);
        $this->assertStringContainsString('api-parceiros-parquecatole.sicredi.net', $html);
        $this->assertStringContainsString('Registrar Novo CSR', $html);
    }

    /** Sicredi configurado (CSR gerado aqui + certificado assinado). */
    protected function configurarSicredi(array $extra = []): void
    {
        $servico = app(SicrediCertificadoService::class);
        $csr = $servico->gerarCsr($this->empresa, 'parquecatole');
        $empresa = $this->empresa->fresh();
        $meta = $servico->gravarPar($empresa, $this->assinarComoSicredi($csr), $servico->chaveDoCsr($empresa));

        $config = $empresa->configuracoes;
        $config['gateway_pix'] = [
            'provedor' => 'sicredi',
            'expiracao_minutos' => 10,
            'sicredi' => array_merge($config['gateway_pix']['sicredi'], $meta, [
                'client_id' => 'cli-sicredi',
                'client_secret' => Crypt::encryptString('segredo-sicredi'),
                'chave_pix' => '21957084000126',
                'tipo_chave' => 'cnpj',
                'ambiente' => 'producao',
            ], $extra),
        ];
        $empresa->update(['configuracoes' => $config]);
        $this->empresa = $empresa->fresh();
    }

    /** Simula o Sicredi assinando o CSR com a CA dele. */
    protected function assinarComoSicredi(string $csr, bool $der = false): string
    {
        $chaveCa = openssl_pkey_new(['private_key_bits' => 2048]);
        $ca = openssl_csr_sign(openssl_csr_new(['commonName' => 'Sicredi CA Teste'], $chaveCa), null, $chaveCa, 30);
        openssl_x509_export(openssl_csr_sign($csr, $ca, $chaveCa, 365), $pem);

        if (! $der) {
            return $pem;
        }

        return base64_decode(preg_replace('/-----[^-]+-----|\s+/', '', $pem));
    }

    protected function dadosEmpresa(array $extra): array
    {
        return array_merge([
            'nome' => 'Parque Catolé',
            'cnpj' => '21.957.084/0001-26',
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
}
