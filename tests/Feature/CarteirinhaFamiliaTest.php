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

    public function test_whatsapp_sem_integracao_abre_wa_me_com_link_da_carteirinha(): void
    {
        [$titular, $cTitular] = $this->familia('JOAO BATISTA FERREIRA', '529.982.247-25');
        $titular->update(['whatsapp' => '(83) 99999-1234']);

        $this->get(route('carteirinhas.index', ['busca' => 'JOAO']))->assertOk()->assertSee('WhatsApp')->assertSee('(83) 99999-1234')->assertSee('Família inteira (3)');
        $this->get(route('carteirinhas.show', $cTitular))->assertOk()->assertSee('Enviar pelo WhatsApp');

        $resposta = $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post(route('carteirinhas.whatsapp', $cTitular), ['telefone' => '(83) 99999-1234', 'familia' => 1])
            ->assertRedirect();
        $destino = $resposta->headers->get('Location');
        $this->assertStringStartsWith('https://wa.me/5583999991234?text=', $destino);
        $this->assertStringContainsString(rawurlencode('Olá, Joao! Seguem as carteirinhas da sua família'), $destino);

        // O link do PDF só abre assinado.
        parse_str((string) parse_url($destino, PHP_URL_QUERY), $query);
        preg_match('#https?://\S+#', $query['text'], $link);
        auth()->logout();
        $this->get($link[0])->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('carteirinhas.compartilhada', $cTitular))->assertForbidden();
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
