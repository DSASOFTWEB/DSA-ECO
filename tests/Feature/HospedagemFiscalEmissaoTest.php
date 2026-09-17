<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Hospedagem;
use App\Models\HospedagemConsumo;
use App\Models\Produto;
use App\Models\Quarto;
use App\Models\Unidade;
use App\Models\User;
use App\Services\Fiscal\HospedagemFiscalService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class HospedagemFiscalEmissaoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_separa_itens_produto_para_nfce_e_servicos_para_nfse(): void
    {
        [$empresa, $unidade, $user] = $this->criarOperador();
        $hospedagem = $this->criarHospedagem($empresa, $unidade, $user);

        $produto = Produto::create([
            'empresa_id' => $empresa->id,
            'unidade_id' => $unidade->id,
            'nome' => 'Refrigerante',
            'tipo_item' => 'produto',
            'ncm' => '22021000',
            'preco_custo' => 2,
            'preco_venda' => 6,
            'controla_estoque' => false,
            'ativo' => true,
        ]);
        $servico = Produto::create([
            'empresa_id' => $empresa->id,
            'unidade_id' => $unidade->id,
            'nome' => 'Lavanderia',
            'tipo_item' => 'servico',
            'codigo_servico_lc116' => '14.01',
            'preco_custo' => 0,
            'preco_venda' => 40,
            'controla_estoque' => false,
            'ativo' => true,
        ]);

        HospedagemConsumo::create([
            'hospedagem_id' => $hospedagem->id,
            'produto_id' => $produto->id,
            'quantidade' => 2,
            'valor_unitario' => 6,
            'subtotal' => 12,
            'registrado_por_id' => $user->id,
        ]);
        HospedagemConsumo::create([
            'hospedagem_id' => $hospedagem->id,
            'produto_id' => $servico->id,
            'quantidade' => 1,
            'valor_unitario' => 40,
            'subtotal' => 40,
            'registrado_por_id' => $user->id,
        ]);
        HospedagemConsumo::create([
            'hospedagem_id' => $hospedagem->id,
            'produto_id' => null,
            'descricao' => 'Taxa late checkout',
            'quantidade' => 1,
            'valor_unitario' => 50,
            'subtotal' => 50,
            'registrado_por_id' => $user->id,
        ]);

        $service = app(HospedagemFiscalService::class);
        $nfce = $service->itensProdutos($hospedagem->fresh(['consumos.produto']));
        $nfse = $service->itensServicos($hospedagem->fresh(['consumos.produto', 'quarto']));

        $this->assertCount(1, $nfce);
        $this->assertSame('Refrigerante', $nfce[0]['descricao']);
        $this->assertTrue(collect($nfse)->contains(fn ($i) => ($i['tipo'] ?? '') === 'diaria'));
        $this->assertTrue(collect($nfse)->contains(fn ($i) => ($i['descricao'] ?? '') === 'Lavanderia'));
        $this->assertTrue(collect($nfse)->contains(fn ($i) => ($i['descricao'] ?? '') === 'Taxa late checkout'));
    }

    public function test_botoes_exigem_certificado_ao_emitir_nfce(): void
    {
        [$empresa, $unidade, $user] = $this->criarOperador();
        $hospedagem = $this->criarHospedagem($empresa, $unidade, $user);
        $produto = Produto::create([
            'empresa_id' => $empresa->id,
            'unidade_id' => $unidade->id,
            'nome' => 'Água',
            'tipo_item' => 'produto',
            'ncm' => '22011000',
            'preco_custo' => 1,
            'preco_venda' => 4,
            'controla_estoque' => false,
            'ativo' => true,
        ]);
        HospedagemConsumo::create([
            'hospedagem_id' => $hospedagem->id,
            'produto_id' => $produto->id,
            'quantidade' => 1,
            'valor_unitario' => 4,
            'subtotal' => 4,
            'registrado_por_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->post(route('hospedagens.emitir-nfce', $hospedagem))
            ->assertRedirect()
            ->assertSessionHas('erro');
    }

    public function test_nao_emite_nfse_sem_diaria_nem_servico(): void
    {
        [$empresa, $unidade, $user] = $this->criarOperador();
        $hospedagem = $this->criarHospedagem($empresa, $unidade, $user, valorDiaria: 0);

        $this->actingAs($user)
            ->post(route('hospedagens.emitir-nfse', $hospedagem))
            ->assertRedirect()
            ->assertSessionHas('erro');
    }

    public function test_mapa_exibe_modal_de_reserva_e_acoes_da_hospedagem(): void
    {
        [$empresa, $unidade, $user] = $this->criarOperador();
        $this->criarHospedagem($empresa, $unidade, $user);

        Quarto::create([
            'empresa_id' => $empresa->id,
            'unidade_id' => $unidade->id,
            'numero' => '102',
            'capacidade_maxima' => 4,
            'valor_diaria' => 220,
            'status' => 'ativo',
        ]);

        $this->actingAs($user)
            ->get(route('hospedagens.mapa'))
            ->assertOk()
            ->assertSee('Reservar')
            ->assertSee('Confirmar reserva')
            ->assertSee('Ver')
            ->assertSee('Imprimir ficha')
            ->assertSee('Fechar conta');
    }

    protected function criarHospedagem(Empresa $empresa, Unidade $unidade, User $user, float $valorDiaria = 150): Hospedagem
    {
        $cliente = Cliente::factory()->create(['empresa_id' => $empresa->id, 'unidade_id' => $unidade->id]);
        $quarto = Quarto::create([
            'empresa_id' => $empresa->id,
            'unidade_id' => $unidade->id,
            'numero' => '101',
            'capacidade_maxima' => 2,
            'valor_diaria' => $valorDiaria,
            'status' => 'ativo',
        ]);

        return Hospedagem::create([
            'empresa_id' => $empresa->id,
            'unidade_id' => $unidade->id,
            'quarto_id' => $quarto->id,
            'cliente_id' => $cliente->id,
            'quantidade_hospedes' => 1,
            'quantidade_adultos' => 1,
            'quantidade_criancas' => 0,
            'quantidade_isentos' => 0,
            'valor_diaria' => $valorDiaria,
            'data_checkin_prevista' => now()->toDateString(),
            'data_checkout_prevista' => now()->addDay()->toDateString(),
            'data_checkin_real' => now(),
            'status' => 'hospedado',
            'registrado_por_id' => $user->id,
        ]);
    }

    protected function criarOperador(): array
    {
        $empresa = Empresa::factory()->create([
            'codigo_municipio_ibge' => '3550308',
            'csc' => 'ABC',
            'csc_id' => '000001',
            'nfse_nacional_habilitado' => true,
        ]);
        $unidade = Unidade::factory()->create([
            'empresa_id' => $empresa->id,
            'uf' => 'SP',
            'cidade' => 'Sao Paulo',
            'cep' => '01001000',
            'endereco' => 'Rua A',
            'numero' => '100',
            'bairro' => 'Centro',
        ]);
        $user = User::factory()->create(['empresa_id' => $empresa->id, 'unidade_id' => $unidade->id]);

        foreach (['pousada.visualizar', 'pousada.checkout', 'pousada.consumos'] as $nome) {
            Permission::findOrCreate($nome, 'web');
        }
        $user->givePermissionTo(['pousada.visualizar', 'pousada.checkout', 'pousada.consumos']);
        Gate::before(fn () => true);

        return [$empresa, $unidade, $user];
    }
}
