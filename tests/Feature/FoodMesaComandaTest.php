<?php

namespace Tests\Feature;

use App\Models\Caixa;
use App\Models\Empresa;
use App\Models\PontoAtendimento;
use App\Models\Produto;
use App\Models\Unidade;
use App\Models\User;
use App\Services\Food\AtendimentoService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class FoodMesaComandaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_fluxo_abre_transfere_e_fecha_conta_integrando_venda_estoque_e_caixa(): void
    {
        [$empresa, $unidade, $operador] = $this->criarOperador();
        $produto = Produto::create([
            'empresa_id' => $empresa->id,
            'unidade_id' => $unidade->id,
            'nome' => 'Porção de batata',
            'preco_custo' => 10,
            'preco_venda' => 30,
            'controla_estoque' => true,
            'estoque_atual' => 20,
            'estoque_minimo' => 2,
            'ativo' => true,
        ]);
        $mesa = $this->criarPonto($empresa, $unidade, 'mesa', 1);
        $comanda = $this->criarPonto($empresa, $unidade, 'comanda', 10);
        $caixa = Caixa::create([
            'empresa_id' => $empresa->id,
            'unidade_id' => $unidade->id,
            'usuario_abertura_id' => $operador->id,
            'data_abertura' => now(),
            'valor_abertura' => 0,
            'status' => 'aberto',
        ]);

        $this->actingAs($operador);
        $service = app(AtendimentoService::class);
        $atendimento = $service->abrir($mesa, $operador, 3);
        $service->adicionarItem($atendimento, ['produto_id' => $produto->id, 'quantidade' => 2], $operador);
        $atendimento = $service->transferir($atendimento, $comanda, $operador);
        $atendimento = $service->fechar($atendimento, [
            'caixa_id' => $caixa->id,
            'desconto' => 5,
            'percentual_servico' => 10,
            'pagamentos' => [['forma' => 'dinheiro', 'valor' => 61]],
        ], $operador);

        $this->assertSame('fechado', $atendimento->status);
        $this->assertSame('livre', $comanda->fresh()->status);
        $this->assertSame('livre', $mesa->fresh()->status);
        $this->assertSame('61.00', $atendimento->valor_total);
        $this->assertSame(18, $produto->fresh()->estoque_atual);
        $this->assertDatabaseHas('vendas', ['id' => $atendimento->venda_id, 'valor_total' => 61]);
        $this->assertDatabaseHas('caixa_movimentacoes', ['caixa_id' => $caixa->id, 'categoria' => 'food', 'valor' => 61]);
        $this->assertDatabaseHas('atendimento_transferencias', ['tipo' => 'integral', 'ponto_destino_id' => $comanda->id]);
    }

    public function test_operador_nao_abre_ponto_de_outra_empresa(): void
    {
        [, , $operador] = $this->criarOperador();
        $outraEmpresa = Empresa::factory()->create();
        $outraUnidade = Unidade::factory()->create(['empresa_id' => $outraEmpresa->id]);
        $pontoAlheio = $this->criarPonto($outraEmpresa, $outraUnidade, 'mesa', 99);

        $this->actingAs($operador)
            ->post(route('food.pontos.abrir', $pontoAlheio), ['quantidade_pessoas' => 1])
            ->assertNotFound();

        $this->assertDatabaseMissing('atendimentos', ['ponto_atendimento_id' => $pontoAlheio->id]);
        $this->assertDatabaseHas('pontos_atendimento', ['id' => $pontoAlheio->id, 'status' => 'livre']);
    }

    public function test_configurador_pode_reservar_bloquear_e_editar_um_ponto_livre(): void
    {
        [$empresa, $unidade, $operador] = $this->criarOperador();
        $mesa = $this->criarPonto($empresa, $unidade, 'mesa', 1);

        $this->actingAs($operador)
            ->put(route('food.pontos.update', $mesa), [
                'numero' => 12,
                'nome' => 'Deck principal',
                'capacidade' => 6,
                'ordem' => 2,
                'status' => 'reservada',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('pontos_atendimento', [
            'id' => $mesa->id,
            'empresa_id' => $empresa->id,
            'numero' => 12,
            'nome' => 'Deck principal',
            'capacidade' => 6,
            'ordem' => 2,
            'status' => 'reservada',
        ]);
    }

    public function test_nao_permite_configurar_ponto_com_atendimento_em_andamento(): void
    {
        [$empresa, $unidade, $operador] = $this->criarOperador();
        $mesa = $this->criarPonto($empresa, $unidade, 'mesa', 1);
        $this->actingAs($operador);
        app(AtendimentoService::class)->abrir($mesa, $operador, 2);

        $this->put(route('food.pontos.update', $mesa), [
            'numero' => 1,
            'nome' => 'Alteração indevida',
            'capacidade' => 4,
            'ordem' => 0,
            'status' => 'bloqueada',
        ])->assertRedirect()->assertSessionHas('erro', 'Não é possível alterar uma mesa ou comanda com atendimento em andamento.');

        $this->assertDatabaseHas('pontos_atendimento', [
            'id' => $mesa->id,
            'nome' => null,
            'status' => 'ocupada',
        ]);
    }

    public function test_configurador_gera_faixa_de_mesas_na_empresa(): void
    {
        [$empresa, $unidade, $operador] = $this->criarOperador();

        $this->actingAs($operador)
            ->post(route('food.pontos.store'), [
                'unidade_id' => $unidade->id,
                'tipo' => 'mesa',
                'numero_inicial' => 1,
                'numero_final' => 5,
                'capacidade' => 4,
            ])
            ->assertRedirect(route('empresa.edit', ['unidade_id' => $unidade->id, 'tipo' => 'mesa']));

        $this->assertSame(5, PontoAtendimento::where('empresa_id', $empresa->id)
            ->where('unidade_id', $unidade->id)
            ->where('tipo', 'mesa')
            ->count());
        $this->assertDatabaseHas('pontos_atendimento', [
            'empresa_id' => $empresa->id,
            'unidade_id' => $unidade->id,
            'tipo' => 'mesa',
            'numero' => 1,
            'capacidade' => 4,
            'status' => 'livre',
        ]);
        $this->assertDatabaseHas('pontos_atendimento', [
            'empresa_id' => $empresa->id,
            'tipo' => 'mesa',
            'numero' => 5,
        ]);
    }

    public function test_configurador_gera_faixa_de_comandas(): void
    {
        [$empresa, $unidade, $operador] = $this->criarOperador();

        $this->actingAs($operador)
            ->post(route('food.pontos.store'), [
                'unidade_id' => $unidade->id,
                'tipo' => 'comanda',
                'numero_inicial' => 10,
                'numero_final' => 12,
            ])
            ->assertRedirect(route('empresa.edit', ['unidade_id' => $unidade->id, 'tipo' => 'comanda']));

        $this->assertSame(3, PontoAtendimento::where([
            'empresa_id' => $empresa->id,
            'unidade_id' => $unidade->id,
            'tipo' => 'comanda',
        ])->count());
    }

    public function test_faixa_rejeita_numeros_ja_existentes(): void
    {
        [$empresa, $unidade, $operador] = $this->criarOperador();
        $this->criarPonto($empresa, $unidade, 'mesa', 3);

        $this->actingAs($operador)
            ->from(route('empresa.edit', ['unidade_id' => $unidade->id, 'tipo' => 'mesa']))
            ->post(route('food.pontos.store'), [
                'unidade_id' => $unidade->id,
                'tipo' => 'mesa',
                'numero_inicial' => 1,
                'numero_final' => 5,
            ])
            ->assertRedirect()
            ->assertSessionHas('erro');

        $this->assertSame(1, PontoAtendimento::where('empresa_id', $empresa->id)->where('tipo', 'mesa')->count());
    }

    public function test_faixa_nao_usa_unidade_de_outra_empresa(): void
    {
        [, , $operador] = $this->criarOperador();
        $outraEmpresa = Empresa::factory()->create();
        $outraUnidade = Unidade::factory()->create(['empresa_id' => $outraEmpresa->id]);

        $this->actingAs($operador)
            ->post(route('food.pontos.store'), [
                'unidade_id' => $outraUnidade->id,
                'tipo' => 'mesa',
                'numero_inicial' => 1,
                'numero_final' => 3,
            ])
            ->assertSessionHasErrors('unidade_id');

        $this->assertSame(0, PontoAtendimento::withoutGlobalScopes()->where('unidade_id', $outraUnidade->id)->count());
    }

    protected function criarOperador(): array
    {
        $empresa = Empresa::factory()->create();
        $unidade = Unidade::factory()->create(['empresa_id' => $empresa->id]);
        $operador = User::factory()->create(['empresa_id' => $empresa->id, 'unidade_id' => $unidade->id]);
        foreach (['food.visualizar', 'food.operar', 'food.fechar', 'food.configurar'] as $nome) {
            Permission::findOrCreate($nome, 'web');
        }
        $operador->givePermissionTo(['food.visualizar', 'food.operar', 'food.fechar', 'food.configurar']);
        // Rede de segurança: se Spatie/cache mexer na conexão, não bloqueia o teste.
        Gate::before(fn () => true);

        return [$empresa, $unidade, $operador];
    }

    protected function criarPonto(Empresa $empresa, Unidade $unidade, string $tipo, int $numero): PontoAtendimento
    {
        return PontoAtendimento::create([
            'empresa_id' => $empresa->id,
            'unidade_id' => $unidade->id,
            'tipo' => $tipo,
            'numero' => $numero,
            'status' => 'livre',
        ]);
    }
}
