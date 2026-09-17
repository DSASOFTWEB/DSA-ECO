<?php

namespace Tests\Unit\Fiscal;

use App\Exceptions\NegocioException;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Hospedagem;
use App\Models\Quarto;
use App\Models\Unidade;
use App\Services\Fiscal\NfseMunicipal\MunicipioNfseCatalog;
use App\Services\Fiscal\NfseMunicipal\Providers\GissAbrasf204Provider;
use App\Services\Fiscal\NfseMunicipal\Xml\AbrasfV2RpsBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NfseMunicipalCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_maceio_como_giss(): void
    {
        $m = app(MunicipioNfseCatalog::class)->resolve('2704302');

        $this->assertSame('Giss', $m['provedor']);
        $this->assertSame('2.04', $m['versao']);
        $this->assertContains('Dividir100', $m['params']);
        $this->assertStringContainsString('ws-maceio.giss.com.br', $m['url_producao']);
        $this->assertStringContainsString('homologacao', $m['url_homologacao']);
    }

    public function test_ibge_invalido_falha(): void
    {
        $this->expectException(NegocioException::class);
        app(MunicipioNfseCatalog::class)->resolve('123');
    }

    public function test_builder_gera_bloco_servico_abrasf(): void
    {
        $empresa = Empresa::factory()->create([
            'cnpj' => '22195708400012',
            'im' => '12345',
            'codigo_municipio_ibge' => '2704302',
            'codigo_servico_hospedagem_lc116' => '09.01.05',
            'codigo_tributacao_municipal_hospedagem' => '010',
            'aliquota_iss_hospedagem' => 5,
            'regime_tributario' => Empresa::REGIME_SIMPLES,
        ]);
        $unidade = Unidade::factory()->create(['empresa_id' => $empresa->id, 'cnpj' => $empresa->cnpj]);
        $cliente = Cliente::factory()->create([
            'empresa_id' => $empresa->id,
            'unidade_id' => $unidade->id,
            'cpf' => '52998224725',
            'nome' => 'Tomador Teste',
        ]);
        $quarto = Quarto::create([
            'empresa_id' => $empresa->id,
            'unidade_id' => $unidade->id,
            'numero' => '1',
            'capacidade_maxima' => 2,
            'valor_diaria' => 100,
            'status' => 'ativo',
        ]);
        $hospedagem = Hospedagem::create([
            'empresa_id' => $empresa->id,
            'unidade_id' => $unidade->id,
            'quarto_id' => $quarto->id,
            'cliente_id' => $cliente->id,
            'quantidade_hospedes' => 1,
            'quantidade_adultos' => 1,
            'quantidade_criancas' => 0,
            'quantidade_isentos' => 0,
            'valor_diaria' => 100,
            'data_checkin_prevista' => now()->toDateString(),
            'data_checkout_prevista' => now()->addDay()->toDateString(),
            'data_checkin_real' => now(),
            'status' => 'hospedado',
        ]);

        $municipio = app(MunicipioNfseCatalog::class)->resolve('2704302');
        $montado = app(AbrasfV2RpsBuilder::class)->montarRps(
            $empresa,
            $unidade,
            $hospedagem->load('cliente'),
            $municipio,
            [['descricao' => 'Hospedagem teste', 'quantidade' => 1, 'valor_unitario' => 100]],
            1,
            42,
        );

        $this->assertStringContainsString('InfDeclaracaoPrestacaoServico', $montado['rps']);
        $this->assertStringContainsString('<ItemListaServico>9.01</ItemListaServico>', $montado['rps']);
        $this->assertStringContainsString('<CodigoTributacaoMunicipio>010</CodigoTributacaoMunicipio>', $montado['rps']);
        $this->assertStringContainsString('<Discriminacao>Hospedagem teste</Discriminacao>', $montado['rps']);
        $this->assertStringContainsString('<CodigoMunicipio>2704302</CodigoMunicipio>', $montado['rps']);
        // Dividir100: 5% → 0.0500
        $this->assertStringContainsString('<Aliquota>0.0500</Aliquota>', $montado['rps']);
    }

    public function test_erro_indica_processando(): void
    {
        $giss = app(GissAbrasf204Provider::class);
        $this->assertTrue($giss->erroIndicaProcessando('A remessa ainda nao foi processada'));
        $this->assertTrue($giss->erroIndicaProcessando('Lote em processamento'));
        $this->assertFalse($giss->erroIndicaProcessando('Codigo de tributacao inexistente'));
    }
}
