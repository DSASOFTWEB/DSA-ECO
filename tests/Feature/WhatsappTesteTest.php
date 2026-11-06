<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\User;
use App\Services\Integrations\EvolutionApiService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Botão "Testar WhatsApp" (Evolution) em Minha Empresa → Gerencial.
 */
class WhatsappTesteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        Gate::before(fn () => true);
        $this->actingAs(User::factory()->create(['empresa_id' => Empresa::factory()->create(['nome' => 'Eco Clube'])->id]));
    }

    public function test_confere_conexao_e_envia_texto_e_pdf(): void
    {
        $fake = $this->fake('open');

        $this->get(route('empresa.edit', ['aba' => 'gerencial']))->assertOk()->assertSee('Testar WhatsApp');

        $this->postJson(route('empresa.whatsapp.testar'))->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('etapas.0.texto', 'Instância conectada ao WhatsApp.')
            ->assertJsonCount(1, 'etapas');
        $this->assertSame([], $fake->enviados, 'sem número não envia nada');

        $this->postJson(route('empresa.whatsapp.testar'), ['telefone' => '(83) 99999-1234'])->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('etapas.1.etapa', 'Mensagem de texto')
            ->assertJsonPath('etapas.2.etapa', 'Arquivo PDF');
        $this->assertSame(['texto:5583999991234', 'pdf:5583999991234:Teste-WhatsApp.pdf'], $fake->enviados);
        $this->assertTrue($fake->pdfValido);
    }

    public function test_instancia_desconectada_e_falha_no_pdf(): void
    {
        $fake = $this->fake('close');
        $fake->falharPdf = true;

        $this->postJson(route('empresa.whatsapp.testar'), ['telefone' => '83999991234'])->assertOk()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('etapas.0.ok', false)
            ->assertJsonPath('etapas.0.texto', 'Instância DESCONECTADA: leia o QR Code com o celular da empresa no painel da Evolution.')
            ->assertJsonPath('etapas.2.texto', 'WhatsApp: instância não encontrada na Evolution (confira o nome da instância).');
    }

    public function test_sem_configuracao_ou_numero_invalido(): void
    {
        config(['evolution.base_url' => '', 'evolution.api_key' => '', 'evolution.instance' => '']);
        $this->postJson(route('empresa.whatsapp.testar'))->assertStatus(422)->assertJsonPath('message', 'Preencha URL, API Key e nome da instância e clique em "Salvar alterações" antes de testar.');

        $this->fake('open');
        $this->postJson(route('empresa.whatsapp.testar'), ['telefone' => '123'])->assertStatus(422);
    }

    protected function fake(string $estado): EvolutionApiService
    {
        $fake = new class($estado) extends EvolutionApiService
        {
            public array $enviados = [];

            public bool $pdfValido = false;

            public bool $falharPdf = false;

            public function __construct(private string $estado) {}

            public function configurada(): bool { return true; }

            public function statusConexao(): array { return ['instance' => ['instanceName' => 'pousada', 'state' => $this->estado]]; }

            public function enviarTexto(string $numero, string $mensagem): array
            {
                $this->enviados[] = "texto:{$numero}";

                return [];
            }

            public function enviarDocumento(string $numero, string $base64, string $nomeArquivo, string $mimetype, string $legenda = ''): array
            {
                if ($this->falharPdf) {
                    throw new \App\Exceptions\IntegrationException('WhatsApp: instância não encontrada na Evolution (confira o nome da instância).', servico: 'evolution-api');
                }
                $this->enviados[] = "pdf:{$numero}:{$nomeArquivo}";
                $this->pdfValido = str_starts_with(base64_decode($base64), '%PDF');

                return [];
            }
        };
        $this->app->instance(EvolutionApiService::class, $fake);

        return $fake;
    }
}
