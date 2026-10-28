<?php

namespace Tests\Feature;

use App\Models\Carteirinha;
use App\Models\Cliente;
use App\Models\Dependente;
use App\Models\Empresa;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class DependenteEdicaoTest extends TestCase
{
    use RefreshDatabase;

    protected Empresa $empresa;

    protected Cliente $titular;

    protected Dependente $dependente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->empresa = Empresa::factory()->create();
        $unidade = Unidade::factory()->create(['empresa_id' => $this->empresa->id]);
        $this->titular = Cliente::factory()->create(['empresa_id' => $this->empresa->id, 'unidade_id' => $unidade->id, 'nome' => 'PAULA REGIA']);
        $this->dependente = Dependente::create([
            'cliente_id' => $this->titular->id, 'nome' => 'MARIA CLRA VIERA', 'parentesco' => Dependente::niveisParentesco()[0],
            'data_nascimento' => '2015-05-10', 'status' => 'ativo',
        ]);
        Gate::before(fn () => true);
        $this->actingAs(User::factory()->create(['empresa_id' => $this->empresa->id]));
    }

    public function test_tela_do_cliente_mostra_editar_dependente(): void
    {
        $this->get(route('clientes.show', $this->titular))
            ->assertOk()
            ->assertSee('editar')
            ->assertSee(route('clientes.dependentes.update', [$this->titular, $this->dependente]), false)
            ->assertSee('Salvar alterações');
    }

    public function test_corrige_nome_cpf_data_e_parentesco(): void
    {
        $carteirinha = Carteirinha::create(['dependente_id' => $this->dependente->id, 'codigo' => 'CART-DEP-1', 'status' => 'ativa', 'emitida_em' => now()]);
        $parentesco = Dependente::niveisParentesco()[1];

        $this->put(route('clientes.dependentes.update', [$this->titular, $this->dependente]), [
            'nome' => 'MARIA CLARA VIEIRA DOS SANTOS SILVA',
            'cpf' => '529.982.247-25',
            'data_nascimento' => '2014-03-02',
            'parentesco' => $parentesco,
        ])->assertRedirect(route('clientes.show', $this->titular))->assertSessionHas('sucesso');

        $this->dependente->refresh();
        $this->assertSame('MARIA CLARA VIEIRA DOS SANTOS SILVA', $this->dependente->nome);
        $this->assertSame('52998224725', $this->dependente->cpf);
        $this->assertSame('2014-03-02', $this->dependente->data_nascimento->toDateString());
        $this->assertSame($parentesco, $this->dependente->parentesco);

        // A carteirinha acompanha o nome corrigido.
        $this->assertSame('MARIA CLARA VIEIRA DOS SANTOS SILVA', $carteirinha->fresh()->titular()->nome);
    }

    public function test_cpf_invalido_reabre_o_formulario_do_dependente_com_erro(): void
    {
        $this->from(route('clientes.show', $this->titular))
            ->put(route('clientes.dependentes.update', [$this->titular, $this->dependente]), [
                'nome' => 'MARIA CLARA', 'cpf' => '111.111.111-11', 'data_nascimento' => '2015-05-10',
                'parentesco' => Dependente::niveisParentesco()[0],
            ])
            ->assertRedirect(route('clientes.show', $this->titular))
            ->assertSessionHasErrorsIn('dependente'.$this->dependente->id, 'cpf');

        $this->assertSame('MARIA CLRA VIERA', $this->dependente->fresh()->nome);
        $this->get(route('clientes.show', $this->titular))->assertSee('CPF inválido')->assertSee('editando: true', false);
    }

    public function test_parentesco_antigo_abre_na_opcao_certa_e_nao_e_trocado(): void
    {
        $this->assertSame('Cônjuge', (new Dependente(['parentesco' => 'ESPOSA']))->parentescoNaLista());
        $this->assertSame('Filho(a)', (new Dependente(['parentesco' => 'FILHA']))->parentescoNaLista());
        $this->assertSame('Filho(a)', (new Dependente(['parentesco' => 'filho']))->parentescoNaLista());
        $this->assertSame('Irmão(ã)', (new Dependente(['parentesco' => 'IRMÃ']))->parentescoNaLista());
        $this->assertNull((new Dependente(['parentesco' => 'AFILHADO DO VIZINHO']))->parentescoNaLista());

        // "ESPOSA" abre selecionando Cônjuge (antes abria em Filho(a)).
        $this->dependente->update(['parentesco' => 'ESPOSA']);
        $this->get(route('clientes.show', $this->titular))->assertSee('<option value="Cônjuge" selected', false);

        // Desconhecido: continua como opção "(atual)" e é aceito ao salvar.
        $this->dependente->update(['parentesco' => 'AFILHADO DO VIZINHO']);
        $this->get(route('clientes.show', $this->titular))->assertSee('AFILHADO DO VIZINHO (atual)');
        $this->put(route('clientes.dependentes.update', [$this->titular, $this->dependente]), [
            'nome' => 'MARIA CLARA', 'data_nascimento' => '2015-05-10', 'parentesco' => 'AFILHADO DO VIZINHO',
        ])->assertSessionHas('sucesso');
        $this->assertSame('AFILHADO DO VIZINHO', $this->dependente->fresh()->parentesco);
    }

    public function test_nao_edita_nem_remove_dependente_de_outro_cliente(): void
    {
        $outro = Cliente::factory()->create(['empresa_id' => $this->empresa->id, 'unidade_id' => $this->titular->unidade_id]);

        $this->put(route('clientes.dependentes.update', [$outro, $this->dependente]), [
            'nome' => 'X', 'data_nascimento' => '2015-05-10', 'parentesco' => Dependente::niveisParentesco()[0],
        ])->assertNotFound();
        $this->delete(route('clientes.dependentes.destroy', [$outro, $this->dependente]))->assertNotFound();

        $this->assertDatabaseHas('dependentes', ['id' => $this->dependente->id, 'nome' => 'MARIA CLRA VIERA']);
    }

    public function test_plano_sem_dependentes_avisa_em_vez_de_dar_erro(): void
    {
        $plano = \App\Models\Plano::factory()->create(['empresa_id' => $this->empresa->id, 'nome' => 'Plano Individual', 'max_dependentes' => 0]);
        \App\Models\Contrato::create([
            'empresa_id' => $this->empresa->id, 'unidade_id' => $this->titular->unidade_id, 'cliente_id' => $this->titular->id,
            'plano_id' => $plano->id, 'data_inicio' => now(), 'dia_vencimento' => 10, 'valor_mensal' => 100, 'status' => 'ativo',
        ]);

        $this->get(route('clientes.show', $this->titular))->assertSee('não inclui dependentes');

        $this->from(route('clientes.show', $this->titular))
            ->post(route('clientes.dependentes.store', $this->titular), [
                'nome' => 'NOVO DEP', 'data_nascimento' => '2018-01-01', 'parentesco' => Dependente::niveisParentesco()[0],
            ])
            ->assertRedirect(route('clientes.show', $this->titular))
            ->assertSessionHas('erro', fn ($e) => str_contains($e, '"Plano Individual" do cliente não inclui dependentes'));

        $this->assertDatabaseMissing('dependentes', ['nome' => 'NOVO DEP']);
    }

    public function test_adicionar_aceita_cpf_e_guarda_so_os_digitos(): void
    {
        $this->post(route('clientes.dependentes.store', $this->titular), [
            'nome' => 'LUCAS SILVA', 'cpf' => '529.982.247-25', 'data_nascimento' => '2018-01-01',
            'parentesco' => Dependente::niveisParentesco()[0],
        ])->assertSessionHas('sucesso');

        $this->assertDatabaseHas('dependentes', ['nome' => 'LUCAS SILVA', 'cpf' => '52998224725']);
    }
}
