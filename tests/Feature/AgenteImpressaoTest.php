<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;
use ZipArchive;

/**
 * Download do agente de impressão ESC/POS (Windows) na aba Gerencial.
 */
class AgenteImpressaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_baixa_zip_com_scripts_e_config_do_site(): void
    {
        $empresa = Empresa::factory()->create(['configuracoes' => ['impressao' => ['modo' => 'escpos', 'agente_url' => 'http://127.0.0.1:9200']]]);
        Gate::before(fn () => true);
        $this->actingAs(User::factory()->create(['empresa_id' => $empresa->id]));

        $this->get(route('empresa.edit', ['aba' => 'gerencial']))->assertOk()
            ->assertSee(route('empresa.agente-impressao'), false)
            ->assertSee('Testar agente');

        $resposta = $this->get(route('empresa.agente-impressao'))->assertOk();
        $this->assertSame('attachment; filename=DSA-AgenteImpressao.zip', $resposta->headers->get('Content-Disposition'));

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($resposta->getFile()->getPathname()));
        foreach (['INSTALAR.bat', 'DESINSTALAR.bat', 'agente.ps1', 'instalar.ps1', 'desinstalar.ps1', 'LEIA-ME.txt', 'config.json'] as $nome) {
            $this->assertNotFalse($zip->locateName('DSA-AgenteImpressao/'.$nome), $nome);
        }

        $config = json_decode($zip->getFromName('DSA-AgenteImpressao/config.json'), true);
        $this->assertSame(9200, $config['porta'], 'porta da URL configurada');
        $this->assertContains(rtrim(config('app.url'), '/'), $config['origens'], 'só aceita o site do sistema');
        $this->assertSame('', $config['impressora']);

        $bat = $zip->getFromName('DSA-AgenteImpressao/INSTALAR.bat');
        $this->assertStringContainsString("\r\n", $bat);
        $this->assertDoesNotMatchRegularExpression("/(?<!\r)\n/", $bat, '.bat com CRLF');
        $zip->close();
    }
}
