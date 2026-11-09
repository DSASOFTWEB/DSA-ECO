<?php

namespace Tests\Feature;

use App\Jobs\EnviarCobrancaWhatsappJob;
use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\Contrato;
use App\Models\Empresa;
use App\Models\Mensalidade;
use App\Models\Plano;
use App\Models\Unidade;
use App\Models\User;
use App\Services\CobrancaWhatsappFila;
use App\Services\Integrations\EvolutionApiService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Cobranças por WhatsApp em fila espaçada (disparo em lote = banimento):
 * régua e botões só agendam; o enviador manda 1 por empresa a cada 10 min,
 * no horário comercial, sem reenvio automático.
 */
class CobrancaWhatsappFilaTest extends TestCase
{
    use RefreshDatabase;

    protected object $whatsapp;

    protected Empresa $empresa;

    protected Unidade $unidade;

    protected function setUp(): void
    {
        parent::setUp();
        config(['parque.cobranca_intervalo_minutos' => 10, 'parque.cobranca_janela_inicio' => 8, 'parque.cobranca_janela_fim' => 20,
            'parque.cobranca_dias_apos_vencimento' => [1, 5, 10]]);
        $this->travelTo(now()->setDate(2026, 11, 9)->setTime(9, 0));

        $this->whatsapp = new class extends EvolutionApiService
        {
            public array $enviados = [];

            public bool $falhar = false;

            public function __construct() {}

            public function configurada(): bool { return true; }

            public function enviarTexto(string $numero, string $mensagem): array
            {
                if ($this->falhar) {
                    throw new \App\Exceptions\IntegrationException('Falha ao comunicar com evolution-api', servico: 'evolution-api');
                }
                $this->enviados[] = [$numero, now()->format('H:i')];

                return ['key' => ['id' => 'MSG'.count($this->enviados)]];
            }
        };
        $this->app->instance(EvolutionApiService::class, $this->whatsapp);

        $this->empresa = Empresa::factory()->create();
        $this->unidade = Unidade::factory()->create(['empresa_id' => $this->empresa->id]);
    }

    public function test_regua_so_agenda_e_o_enviador_manda_um_a_cada_10_minutos(): void
    {
        // 3 atrasadas há 1 dia (régua D+1) + 1 que vence em 3 dias (lembrete).
        foreach (['83911111111', '83922222222', '83933333333'] as $tel) {
            $this->mensalidade($tel, now()->subDay(), 'atrasado');
        }
        $this->mensalidade('83944444444', now()->addDays(3), 'pendente');

        $this->artisan('mensalidades:cobrar')->assertSuccessful();
        $this->assertSame(4, Cobranca::where('status', 'agendado')->count());
        $this->assertSame([], $this->whatsapp->enviados, 'a régua não envia nada na hora');

        // Rodar a régua de novo no mesmo dia não duplica.
        $this->artisan('mensalidades:cobrar');
        $this->assertSame(4, Cobranca::count());

        // Enviador a cada minuto: só 1 sai a cada 10 minutos.
        for ($minuto = 0; $minuto < 35; $minuto++) {
            $this->artisan('mensalidades:enviar-fila');
            $this->travel(1)->minutes();
        }

        $this->assertSame(['09:00', '09:10', '09:20', '09:30'], array_column($this->whatsapp->enviados, 1));
        $this->assertSame(4, Cobranca::where('status', 'enviado')->count());
        $this->assertSame(['key' => ['id' => 'MSG1']], Cobranca::orderBy('id')->first()->resposta_gateway, 'guarda o id da mensagem (confirmação de leitura)');
    }

    public function test_fora_do_horario_espera_e_paga_e_cancelada(): void
    {
        $paga = $this->mensalidade('83911111111', now()->subDay(), 'atrasado');
        $this->mensalidade('83922222222', now()->subDay(), 'atrasado');
        $fila = app(CobrancaWhatsappFila::class);
        $fila->agendar($paga);
        $fila->agendar(Mensalidade::latest('id')->first());

        $this->travelTo(now()->setTime(21, 30));
        $this->artisan('mensalidades:enviar-fila');
        $this->assertSame([], $this->whatsapp->enviados, 'nada sai à noite');

        // Pagou antes de chegar a vez: cancela sem mandar e não gasta a vez.
        $paga->update(['status' => 'pago', 'data_pagamento' => now()]);
        $this->travelTo(now()->addDay()->setTime(8, 0));
        $this->artisan('mensalidades:enviar-fila');

        $this->assertSame('cancelado', Cobranca::where('mensalidade_id', $paga->id)->value('status'));
        $this->assertCount(0, $this->whatsapp->enviados);
        $this->travel(1)->minutes();
        $this->artisan('mensalidades:enviar-fila');
        $this->assertCount(1, $this->whatsapp->enviados);
    }

    public function test_falha_nao_reenvia_e_conta_no_intervalo(): void
    {
        $m = $this->mensalidade('83911111111', now()->subDay(), 'atrasado');
        $this->mensalidade('83922222222', now()->subDay(), 'atrasado');
        $fila = app(CobrancaWhatsappFila::class);
        $fila->agendar($m);
        $fila->agendar(Mensalidade::latest('id')->first());

        $this->whatsapp->falhar = true;
        $this->artisan('mensalidades:enviar-fila');
        $this->assertSame('falhou', Cobranca::where('mensalidade_id', $m->id)->value('status'));

        // Falhou agora: a próxima espera os 10 minutos e a que falhou não volta sozinha.
        $this->whatsapp->falhar = false;
        $this->travel(5)->minutes();
        $this->artisan('mensalidades:enviar-fila');
        $this->assertCount(0, $this->whatsapp->enviados);
        $this->travel(6)->minutes();
        $this->artisan('mensalidades:enviar-fila');
        $this->assertCount(1, $this->whatsapp->enviados);
        $this->assertSame('falhou', Cobranca::where('mensalidade_id', $m->id)->value('status'));
    }

    public function test_botao_e_lote_so_agendam_e_job_antigo_nao_dispara(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        Gate::before(fn () => true);
        $this->actingAs(User::factory()->create(['empresa_id' => $this->empresa->id]));

        $a = $this->mensalidade('83911111111', now()->subDay(), 'atrasado');
        $b = $this->mensalidade('83922222222', now()->subDay(), 'atrasado');

        $this->post(route('mensalidades.cobrar-whatsapp', $a))->assertSessionHas('sucesso', fn ($m) => str_contains($m, '1 mensagem a cada 10 min'));
        $this->post(route('mensalidades.cobrar-whatsapp', $a))->assertSessionHas('erro', fn ($m) => str_contains($m, 'já está na fila'));
        $this->post(route('mensalidades.cobrar-whatsapp-lote'), ['ids' => "{$a->id},{$b->id}"])
            ->assertSessionHas('sucesso', fn ($m) => str_contains($m, 'Na fila agora: 2'));

        // Jobs antigos (enfileirados antes da mudança) só agendam.
        EnviarCobrancaWhatsappJob::dispatchSync($b->id, 5);

        $this->assertSame(2, Cobranca::where('status', 'agendado')->count());
        $this->assertSame([], $this->whatsapp->enviados);
    }

    public function test_cobranca_automatica_desligada_na_empresa(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        Gate::before(fn () => true);
        $this->actingAs(User::factory()->create(['empresa_id' => $this->empresa->id]));
        $this->assertTrue($this->empresa->cobrancaAutomaticaWhatsapp(), 'padrão: ligada');

        // Uma automática já agendada antes de desligar.
        $antiga = $this->mensalidade('83911111111', now()->subDay(), 'atrasado');
        $this->artisan('mensalidades:cobrar');
        $this->assertSame(1, Cobranca::where('status', 'agendado')->count());

        // Desmarca na tela (Minha Empresa → Gerencial).
        $this->get(route('empresa.edit', ['aba' => 'gerencial']))->assertSee('Enviar cobranças automáticas de mensalidade por WhatsApp')
            ->assertSee('name="evolution_cobranca_automatica" value="1"', false);
        $this->put(route('empresa.update'), $this->dadosEmpresa())->assertSessionHasNoErrors();
        $this->assertFalse($this->empresa->fresh()->cobrancaAutomaticaWhatsapp());

        // Régua não agenda mais nada.
        $this->mensalidade('83922222222', now()->subDays(5), 'atrasado');
        Cobranca::query()->update(['created_at' => now()->subDay()]);
        $this->artisan('mensalidades:cobrar');
        $this->assertSame(1, Cobranca::count());

        // A automática que já estava na fila é cancelada sem sair...
        $this->artisan('mensalidades:enviar-fila');
        $this->assertSame('cancelado', Cobranca::where('mensalidade_id', $antiga->id)->value('status'));
        $this->assertSame([], $this->whatsapp->enviados);

        // ...mas o envio manual continua funcionando.
        $manual = $this->mensalidade('83933333333', now()->subDay(), 'atrasado');
        app(CobrancaWhatsappFila::class)->agendar($manual);
        $this->artisan('mensalidades:enviar-fila');
        $this->assertCount(1, $this->whatsapp->enviados);

        // Religa.
        $this->put(route('empresa.update'), $this->dadosEmpresa(['evolution_cobranca_automatica' => '1']))->assertSessionHasNoErrors();
        $this->assertTrue($this->empresa->fresh()->cobrancaAutomaticaWhatsapp());
    }

    protected function dadosEmpresa(array $extra = []): array
    {
        return array_merge([
            'nome' => 'Parque Teste', 'regime_tributario' => 'simples', 'impressao_modo' => 'dom', 'impressao_colunas' => 48,
            'ambiente_nfe' => 2, 'numero_serie_nfe' => 1, 'numero_serie_nfce' => 1, 'numero_serie_nfse' => 1,
            'numero_ultima_nfe_producao' => 0, 'numero_ultima_nfe_homologacao' => 0, 'numero_ultima_nfce_producao' => 0,
            'numero_ultima_nfce_homologacao' => 0, 'numero_ultima_nfse' => 0, 'nfse_provider' => 'nacional_gov', 'nfse_auth_mode' => 'certificado',
        ], $extra);
    }

    protected function mensalidade(string $whatsapp, $vencimento, string $status): Mensalidade
    {
        $cliente = Cliente::factory()->create(['empresa_id' => $this->empresa->id, 'unidade_id' => $this->unidade->id, 'whatsapp' => $whatsapp]);
        $plano = Plano::factory()->create(['empresa_id' => $this->empresa->id]);
        $contrato = Contrato::create([
            'empresa_id' => $this->empresa->id, 'unidade_id' => $this->unidade->id, 'cliente_id' => $cliente->id, 'plano_id' => $plano->id,
            'data_inicio' => now()->subMonths(2), 'dia_vencimento' => 10, 'valor_mensal' => 100, 'status' => 'ativo',
        ]);

        return Mensalidade::create([
            'contrato_id' => $contrato->id, 'empresa_id' => $this->empresa->id, 'competencia' => now()->startOfMonth()->addMonths(rand(1, 900)),
            'valor_original' => 100, 'valor_total' => 100, 'data_vencimento' => $vencimento->toDateString(), 'status' => $status,
        ]);
    }
}
