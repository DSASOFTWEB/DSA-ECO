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

    public function test_whatsapp_sem_evolution_avisa_e_nao_abre_wa_me(): void
    {
        config(['evolution.base_url' => '', 'evolution.api_key' => '', 'evolution.instance' => '']);
        [$titular, $cTitular] = $this->familia('JOAO BATISTA FERREIRA', '529.982.247-25');
        $titular->update(['whatsapp' => '(83) 99999-1234']);

        $this->get(route('carteirinhas.index', ['busca' => 'JOAO']))->assertOk()->assertSee('WhatsApp')->assertSee('(83) 99999-1234')->assertSee('Família inteira (3)');
        $this->get(route('carteirinhas.show', $cTitular))->assertOk()
            ->assertSee('Enviar pelo WhatsApp')
            ->assertSee('WhatsApp da empresa não configurado');

        $resposta = $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->from(route('carteirinhas.show', $cTitular))
            ->post(route('carteirinhas.whatsapp', $cTitular), ['telefone' => '(83) 99999-1234', 'familia' => 1])
            ->assertRedirect(route('carteirinhas.show', $cTitular))
            ->assertSessionHas('erro', fn ($e) => str_contains($e, 'Evolution API) não configurado'));
        $this->assertStringNotContainsString('wa.me', (string) $resposta->headers->get('Location'));
    }

    public function test_usa_a_evolution_configurada_na_empresa_e_mostra_o_erro_da_api(): void
    {
        config(['evolution.base_url' => '', 'evolution.api_key' => '', 'evolution.instance' => '']);
        [, $cTitular] = $this->familia('JOAO BATISTA FERREIRA', '529.982.247-25');
        $empresa = \App\Models\Empresa::findOrFail($cTitular->cliente->empresa_id);
        // Configurada pela tela (Minha Empresa → Gerencial), não no .env.
        $empresa->update(['configuracoes' => ['evolution' => ['base_url' => 'http://127.0.0.1:9', 'api_key' => 'chave', 'instance' => 'pousada']]]);

        $this->get(route('carteirinhas.show', $cTitular))->assertSee('Vai como arquivo PDF pelo WhatsApp da empresa.');

        // API fora do ar: avisa o motivo (antes caía calado no wa.me).
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post(route('carteirinhas.whatsapp', $cTitular), ['telefone' => '83988887777'])
            ->assertSessionHas('erro', fn ($e) => str_contains($e, 'Não foi possível enviar pelo WhatsApp para +5583988887777'));
    }

    public function test_whatsapp_com_integracao_envia_o_pdf_como_arquivo(): void
    {
        [$titular, $cTitular, $cFilha] = $this->familia('JOAO BATISTA FERREIRA', '529.982.247-25');
        $whatsapp = new class extends \App\Services\Integrations\EvolutionApiService
        {
            public array $enviados = [];

            public function __construct() {}

            public function configurada(): bool { return true; }

            public function enviarDocumento(string $numero, string $base64, string $nomeArquivo, string $mimetype, string $legenda = ''): array
            {
                $this->enviados[] = ['numero' => $numero, 'arquivo' => $nomeArquivo, 'mimetype' => $mimetype, 'pdf' => str_starts_with(base64_decode($base64), '%PDF')];

                return ['ok' => true];
            }
        };
        $this->app->instance(\App\Services\Integrations\EvolutionApiService::class, $whatsapp);
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        $this->post(route('carteirinhas.whatsapp', $cFilha), ['telefone' => '83988887777'])->assertSessionHas('sucesso');
        $this->post(route('carteirinhas.whatsapp', $cTitular), ['telefone' => '83988887777', 'familia' => 1])->assertSessionHas('sucesso', fn ($m) => str_contains($m, '3 carteirinhas'));

        $this->assertSame('5583988887777', $whatsapp->enviados[0]['numero']);
        $this->assertSame('application/pdf', $whatsapp->enviados[0]['mimetype']);
        $this->assertTrue($whatsapp->enviados[0]['pdf']);
        $this->assertSame('Carteirinhas - JOAO BATISTA FERREIRA.pdf', $whatsapp->enviados[1]['arquivo']);

        $this->post(route('carteirinhas.whatsapp', $cTitular), ['telefone' => '1234'])->assertSessionHas('erro');
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
