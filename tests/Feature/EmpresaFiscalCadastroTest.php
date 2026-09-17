<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class EmpresaFiscalCadastroTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_atualiza_parametros_fiscais_da_empresa(): void
    {
        [$empresa, , $user] = $this->criarGestor();

        $this->actingAs($user)
            ->put(route('empresa.update'), $this->payloadFiscal([
                'nome' => $empresa->nome,
                'regime_tributario' => 'simples',
                'ambiente_nfe' => 2,
                'csc_id' => '000001',
                'csc' => 'ABC123CSC',
                'ie' => '123456789',
                'im' => '998877',
                'cnae' => '9311500',
                'numero_serie_nfce' => 3,
                'nfse_provider' => 'nacional_gov',
                'nfse_nacional_habilitado' => 1,
                'bluesoft_token' => 'token-empresa-cosmos',
            ]))
            ->assertRedirect(route('empresa.edit'));

        $empresa->refresh();

        $this->assertSame('123456789', $empresa->ie);
        $this->assertSame('998877', $empresa->im);
        $this->assertSame('9311500', $empresa->cnae);
        $this->assertSame(2, $empresa->ambiente_nfe);
        $this->assertSame('000001', $empresa->csc_id);
        $this->assertSame('ABC123CSC', $empresa->csc);
        $this->assertSame(3, $empresa->numero_serie_nfce);
        $this->assertTrue($empresa->nfse_nacional_habilitado);
        $this->assertSame('token-empresa-cosmos', $empresa->bluesoft_token);
        $this->assertSame(1, $empresa->crt());
    }

    public function test_upload_certificado_a1_grava_blob_e_senha(): void
    {
        [$empresa, , $user] = $this->criarGestor();
        $pfx = UploadedFile::fake()->createWithContent(
            'certificado.pfx',
            $this->criarPfx('senha-secreta')
        );

        $this->actingAs($user)
            ->post(route('empresa.update'), $this->payloadFiscal([
                '_method' => 'PUT',
                'nome' => $empresa->nome,
                'certificado' => $pfx,
                'certificado_senha' => 'senha-secreta',
            ]))
            ->assertRedirect(route('empresa.edit'));

        $empresa->refresh();

        $this->assertTrue($empresa->temCertificadoDigital());
        $this->assertSame('senha-secreta', $empresa->certificado_senha);
        $this->assertNotEmpty($empresa->getRawOriginal('certificado_arquivo'));
    }

    public function test_certificado_invalido_nao_substitui_o_arquivo_ja_salvo(): void
    {
        [$empresa, , $user] = $this->criarGestor();
        $original = $this->criarPfx('senha-original');
        $empresa->update([
            'certificado_arquivo' => $original,
            'certificado_senha' => 'senha-original',
        ]);

        $invalido = UploadedFile::fake()->createWithContent('certificado.pfx', 'arquivo-invalido');

        $this->actingAs($user)
            ->from(route('empresa.edit'))
            ->post(route('empresa.update'), $this->payloadFiscal([
                '_method' => 'PUT',
                'nome' => $empresa->nome,
                'certificado' => $invalido,
                'certificado_senha' => 'senha-errada',
            ]))
            ->assertRedirect(route('empresa.edit'))
            ->assertSessionHasErrors('certificado');

        $empresa->refresh();
        $this->assertSame($original, $empresa->getRawOriginal('certificado_arquivo'));
        $this->assertSame('senha-original', $empresa->certificado_senha);
    }

    public function test_cosmos_prioriza_token_da_empresa_sobre_env(): void
    {
        config(['parque.cosmos_token' => 'token-env']);
        [$empresa, , $user] = $this->criarGestor();
        $empresa->update(['bluesoft_token' => 'token-empresa']);

        Http::fake(function ($request) {
            $this->assertSame('token-empresa', $request->header('X-Cosmos-Token')[0] ?? null);

            return Http::response([
                'gtin' => '7891000100103',
                'description' => 'ITEM EMPRESA',
                'ncm' => ['code' => '22011000'],
            ], 200);
        });

        $this->actingAs($user)
            ->getJson(route('produtos.consultar-ean', ['ean' => '7891000100103']))
            ->assertOk()
            ->assertJsonPath('nome', 'ITEM EMPRESA');
    }

    public function test_cosmos_usa_env_quando_empresa_sem_token(): void
    {
        config(['parque.cosmos_token' => 'token-env']);
        [, , $user] = $this->criarGestor();

        Http::fake(function ($request) {
            $this->assertSame('token-env', $request->header('X-Cosmos-Token')[0] ?? null);

            return Http::response([
                'gtin' => '7891000100103',
                'description' => 'ITEM ENV',
            ], 200);
        });

        $this->actingAs($user)
            ->getJson(route('produtos.consultar-ean', ['ean' => '7891000100103']))
            ->assertOk()
            ->assertJsonPath('nome', 'ITEM ENV');
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function payloadFiscal(array $extra = []): array
    {
        return array_merge([
            'nome' => 'Empresa Teste',
            'regime_tributario' => 'simples',
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
            'impressao_modo' => 'dom',
            'impressao_colunas' => 48,
        ], $extra);
    }

    protected function criarGestor(): array
    {
        $empresa = Empresa::factory()->create();
        $unidade = Unidade::factory()->create(['empresa_id' => $empresa->id]);
        $user = User::factory()->create(['empresa_id' => $empresa->id, 'unidade_id' => $unidade->id]);

        foreach (['empresa.gerenciar', 'estoque.visualizar', 'estoque.criar'] as $nome) {
            Permission::findOrCreate($nome, 'web');
        }
        $user->givePermissionTo(['empresa.gerenciar', 'estoque.visualizar', 'estoque.criar']);
        Gate::before(fn () => true);

        return [$empresa, $unidade, $user];
    }

    protected function criarPfx(string $senha): string
    {
        $chave = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $csr = openssl_csr_new(['commonName' => 'Empresa Teste'], $chave, ['digest_alg' => 'sha256']);
        $certificado = openssl_csr_sign($csr, null, $chave, 1, ['digest_alg' => 'sha256']);

        $this->assertNotFalse($certificado);
        $this->assertTrue(openssl_pkcs12_export($certificado, $pfx, $chave, $senha));

        return $pfx;
    }
}
