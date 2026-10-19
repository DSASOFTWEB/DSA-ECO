<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\DocumentoFiscal;
use App\Models\Empresa;
use App\Models\Unidade;
use App\Services\Fiscal\NfceEmissaoService;
use App\Support\Documento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\XmlNfceExemplo;
use Tests\TestCase;

/**
 * Sem cliente identificado (ou com CPF/CNPJ inválido) a NFC-e vai para
 * consumidor final: sem <dest> no XML e "CONSUMIDOR FINAL" no cupom.
 */
class NfceConsumidorFinalTest extends TestCase
{
    use RefreshDatabase;
    use XmlNfceExemplo;

    public function test_valida_cpf_e_cnpj_pelos_digitos(): void
    {
        $this->assertTrue(Documento::cpfValido('529.982.247-25'));
        $this->assertFalse(Documento::cpfValido('529.982.247-24'));
        $this->assertFalse(Documento::cpfValido('000.000.000-00'));
        $this->assertFalse(Documento::cpfValido('111.111.111-11'));
        $this->assertFalse(Documento::cpfValido('1234'));
        $this->assertTrue(Documento::cnpjValido('11.222.333/0001-81'));
        $this->assertFalse(Documento::cnpjValido('11.222.333/0001-82'));
    }

    public function test_sem_cliente_vai_para_consumidor_final(): void
    {
        $xml = $this->xmlGerado(null);

        $this->assertStringNotContainsString('<dest>', $xml);
        $this->assertStringContainsString('<indFinal>1</indFinal>', $xml);
    }

    public function test_cliente_com_cpf_invalido_vai_para_consumidor_final(): void
    {
        foreach (['000.000.000-00', '529.982.247-24', '', null] as $cpf) {
            $cliente = new Cliente(['nome' => 'Fulano', 'cpf' => $cpf]);
            $this->assertStringNotContainsString('<dest>', $this->xmlGerado($cliente), "CPF {$cpf}");
        }
    }

    public function test_cliente_identificado_em_producao_sai_com_cpf_e_nome(): void
    {
        $xml = $this->xmlGerado(new Cliente(['nome' => 'Maria da Silva', 'cpf' => '529.982.247-25']), ambiente: 1);

        $this->assertStringContainsString('<CPF>52998224725</CPF>', $xml);
        $this->assertStringContainsString('<xNome>Maria da Silva</xNome>', $xml);
    }

    public function test_em_homologacao_o_nome_do_destinatario_e_o_exigido_pela_sefaz(): void
    {
        $xml = $this->xmlGerado(new Cliente(['nome' => 'Maria da Silva', 'cpf' => '529.982.247-25']), ambiente: 2);

        $this->assertStringContainsString('<xNome>NF-E EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL</xNome>', $xml);
    }

    public function test_cliente_com_cnpj_sai_com_tag_cnpj(): void
    {
        $xml = $this->xmlGerado(new Cliente(['nome' => 'Empresa Cliente', 'cpf' => '11.222.333/0001-81']), ambiente: 1);

        $this->assertStringContainsString('<CNPJ>11222333000181</CNPJ>', $xml);
    }

    public function test_cupom_mostra_consumidor_final_quando_nao_identificado(): void
    {
        $empresa = Empresa::factory()->create();
        $this->actingAs(\App\Models\User::factory()->create(['empresa_id' => $empresa->id]));
        $semDest = preg_replace('#<dest>.*?</dest>#s', '', $this->xmlNfceAutorizada());

        $doc = DocumentoFiscal::create([
            'empresa_id' => $empresa->id, 'modelo' => DocumentoFiscal::MODELO_NFCE, 'origem' => 'venda_pdv',
            'ambiente' => 2, 'status' => DocumentoFiscal::STATUS_AUTORIZADO, 'numero' => 901, 'serie' => 1,
            'valor_total' => 19, 'xml_protocolado' => $semDest,
        ]);

        $this->get(route('fiscal.danfce', $doc))->assertOk()->assertSee('CONSUMIDOR FINAL — NÃO IDENTIFICADO');
        $this->assertStringContainsString('CONSUMIDOR FINAL - NAO IDENTIFICADO', $this->get(route('fiscal.danfce.escpos', $doc))->getContent());
    }

    /** Gera o XML da NFC-e (antes da assinatura) como iria para a SEFAZ. */
    protected function xmlGerado(?Cliente $cliente, int $ambiente = 2): string
    {
        $empresa = Empresa::where('cnpj', '11222333000181')->first()
            ?? Empresa::factory()->create(['cnpj' => '11222333000181', 'ie' => '161234567', 'codigo_municipio_ibge' => '2504009']);
        $empresa->update(['ambiente_nfe' => $ambiente]);
        $unidade = Unidade::where('empresa_id', $empresa->id)->first()
            ?? Unidade::factory()->create(['empresa_id' => $empresa->id, 'uf' => 'PB']);
        $itens = [['descricao' => 'Refrigerante', 'quantidade' => 1, 'valor_unitario' => 8, 'ncm' => '22021000']];

        $metodo = new \ReflectionMethod(NfceEmissaoService::class, 'montarXml');

        return $metodo->invoke(app(NfceEmissaoService::class), $empresa, $unidade, $cliente, 'dinheiro', $itens, 1, 1, '2504009');
    }
}
