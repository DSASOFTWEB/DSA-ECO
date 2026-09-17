<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\DocumentoFiscal;
use App\Models\Empresa;
use App\Models\Hospedagem;
use App\Models\Quarto;
use App\Models\Unidade;
use App\Models\User;
use App\Services\Fiscal\FiscalXmlStorageService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DocumentoFiscalXmlPersistenciaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        Storage::fake('local');
    }

    public function test_grava_xml_autorizado_no_banco_e_na_pasta(): void
    {
        $empresa = Empresa::factory()->create();
        $documento = DocumentoFiscal::create([
            'empresa_id' => $empresa->id,
            'modelo' => DocumentoFiscal::MODELO_NFSE,
            'origem' => 'teste',
            'ambiente' => 2,
            'status' => DocumentoFiscal::STATUS_PROCESSANDO,
            'serie' => 1,
            'numero' => 10,
            'valor_total' => 100,
        ]);

        $xml = '<?xml version="1.0"?><NFSe><infNFSe Id="NFS123">ok</infNFSe></NFSe>';
        $chave = str_repeat('1', 50);

        $storage = app(FiscalXmlStorageService::class);
        $path = $storage->salvarAutorizado($documento, $xml, $chave);

        $this->assertNotNull($path);
        Storage::disk('local')->assertExists($path);
        $this->assertSame($xml, $storage->ler($path));

        $documento->update([
            'status' => DocumentoFiscal::STATUS_AUTORIZADO,
            'chave' => $chave,
            'protocolo' => 'PROT-1',
            'recibo' => 'DPS-1',
            'xml_protocolado' => $xml,
            'xml_path' => $path,
        ]);

        $this->assertDatabaseHas('documentos_fiscais', [
            'id' => $documento->id,
            'chave' => $chave,
            'protocolo' => 'PROT-1',
            'recibo' => 'DPS-1',
            'xml_path' => $path,
        ]);
    }

    public function test_decodifica_nfse_gzip_base64(): void
    {
        $xml = '<NFSe><infNFSe>teste</infNFSe></NFSe>';
        $payload = base64_encode(gzencode($xml));

        $decoded = app(FiscalXmlStorageService::class)->decodificarGzipBase64($payload);

        $this->assertSame($xml, $decoded);
    }

    public function test_download_xml_fiscal_autorizado(): void
    {
        [$empresa, $unidade, $user] = $this->criarOperador();
        $hospedagem = $this->criarHospedagem($empresa, $unidade, $user);

        $xml = '<?xml version="1.0"?><NFSe><infNFSe Id="NFS1">ok</infNFSe></NFSe>';
        $documento = DocumentoFiscal::create([
            'empresa_id' => $empresa->id,
            'unidade_id' => $unidade->id,
            'hospedagem_id' => $hospedagem->id,
            'modelo' => DocumentoFiscal::MODELO_NFSE,
            'origem' => 'hospedagem_servicos',
            'ambiente' => 2,
            'status' => DocumentoFiscal::STATUS_AUTORIZADO,
            'serie' => 1,
            'numero' => 1,
            'chave' => str_repeat('9', 50),
            'protocolo' => 'IDDPS1',
            'recibo' => 'IDDPS1',
            'xml_protocolado' => $xml,
            'valor_total' => 50,
        ]);

        $path = app(FiscalXmlStorageService::class)->salvarAutorizado($documento, $xml, $documento->chave);
        $documento->update(['xml_path' => $path]);

        $this->actingAs($user)
            ->get(route('hospedagens.documentos-fiscais.xml', [$hospedagem, $documento]))
            ->assertOk()
            ->assertHeader('content-type', 'application/xml; charset=UTF-8')
            ->assertSee('infNFSe', false);
    }

    protected function criarHospedagem(Empresa $empresa, Unidade $unidade, User $user): Hospedagem
    {
        $cliente = Cliente::factory()->create(['empresa_id' => $empresa->id, 'unidade_id' => $unidade->id]);
        $quarto = Quarto::create([
            'empresa_id' => $empresa->id,
            'unidade_id' => $unidade->id,
            'numero' => '101',
            'capacidade_maxima' => 2,
            'valor_diaria' => 150,
            'status' => 'ativo',
        ]);

        return Hospedagem::create([
            'empresa_id' => $empresa->id,
            'unidade_id' => $unidade->id,
            'quarto_id' => $quarto->id,
            'cliente_id' => $cliente->id,
            'quantidade_hospedes' => 1,
            'quantidade_adultos' => 1,
            'quantidade_criancas' => 0,
            'quantidade_isentos' => 0,
            'valor_diaria' => 150,
            'data_checkin_prevista' => now()->toDateString(),
            'data_checkout_prevista' => now()->addDay()->toDateString(),
            'data_checkin_real' => now(),
            'status' => 'hospedado',
            'registrado_por_id' => $user->id,
        ]);
    }

    protected function criarOperador(): array
    {
        $empresa = Empresa::factory()->create();
        $unidade = Unidade::factory()->create(['empresa_id' => $empresa->id]);
        $user = User::factory()->create(['empresa_id' => $empresa->id, 'unidade_id' => $unidade->id]);
        Permission::findOrCreate('pousada.visualizar', 'web');
        $user->givePermissionTo('pousada.visualizar');
        Gate::before(fn () => true);

        return [$empresa, $unidade, $user];
    }
}
