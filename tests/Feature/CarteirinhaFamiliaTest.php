<?php

namespace Tests\Feature;

use App\Models\Carteirinha;
use App\Models\Cliente;
use App\Models\Dependente;
use App\Models\Empresa;
use App\Models\Unidade;
use App\Models\User;
use App\Services\Relatorios\CarteirinhaPdfExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class CarteirinhaFamiliaTest extends TestCase
{
    use RefreshDatabase;

    protected Empresa $empresa;

    protected Unidade $unidade;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = Empresa::factory()->create();
        $this->unidade = Unidade::factory()->create(['empresa_id' => $this->empresa->id]);
        Gate::before(fn () => true);
        $this->actingAs(User::factory()->create(['empresa_id' => $this->empresa->id]));
    }

    public function test_busca_pelo_titular_traz_tambem_os_dependentes(): void
    {
        [$titular, $cTitular, $cFilha, $cFilho] = $this->familia('JOAO BATISTA FERREIRA', '529.982.247-25');
        [, $cOutro] = $this->familia('PEDRO ALVES', '111.444.777-35');

        $resposta = $this->get(route('carteirinhas.index', ['busca' => 'JOAO BATISTA']))->assertOk();

        $resposta->assertSee($cTitular->codigo)->assertSee($cFilha->codigo)->assertSee($cFilho->codigo)
            ->assertDontSee($cOutro->codigo)
            ->assertSeeInOrder([$cTitular->codigo, $cFilha->codigo, $cFilho->codigo]) // titular primeiro
            ->assertSee('Imprimir família (3)');

        // Pelo CPF do titular também.
        $this->get(route('carteirinhas.index', ['busca' => '52998224725']))->assertSee($cFilha->codigo)->assertDontSee($cOutro->codigo);

        // Buscar o dependente pelo nome continua trazendo só ele.
        $this->get(route('carteirinhas.index', ['busca' => 'MARIA']))->assertSee($cFilha->codigo)->assertDontSee($cFilho->codigo);
    }

    public function test_imprime_a_familia_num_pdf_so(): void
    {
        [, $cTitular, $cFilha, $cFilho] = $this->familia('JOAO BATISTA FERREIRA', '529.982.247-25');

        $this->get(route('carteirinhas.imprimir-lote', ['ids' => implode(',', [$cTitular->id, $cFilha->id, $cFilho->id])]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_fonte_do_nome_diminui_para_caber_o_nome_completo(): void
    {
        $this->assertSame(10.5, CarteirinhaPdfExport::fonteDoNome('ANA LIMA'));
        $medio = CarteirinhaPdfExport::fonteDoNome('MARIA APARECIDA DOS SANTOS'); // 26 letras
        $this->assertLessThan(10.5, $medio);
        $this->assertGreaterThanOrEqual(8, $medio);

        $longo = CarteirinhaPdfExport::fonteDoNome('MARIA APARECIDA DOS SANTOS OLIVEIRA DE ALBUQUERQUE'); // 2 linhas
        $this->assertGreaterThanOrEqual(6.5, $longo);
        $this->assertLessThanOrEqual(9, $longo);

        // Cabe: largura estimada por linha (2 linhas no longo) ≤ área do nome (~167pt).
        $this->assertLessThanOrEqual(167 * 2, mb_strlen('MARIA APARECIDA DOS SANTOS OLIVEIRA DE ALBUQUERQUE') * 0.7 * $longo);
    }

    /** @return array{0: Cliente, 1: Carteirinha, 2: Carteirinha, 3: Carteirinha} */
    protected function familia(string $nome, string $cpf): array
    {
        $titular = Cliente::factory()->create(['empresa_id' => $this->empresa->id, 'unidade_id' => $this->unidade->id, 'nome' => $nome, 'cpf' => preg_replace('/\D/', '', $cpf)]);
        $cTitular = Carteirinha::create(['cliente_id' => $titular->id, 'codigo' => 'CT-'.$titular->id, 'status' => 'ativa', 'emitida_em' => now()]);

        $cartoes = [];
        foreach (['MARIA', 'LUCAS'] as $i => $primeiroNome) {
            $dep = Dependente::create(['cliente_id' => $titular->id, 'nome' => "{$primeiroNome} {$titular->id} FERREIRA", 'parentesco' => 'filho', 'data_nascimento' => '2015-05-10', 'status' => 'ativo']);
            $cartoes[] = Carteirinha::create(['dependente_id' => $dep->id, 'codigo' => "CD-{$titular->id}-{$i}", 'status' => 'ativa', 'emitida_em' => now()]);
        }

        return [$titular, $cTitular, ...$cartoes];
    }
}
