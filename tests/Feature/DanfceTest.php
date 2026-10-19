<?php

namespace Tests\Feature;

use App\Models\DocumentoFiscal;
use App\Models\Empresa;
use App\Models\Unidade;
use App\Models\User;
use App\Services\Fiscal\DanfceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\XmlNfceExemplo;
use Tests\TestCase;

class DanfceTest extends TestCase
{
    use RefreshDatabase;
    use XmlNfceExemplo;

    protected Empresa $empresa;

    protected User $operador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = Empresa::factory()->create();
        $unidade = Unidade::factory()->create(['empresa_id' => $this->empresa->id]);
        $this->operador = User::factory()->create(['empresa_id' => $this->empresa->id, 'unidade_id' => $unidade->id]);
        Gate::before(fn () => true);
        $this->actingAs($this->operador);
    }

    public function test_le_o_xml_autorizado_com_tudo_que_o_danfe_precisa(): void
    {
        $d = app(DanfceService::class)->dados($this->documento());

        $this->assertSame('CATOLE ECO CLUBE LTDA', $d['emitente']['razao']);
        $this->assertSame('12.345.678/0001-99', $d['emitente']['cnpj']);
        $this->assertCount(2, $d['itens']);
        $this->assertEquals(1.0, $d['itens'][0]['desconto']);
        $this->assertEquals(19.0, $d['total']);
        $this->assertSame('Pagamento Instantâneo (PIX)', $d['pagamentos'][0]['forma']);
        $this->assertSame('529.982.247-25', $d['consumidor']['documento']);
        $this->assertSame('901', $d['numero']);
        $this->assertTrue($d['homologacao']);
        $this->assertSame('325260000123456', $d['protocolo']);
        $this->assertStringStartsWith('https://www.sefaz.pb.gov.br/nfce?p=', $d['qr_code']);
        $this->assertSame('3526 1012 3456 7800 0199 6500 1000 0009 0112 3456 7890', $d['chave_formatada']);
        $this->assertSame('14:30', $d['emissao']->format('H:i'), 'horário de Brasília');
    }

    public function test_pagina_do_danfe_80mm(): void
    {
        $doc = $this->documento();

        $this->get(route('fiscal.danfce', $doc))
            ->assertOk()
            ->assertSee('Documento Auxiliar da Nota Fiscal de Consumidor Eletrônica')
            ->assertSee('REFRIGERANTE LATA')
            ->assertSee('19,00')
            ->assertSee('NFC-e nº 901')
            ->assertSee('325260000123456')
            ->assertSee('SEM VALOR FISCAL')
            ->assertSee('MARIA DA SILVA')
            ->assertSee('data:image', false);
    }

    public function test_producao_nao_mostra_aviso_de_homologacao(): void
    {
        $this->get(route('fiscal.danfce', $this->documento(tpAmb: '1')))->assertOk()->assertDontSee('SEM VALOR FISCAL');
    }

    public function test_danfe_escpos_tem_qr_nativo_da_sefaz(): void
    {
        $doc = $this->documento();

        $bytes = $this->get(route('fiscal.danfce.escpos', $doc))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/octet-stream')
            ->getContent();

        $this->assertStringContainsString("\x1D(k", $bytes);
        $this->assertStringContainsString('https://www.sefaz.pb.gov.br/nfce?p=', $bytes);
        $this->assertStringContainsString('REFRIGERANTE LATA', $bytes);
        $this->assertStringContainsString('SEM VALOR FISCAL', $bytes);
        $this->assertStringEndsWith("\x1D\x56\x00", $bytes);
    }

    public function test_nao_imprime_nfce_nao_autorizada_nem_de_outra_empresa(): void
    {
        $rejeitada = $this->documento();
        $rejeitada->update(['status' => DocumentoFiscal::STATUS_REJEITADO]);

        $this->from(route('vendas.index'))->get(route('fiscal.danfce', $rejeitada))
            ->assertRedirect(route('vendas.index'))
            ->assertSessionHas('erro');

        $doc = $this->documento();
        $intruso = User::factory()->create(['empresa_id' => Empresa::factory()->create()->id]);
        $this->actingAs($intruso)->get(route('fiscal.danfce', $doc))->assertNotFound();
        $this->actingAs($intruso)->get(route('fiscal.danfce.escpos', $doc))->assertNotFound();
    }

    protected function documento(string $tpAmb = '2'): DocumentoFiscal
    {
        return DocumentoFiscal::create([
            'empresa_id' => $this->empresa->id,
            'modelo' => DocumentoFiscal::MODELO_NFCE,
            'origem' => 'venda_pdv',
            'ambiente' => (int) $tpAmb,
            'status' => DocumentoFiscal::STATUS_AUTORIZADO,
            'numero' => 901,
            'serie' => 1,
            'valor_total' => 19,
            'xml_protocolado' => $this->xmlNfceAutorizada(901, $tpAmb),
        ]);
    }
}
