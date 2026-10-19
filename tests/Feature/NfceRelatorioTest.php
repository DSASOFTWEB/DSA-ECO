<?php

namespace Tests\Feature;

use App\Jobs\GerarFechamentoFiscalJob;
use App\Mail\FechamentoFiscalMail;
use App\Models\DocumentoFiscal;
use App\Models\Empresa;
use App\Models\FechamentoFiscal;
use App\Models\User;
use App\Services\Integrations\EvolutionApiService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\XmlNfceExemplo;
use Tests\TestCase;
use ZipArchive;

class NfceRelatorioTest extends TestCase
{
    use RefreshDatabase;
    use XmlNfceExemplo;

    protected Empresa $empresa;

    protected User $operador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        Storage::fake('local');

        $this->empresa = Empresa::factory()->create(['nome' => 'Catolé Eco Clube']);
        $this->operador = User::factory()->create(['empresa_id' => $this->empresa->id]);
        Gate::before(fn () => true);
        $this->actingAs($this->operador);
    }

    public function test_lista_nfce_do_periodo_com_totais_e_acoes(): void
    {
        $this->nota(901, 19, '2026-09-10 10:00:00');
        $this->nota(902, 31, '2026-09-20 15:00:00');
        $this->nota(903, 50, '2026-08-05 09:00:00'); // fora do período
        $this->nota(904, 12, '2026-09-21 09:00:00', DocumentoFiscal::STATUS_REJEITADO);

        $outra = Empresa::factory()->create();
        DocumentoFiscal::withoutGlobalScopes()->create(['empresa_id' => $outra->id, 'modelo' => 'nfce', 'status' => 'autorizado', 'numero' => 777, 'serie' => 1, 'valor_total' => 99, 'autorizado_em' => '2026-09-15 10:00:00']);

        $this->get(route('relatorios.index'))->assertOk()->assertSee('NFC-e emitidas');

        $this->get(route('relatorios.nfce', ['inicio' => '2026-09-01', 'fim' => '2026-09-30']))
            ->assertOk()
            ->assertSee('901')->assertSee('902')->assertDontSee('>903', false)->assertDontSee('777')
            ->assertSee('R$ 50,00') // 19 + 31 (rejeitada não soma)
            ->assertSee('Rejeitada')
            ->assertSee('Ver DANFE')
            ->assertSee('Consumidor final');
    }

    public function test_baixa_o_xml_da_nota(): void
    {
        $nota = $this->nota(901, 19, '2026-09-10 10:00:00');

        $this->get(route('fiscal.xml', $nota))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('<nNF>901</nNF>', false);
    }

    public function test_fecha_o_mes_gerando_zip_com_xml_pdf_e_resumo(): void
    {
        $this->nota(901, 19, '2026-09-10 10:00:00');
        $this->nota(902, 31, '2026-09-20 15:00:00');
        $this->nota(903, 50, '2026-10-01 09:00:00'); // outro mês

        $this->post(route('relatorios.nfce.fechar'), ['mes' => '2026-09'])->assertRedirect()->assertSessionHas('sucesso');

        $fechamento = FechamentoFiscal::firstOrFail();
        $this->assertSame(FechamentoFiscal::STATUS_PRONTO, $fechamento->status);
        $this->assertSame(2, $fechamento->quantidade);
        $this->assertEquals(50, $fechamento->valor_total);
        $this->assertSame('Catole Eco Clube - NFC-e 09-2026.zip', $fechamento->arquivo_nome);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('local')->path($fechamento->arquivo_path)));
        $nomes = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nomes[] = $zip->getNameIndex($i);
        }
        $pasta = 'Catole Eco Clube - NFC-e 09-2026/';
        $this->assertContains($pasta.'Resumo.pdf', $nomes);
        $this->assertContains($pasta.'Resumo.csv', $nomes);
        $this->assertCount(2, preg_grep('#^'.preg_quote($pasta, '#').'XML/NFCe-S1-N00090[12]#', $nomes));
        $this->assertCount(2, preg_grep('#^'.preg_quote($pasta, '#').'PDF/NFCe-S1-N00090[12]\.pdf$#', $nomes));
        $this->assertStringStartsWith('%PDF', $zip->getFromName($pasta.'PDF/NFCe-S1-N000901.pdf'));
        $this->assertStringContainsString('901', $zip->getFromName($pasta.'Resumo.csv'));
        $zip->close();

        $this->get(route('fiscal.fechamentos.download', $fechamento))->assertOk()->assertDownload('Catole Eco Clube - NFC-e 09-2026.zip');
        $this->get(route('relatorios.nfce'))->assertSee('09/2026')->assertSee('Baixar ZIP');
    }

    public function test_link_publico_so_funciona_assinado(): void
    {
        $this->nota(901, 19, '2026-09-10 10:00:00');
        $this->post(route('relatorios.nfce.fechar'), ['mes' => '2026-09']);
        $fechamento = FechamentoFiscal::firstOrFail();
        auth()->logout();

        $this->get(route('fiscal.fechamentos.publico', ['fechamento' => $fechamento->id]))->assertForbidden();
        $this->get(URL::temporarySignedRoute('fiscal.fechamentos.publico', now()->addDay(), ['fechamento' => $fechamento->id]))->assertOk()->assertDownload();
    }

    public function test_envia_o_pacote_por_email(): void
    {
        Mail::fake();
        $this->nota(901, 19, '2026-09-10 10:00:00');
        $this->post(route('relatorios.nfce.fechar'), ['mes' => '2026-09']);
        $fechamento = FechamentoFiscal::firstOrFail();

        $this->post(route('fiscal.fechamentos.email', $fechamento), ['email' => 'contador@exemplo.com.br', 'mensagem' => 'Segue o mês'])
            ->assertRedirect()->assertSessionHas('sucesso');

        Mail::assertSent(FechamentoFiscalMail::class, fn ($mail) => $mail->hasTo('contador@exemplo.com.br') && count($mail->attachments()) === 1);
    }

    public function test_whatsapp_sem_integracao_abre_wa_me_com_link_e_com_integracao_envia_o_arquivo(): void
    {
        $this->nota(901, 19, '2026-09-10 10:00:00');
        $this->post(route('relatorios.nfce.fechar'), ['mes' => '2026-09']);
        $fechamento = FechamentoFiscal::firstOrFail();

        $resposta = $this->post(route('fiscal.fechamentos.whatsapp', $fechamento), ['telefone' => '(83) 99999-1234']);
        $resposta->assertRedirect();
        $this->assertStringStartsWith('https://wa.me/5583999991234?text=', $resposta->headers->get('Location'));
        $this->assertStringContainsString(rawurlencode('/fiscal/fechamentos/'.$fechamento->id.'/baixar'), $resposta->headers->get('Location'));

        $whatsapp = new class extends EvolutionApiService
        {
            public array $enviados = [];

            public function __construct() {}

            public function configurada(): bool { return true; }

            public function enviarDocumento(string $numero, string $base64, string $nomeArquivo, string $mimetype, string $legenda = ''): array
            {
                $this->enviados[] = compact('numero', 'nomeArquivo', 'mimetype');

                return ['ok' => true];
            }
        };
        $this->app->instance(EvolutionApiService::class, $whatsapp);

        $this->post(route('fiscal.fechamentos.whatsapp', $fechamento), ['telefone' => '83999991234'])->assertSessionHas('sucesso');
        $this->assertSame([['numero' => '5583999991234', 'nomeArquivo' => 'Catole Eco Clube - NFC-e 09-2026.zip', 'mimetype' => 'application/zip']], $whatsapp->enviados);
    }

    public function test_mes_grande_vai_para_a_fila(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        foreach (range(1, \App\Services\Fiscal\FechamentoFiscalService::LIMITE_GERACAO_IMEDIATA + 1) as $i) {
            DocumentoFiscal::create(['empresa_id' => $this->empresa->id, 'modelo' => 'nfce', 'status' => 'autorizado', 'numero' => $i, 'serie' => 1, 'valor_total' => 1, 'autorizado_em' => '2026-09-10 10:00:00']);
        }

        $this->post(route('relatorios.nfce.fechar'), ['mes' => '2026-09'])->assertSessionHas('info');

        \Illuminate\Support\Facades\Queue::assertPushed(GerarFechamentoFiscalJob::class);
        $this->assertSame(FechamentoFiscal::STATUS_PROCESSANDO, FechamentoFiscal::firstOrFail()->status);
    }

    protected function nota(int $numero, float $valor, string $data, string $status = DocumentoFiscal::STATUS_AUTORIZADO): DocumentoFiscal
    {
        return DocumentoFiscal::create([
            'empresa_id' => $this->empresa->id,
            'modelo' => DocumentoFiscal::MODELO_NFCE,
            'origem' => 'venda_pdv',
            'ambiente' => 2,
            'status' => $status,
            'serie' => 1,
            'numero' => $numero,
            'valor_total' => $valor,
            'forma_pagamento' => 'pix',
            'autorizado_em' => $status === DocumentoFiscal::STATUS_AUTORIZADO ? $data : null,
            'created_at' => $data,
            'xml_protocolado' => $status === DocumentoFiscal::STATUS_AUTORIZADO ? $this->xmlNfceAutorizada($numero) : null,
            'mensagem_erro' => $status === DocumentoFiscal::STATUS_REJEITADO ? 'cStat 999: teste' : null,
        ]);
    }
}
