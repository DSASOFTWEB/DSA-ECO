<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class HospedagemReservaClienteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_nova_reserva_oferece_cadastro_rapido_de_hospede(): void
    {
        [, , $operador] = $this->cenario();

        $this->actingAs($operador)
            ->get(route('hospedagens.create'))
            ->assertOk()
            ->assertSee('Novo cliente')
            ->assertSee('Nenhum cliente encontrado', false)
            ->assertSee('Cadastrar novo cliente')
            ->assertSee('Salvar e selecionar')
            ->assertSee(route('clientes.store'), false);
    }

    public function test_cadastro_rapido_devolve_cliente_para_selecionar(): void
    {
        [$empresa, , $operador] = $this->cenario();

        $resposta = $this->actingAs($operador)
            ->postJson(route('clientes.store'), ['nome' => 'Hóspede Novo', 'cpf' => '529.982.247-25'])
            ->assertCreated()
            ->assertJsonPath('nome', 'Hóspede Novo');

        $this->assertDatabaseHas('clientes', ['id' => $resposta->json('id'), 'empresa_id' => $empresa->id]);
    }

    public function test_hospede_escolhido_nao_se_perde_quando_a_reserva_volta_com_erro(): void
    {
        [$empresa, $unidade, $operador] = $this->cenario();
        $cliente = Cliente::factory()->create(['empresa_id' => $empresa->id, 'unidade_id' => $unidade->id, 'nome' => 'Maria Silva Teste']);

        $this->actingAs($operador)
            ->from(route('hospedagens.create'))
            ->post(route('hospedagens.store'), ['cliente_id' => $cliente->id]) // sem quarto/datas
            ->assertRedirect(route('hospedagens.create'))
            ->assertSessionHasErrors('quarto_id');

        $this->get(route('hospedagens.create'))
            ->assertOk()
            ->assertSee('Maria Silva Teste');
    }

    protected function cenario(): array
    {
        $empresa = Empresa::factory()->create();
        $unidade = Unidade::factory()->create(['empresa_id' => $empresa->id]);
        $operador = User::factory()->create(['empresa_id' => $empresa->id, 'unidade_id' => $unidade->id]);
        Gate::before(fn () => true);

        return [$empresa, $unidade, $operador];
    }
}
