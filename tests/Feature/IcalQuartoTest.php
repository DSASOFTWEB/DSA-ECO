<?php

namespace Tests\Feature;

use App\Exceptions\NegocioException;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Hospedagem;
use App\Models\Quarto;
use App\Models\QuartoBloqueio;
use App\Models\Unidade;
use App\Models\User;
use App\Services\Hospedagem\IcalQuartoService;
use App\Services\HospedagemService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Sincronização de calendário iCal do quarto com Booking.com/Airbnb.
 */
class IcalQuartoTest extends TestCase
{
    use RefreshDatabase;

    protected const URL_BOOKING = 'https://admin.booking.com/hotel/hoteladmin/ical.html?t=abc123';

    protected Empresa $empresa;

    protected Unidade $unidade;

    protected User $operador;

    protected Quarto $quarto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->travelTo(now()->setDate(2026, 11, 3)->setTime(10, 0));

        $this->empresa = Empresa::factory()->create();
        $this->unidade = Unidade::factory()->create(['empresa_id' => $this->empresa->id]);
        $this->operador = User::factory()->create(['empresa_id' => $this->empresa->id, 'unidade_id' => $this->unidade->id]);
        Gate::before(fn () => true);
        $this->actingAs($this->operador);

        $this->quarto = Quarto::create([
            'empresa_id' => $this->empresa->id, 'unidade_id' => $this->unidade->id, 'numero' => 'Apto 03',
            'capacidade_maxima' => 2, 'valor_diaria' => 150, 'status' => 'ativo',
        ]);
    }

    public function test_link_publico_exporta_datas_ocupadas_sem_dados_do_hospede(): void
    {
        $reserva = $this->reservar('2026-11-10', '2026-11-13', 'MARIA HOSPEDE');
        $this->reservar('2026-11-20', '2026-11-22')->update(['status' => 'cancelado']);
        QuartoBloqueio::create(['empresa_id' => $this->empresa->id, 'quarto_id' => $this->quarto->id, 'origem' => 'booking', 'uid' => 'b1', 'inicio' => '2026-11-15', 'fim' => '2026-11-17']);

        $link = $this->quarto->linkIcal();
        auth()->logout();

        $ics = $this->get($link)->assertOk()->assertHeader('Content-Type', 'text/calendar; charset=utf-8')->getContent();

        $this->assertStringContainsString("UID:hospedagem-{$reserva->id}@", $ics);
        $this->assertStringContainsString("DTSTART;VALUE=DATE:20261110\r\nDTEND;VALUE=DATE:20261113", $ics);
        $this->assertStringNotContainsString('MARIA', $ics, 'nunca expõe o hóspede');
        $this->assertStringNotContainsString('20261120', $ics, 'cancelada não sai');
        $this->assertStringNotContainsString('20261115', $ics, 'o que veio do Booking não volta para ele');

        $this->get(route('ical.quarto', str_repeat('x', 40)))->assertNotFound();
    }

    public function test_sincroniza_reservas_do_booking_e_libera_as_canceladas(): void
    {
        $this->quarto->forceFill(['ical_importacao_url' => self::URL_BOOKING])->save();
        Http::fake([self::URL_BOOKING => Http::sequence()
            ->push($this->icsBooking([['bk-1', '20261105', '20261108'], ['bk-2', '20261118', '20261120'], ['bk-velha', '20261001', '20261003']]))
            ->push($this->icsBooking([['bk-1', '20261105', '20261109']]))]);

        $r = app(IcalQuartoService::class)->sincronizar($this->quarto);
        $this->assertSame(['criados' => 2, 'atualizados' => 0, 'removidos' => 0, 'conflitos' => 0], $r, 'passado é ignorado');

        $b = QuartoBloqueio::where('uid', 'bk-1')->firstOrFail();
        $this->assertSame('booking', $b->origem);
        $this->assertSame('2026-11-08', $b->fim->toDateString());
        $this->assertSame('CLOSED - Not available', $b->resumo, 'linha dobrada do .ics é juntada');
        $this->assertNotNull($this->quarto->fresh()->ical_sincronizado_em);

        // Booking estendeu bk-1 e cancelou bk-2.
        $r = app(IcalQuartoService::class)->sincronizar($this->quarto->fresh());
        $this->assertSame(1, $r['atualizados']);
        $this->assertSame(1, $r['removidos']);
        $this->assertSame('2026-11-09', $b->fresh()->fim->toDateString());
        $this->assertDatabaseMissing('quarto_bloqueios', ['uid' => 'bk-2']);
    }

    public function test_data_vendida_no_booking_bloqueia_reserva_no_erp_e_vira_hospedagem_ao_registrar(): void
    {
        $this->quarto->forceFill(['ical_importacao_url' => self::URL_BOOKING])->save();
        Http::fake([self::URL_BOOKING => Http::response($this->icsBooking([['bk-1', '20261105', '20261108']]))]);
        app(IcalQuartoService::class)->sincronizar($this->quarto);
        $bloqueio = QuartoBloqueio::firstOrFail();

        try {
            $this->reservar('2026-11-06', '2026-11-07');
            $this->fail('Deveria bloquear');
        } catch (NegocioException $e) {
            $this->assertStringContainsString('vendido no Booking.com de 05/11 a 08/11', $e->getMessage());
        }

        // Saída do hóspede do Booking (dia 8) é livre para nova entrada.
        $this->reservar('2026-11-08', '2026-11-10');

        // Mapa mostra a reserva do Booking com o botão de registrar.
        $this->get(route('hospedagens.mapa'))->assertOk()
            ->assertSee('Booking.com')
            ->assertSee(route('hospedagens.create', ['bloqueio' => $bloqueio->id]), false);
        $this->get(route('hospedagens.create', ['bloqueio' => $bloqueio->id]))->assertOk()
            ->assertSee('value="2026-11-05"', false)->assertSee('value="2026-11-08"', false)->assertSee('Reserva do <strong>Booking.com</strong>', false);

        // Recepção registra o hóspede da reserva do Booking.
        $cliente = Cliente::factory()->create(['empresa_id' => $this->empresa->id, 'unidade_id' => $this->unidade->id]);
        $this->post(route('hospedagens.store'), [
            'quarto_id' => $this->quarto->id, 'cliente_id' => $cliente->id, 'quantidade_adultos' => 2,
            'data_checkin_prevista' => '2026-11-05', 'data_checkout_prevista' => '2026-11-08', 'bloqueio_id' => $bloqueio->id,
        ])->assertSessionHasNoErrors()->assertSessionHas('sucesso');

        $hospedagem = Hospedagem::where('cliente_id', $cliente->id)->firstOrFail();
        $this->assertSame($hospedagem->id, $bloqueio->fresh()->hospedagem_id);

        // Próxima leitura do Booking não acusa conflito com o próprio hóspede.
        $r = app(IcalQuartoService::class)->sincronizar($this->quarto->fresh());
        $this->assertSame(0, $r['conflitos']);
        $this->assertFalse($bloqueio->fresh()->conflito);
    }

    public function test_marca_overbooking_quando_o_canal_vende_data_ja_ocupada(): void
    {
        $this->reservar('2026-11-05', '2026-11-07');
        $this->quarto->forceFill(['ical_importacao_url' => self::URL_BOOKING])->save();
        Http::fake([self::URL_BOOKING => Http::response($this->icsBooking([['bk-1', '20261106', '20261108']]))]);

        $this->put(route('quartos.ical.salvar', $this->quarto), ['ical_importacao_url' => self::URL_BOOKING])
            ->assertSessionHas('erro', fn ($e) => str_contains($e, 'overbooking'));

        $this->assertTrue(QuartoBloqueio::firstOrFail()->conflito);
        $this->get(route('quartos.edit', $this->quarto))->assertSee('OVERBOOKING');
    }

    public function test_erro_de_leitura_fica_registrado_e_url_precisa_ser_publica(): void
    {
        $this->quarto->forceFill(['ical_importacao_url' => self::URL_BOOKING])->save();
        Http::fake([self::URL_BOOKING => Http::response('erro', 500)]);

        $this->artisan('hospedagem:sincronizar-ical')->assertSuccessful();
        $this->assertStringContainsString('HTTP 500', $this->quarto->fresh()->ical_erro);
        $this->get(route('quartos.edit', $this->quarto))->assertSee('Erro na última leitura');

        $servico = app(IcalQuartoService::class);
        foreach (['http://admin.booking.com/ical', 'https://127.0.0.1/ical.ics', 'https://10.0.0.5/x.ics'] as $url) {
            try {
                $servico->validarUrl($url);
                $this->fail("Deveria recusar {$url}");
            } catch (NegocioException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_tela_do_quarto_mostra_os_dois_links(): void
    {
        $this->get(route('quartos.edit', $this->quarto))->assertOk()
            ->assertSee('Booking.com / Airbnb (calendário iCal)')
            ->assertSee($this->quarto->fresh()->linkIcal(), false)
            ->assertSee('Salvar e sincronizar');

        $antigo = $this->quarto->fresh()->ical_token;
        $this->post(route('quartos.ical.novo-link', $this->quarto))->assertSessionHas('sucesso');
        $this->assertNotSame($antigo, $this->quarto->fresh()->ical_token);
    }

    public function test_passo_a_passo_em_pdf(): void
    {
        $this->get(route('quartos.edit', $this->quarto))->assertSee(route('quartos.ical.passo-a-passo'), false);

        $resposta = $this->get(route('quartos.ical.passo-a-passo'))->assertOk();
        $this->assertSame('application/pdf', $resposta->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $resposta->getContent());

        $html = view('quartos.pdf.ical-passo-a-passo', ['empresa' => $this->empresa])->render();
        foreach (['Sincronizar calendários', 'Importar calendário', 'Exportar calendário', 'Registrar hóspede', 'OVERBOOKING', 'Sincronizar agora'] as $trecho) {
            $this->assertStringContainsString($trecho, $html);
        }
    }

    protected function reservar(string $entrada, string $saida, string $nome = 'HOSPEDE ERP'): Hospedagem
    {
        $cliente = Cliente::factory()->create(['empresa_id' => $this->empresa->id, 'unidade_id' => $this->unidade->id, 'nome' => $nome]);

        return app(HospedagemService::class)->reservar([
            'quarto_id' => $this->quarto->id, 'cliente_id' => $cliente->id, 'quantidade_adultos' => 1,
            'data_checkin_prevista' => $entrada, 'data_checkout_prevista' => $saida,
        ], $this->operador);
    }

    /** .ics no formato do Booking.com (com uma linha dobrada no SUMMARY). */
    protected function icsBooking(array $eventos): string
    {
        $linhas = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Booking.com//NONSGML Booking.com//EN'];
        foreach ($eventos as [$uid, $inicio, $fim]) {
            array_push($linhas, 'BEGIN:VEVENT', "UID:{$uid}", "DTSTART;VALUE=DATE:{$inicio}", "DTEND;VALUE=DATE:{$fim}", 'SUMMARY:CLOSED - Not ', ' available', 'END:VEVENT');
        }
        $linhas[] = 'END:VCALENDAR';

        return implode("\r\n", $linhas);
    }
}
