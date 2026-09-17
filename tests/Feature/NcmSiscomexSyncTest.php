<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Ncm;
use App\Models\Unidade;
use App\Models\User;
use App\Services\Fiscal\NcmSiscomexService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class NcmSiscomexSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_baixa_ncm_pela_api_siscomex(): void
    {
        [, , $user] = $this->criarUsuarioEstoque();

        Http::fake([
            NcmSiscomexService::URL_SISCOMEX.'*' => Http::response([
                'Nomenclaturas' => [
                    ['Codigo' => '2201.10.00', 'Descricao' => 'Aguas minerais'],
                    ['Codigo' => '2202', 'Descricao' => 'Capitulo incompleto'],
                    ['Codigo' => '22021000', 'Descricao' => 'Aguas incluindo as aguas minerais'],
                ],
            ], 200),
        ]);

        $this->actingAs($user)
            ->post(route('produtos.sincronizar-ncm'))
            ->assertRedirect(route('produtos.index'))
            ->assertSessionHas('sucesso');

        $this->assertDatabaseHas('ncms', ['ncm' => '22011000', 'descricao' => 'AGUAS MINERAIS']);
        $this->assertDatabaseHas('ncms', ['ncm' => '22021000']);
        $this->assertSame(2, Ncm::count());
    }

    public function test_autocomplete_ncm_retorna_sugestoes(): void
    {
        [, , $user] = $this->criarUsuarioEstoque();

        Ncm::create([
            'ncm' => '22011000',
            'ex' => '',
            'descricao' => 'AGUAS MINERAIS',
            'fonte' => 'API',
        ]);

        $this->actingAs($user)
            ->getJson(route('produtos.ncm-autocomplete', ['busca' => '2201']))
            ->assertOk()
            ->assertJsonFragment(['ncm' => '22011000']);
    }

    protected function criarUsuarioEstoque(): array
    {
        $empresa = Empresa::factory()->create();
        $unidade = Unidade::factory()->create(['empresa_id' => $empresa->id]);
        $user = User::factory()->create(['empresa_id' => $empresa->id, 'unidade_id' => $unidade->id]);

        foreach (['estoque.visualizar', 'estoque.criar'] as $nome) {
            Permission::findOrCreate($nome, 'web');
        }
        $user->givePermissionTo(['estoque.visualizar', 'estoque.criar']);
        Gate::before(fn () => true);

        return [$empresa, $unidade, $user];
    }
}
