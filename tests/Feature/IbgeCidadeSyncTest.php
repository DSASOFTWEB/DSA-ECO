<?php

namespace Tests\Feature;

use App\Models\Cidade;
use App\Models\Empresa;
use App\Models\Unidade;
use App\Models\User;
use App\Services\IbgeCidadeService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class IbgeCidadeSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_baixa_cidades_pela_api_ibge(): void
    {
        [, , $user] = $this->criarUsuarioEmpresa();

        Http::fake([
            IbgeCidadeService::URL_MUNICIPIOS => Http::response([
                [
                    'id' => 3550308,
                    'nome' => 'São Paulo',
                    'microrregiao' => [
                        'mesorregiao' => [
                            'UF' => ['sigla' => 'SP', 'nome' => 'São Paulo'],
                        ],
                    ],
                ],
                [
                    'id' => 3304557,
                    'nome' => 'Rio de Janeiro',
                    'microrregiao' => [
                        'mesorregiao' => [
                            'UF' => ['sigla' => 'RJ', 'nome' => 'Rio de Janeiro'],
                        ],
                    ],
                ],
                [
                    'id' => 12,
                    'nome' => 'Inválido',
                ],
            ], 200),
        ]);

        $this->actingAs($user)
            ->post(route('cidades.sincronizar'))
            ->assertRedirect(route('cidades.index'))
            ->assertSessionHas('sucesso');

        $this->assertDatabaseHas('cidades', ['codigo' => '3550308', 'nome' => 'São Paulo', 'uf' => 'SP']);
        $this->assertDatabaseHas('cidades', ['codigo' => '3304557', 'nome' => 'Rio de Janeiro', 'uf' => 'RJ']);
        $this->assertSame(2, Cidade::count());
    }

    public function test_sincroniza_apenas_uf_selecionada(): void
    {
        [, , $user] = $this->criarUsuarioEmpresa();

        Http::fake([
            'https://servicodados.ibge.gov.br/api/v1/localidades/estados/PR/municipios' => Http::response([
                [
                    'id' => 4106902,
                    'nome' => 'Curitiba',
                    'microrregiao' => [
                        'mesorregiao' => [
                            'UF' => ['sigla' => 'PR'],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->actingAs($user)
            ->post(route('cidades.sincronizar'), ['uf' => 'PR'])
            ->assertRedirect(route('cidades.index'))
            ->assertSessionHas('sucesso');

        $this->assertDatabaseHas('cidades', ['codigo' => '4106902', 'uf' => 'PR']);
    }

    public function test_autocomplete_cidade_retorna_sugestoes(): void
    {
        [, , $user] = $this->criarUsuarioEmpresa();

        Cidade::create(['nome' => 'São Paulo', 'uf' => 'SP', 'codigo' => '3550308']);

        $this->actingAs($user)
            ->getJson(route('cidades.autocomplete', ['busca' => 'São']))
            ->assertOk()
            ->assertJsonFragment(['codigo' => '3550308']);
    }

    public function test_usuario_sem_permissao_nao_acessa_cidades(): void
    {
        $empresa = Empresa::factory()->create();
        $unidade = Unidade::factory()->create(['empresa_id' => $empresa->id]);
        $user = User::factory()->create(['empresa_id' => $empresa->id, 'unidade_id' => $unidade->id]);

        $this->actingAs($user)
            ->get(route('cidades.index'))
            ->assertForbidden();
    }

    protected function criarUsuarioEmpresa(): array
    {
        $empresa = Empresa::factory()->create();
        $unidade = Unidade::factory()->create(['empresa_id' => $empresa->id]);
        $user = User::factory()->create(['empresa_id' => $empresa->id, 'unidade_id' => $unidade->id]);

        Permission::findOrCreate('empresa.gerenciar', 'web');
        $user->givePermissionTo('empresa.gerenciar');
        Gate::before(fn () => null);

        return [$empresa, $unidade, $user];
    }
}
