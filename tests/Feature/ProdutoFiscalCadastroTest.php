<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Produto;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProdutoFiscalCadastroTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_cadastra_produto_com_campos_nfce(): void
    {
        [, , $user] = $this->criarUsuarioEstoque();

        $this->actingAs($user)
            ->post(route('produtos.store'), [
                'nome' => 'Água mineral 500ml',
                'tipo_item' => 'produto',
                'ean' => '7891000100103',
                'unidade_comercial' => 'UN',
                'ncm' => '22011000',
                'cest' => '0300100',
                'cfop' => '5102',
                'origem' => 0,
                'csosn' => '102',
                'cst_pis' => '49',
                'cst_cofins' => '49',
                'preco_custo' => 1.5,
                'preco_venda' => 4.5,
                'controla_estoque' => 1,
                'estoque_atual' => 10,
                'estoque_minimo' => 2,
                'ativo' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('produtos', [
            'nome' => 'Água mineral 500ml',
            'tipo_item' => 'produto',
            'ean' => '7891000100103',
            'ncm' => '22011000',
            'cfop' => '5102',
            'csosn' => '102',
        ]);
    }

    public function test_cadastra_servico_com_campos_nfse_nacional(): void
    {
        [, , $user] = $this->criarUsuarioEstoque();

        $this->actingAs($user)
            ->post(route('produtos.store'), [
                'nome' => 'Aluguel de cadeira',
                'tipo_item' => 'servico',
                'unidade_comercial' => 'UN',
                'codigo_servico_lc116' => '17.05',
                'codigo_tributacao_municipal' => '1705',
                'cnae_servico' => '7739099',
                'nbs' => '123456789',
                'aliq_iss' => 5,
                'iss_retido' => 0,
                'preco_custo' => 0,
                'preco_venda' => 25,
                'controla_estoque' => 0,
                'ativo' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('produtos', [
            'nome' => 'Aluguel de cadeira',
            'tipo_item' => 'servico',
            'codigo_servico_lc116' => '17.05',
            'cnae_servico' => '7739099',
            'nbs' => '123456789',
        ]);
    }

    public function test_consulta_ean_cosmos_preenche_payload(): void
    {
        config(['parque.cosmos_token' => 'token-teste']);
        [, , $user] = $this->criarUsuarioEstoque();

        Http::fake([
            'api.cosmos.bluesoft.com.br/*' => Http::response([
                'gtin' => '7891000100103',
                'description' => 'PRODUTO COSMOS TESTE',
                'brand' => ['name' => 'Marca X'],
                'ncm' => ['code' => '22011000'],
                'thumbnail' => 'https://cdn.example.com/produto.jpg',
            ], 200),
        ]);

        $this->actingAs($user)
            ->getJson(route('produtos.consultar-ean', ['ean' => '7891000100103']))
            ->assertOk()
            ->assertJsonPath('nome', 'PRODUTO COSMOS TESTE')
            ->assertJsonPath('ncm', '22011000')
            ->assertJsonPath('imagem_url', 'https://cdn.example.com/produto.jpg')
            ->assertJsonPath('marca', 'Marca X');
    }

    public function test_consulta_ean_sem_token_retorna_422(): void
    {
        config(['parque.cosmos_token' => '']);
        [, , $user] = $this->criarUsuarioEstoque();

        $this->actingAs($user)
            ->getJson(route('produtos.consultar-ean', ['ean' => '7891000100103']))
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Token Cosmos não configurado na empresa nem em COSMOS_TOKEN (.env).']);
    }

    public function test_rejeita_ncm_invalido(): void
    {
        [, , $user] = $this->criarUsuarioEstoque();

        $this->actingAs($user)
            ->post(route('produtos.store'), [
                'nome' => 'Item inválido',
                'tipo_item' => 'produto',
                'unidade_comercial' => 'UN',
                'ncm' => '123',
                'preco_custo' => 1,
                'preco_venda' => 2,
                'ativo' => 1,
            ])
            ->assertSessionHasErrors('ncm');
    }

    protected function criarUsuarioEstoque(): array
    {
        $empresa = Empresa::factory()->create();
        $unidade = Unidade::factory()->create(['empresa_id' => $empresa->id]);
        $user = User::factory()->create(['empresa_id' => $empresa->id, 'unidade_id' => $unidade->id]);

        foreach (['estoque.visualizar', 'estoque.criar', 'estoque.editar', 'estoque.excluir', 'estoque.ajustar'] as $nome) {
            Permission::findOrCreate($nome, 'web');
        }
        $user->givePermissionTo(['estoque.visualizar', 'estoque.criar', 'estoque.editar', 'estoque.excluir', 'estoque.ajustar']);
        Gate::before(fn () => true);

        return [$empresa, $unidade, $user];
    }
}
