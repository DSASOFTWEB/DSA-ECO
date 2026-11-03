<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * Download do agente de impressão ESC/POS para Windows (resources/
 * agente-impressao). O config.json vai pronto com a porta da URL
 * configurada e o endereço deste sistema — o agente só aceita pedidos dele.
 */
class AgenteImpressaoController extends Controller
{
    public const PASTA = 'agente-impressao';

    public function download(Request $request): BinaryFileResponse
    {
        Gate::authorize('empresa.gerenciar');

        $empresa = Empresa::findOrFail($request->user()->empresa_id);
        $porta = (int) (parse_url($empresa->configuracaoImpressao()['agente_url'], PHP_URL_PORT) ?: 9110);

        $config = [
            'porta' => $porta,
            'impressora' => '',
            'origens' => array_values(array_unique([
                $request->getSchemeAndHttpHost(),
                rtrim((string) config('app.url'), '/'),
            ])),
        ];

        $zipPath = tempnam(sys_get_temp_dir(), 'agente').'.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        // Scripts do Windows com CRLF, mesmo que o servidor tenha o arquivo com LF.
        foreach (glob(resource_path(self::PASTA.'/*')) as $arquivo) {
            $conteudo = preg_replace("/\r?\n/", "\r\n", (string) file_get_contents($arquivo));
            $zip->addFromString('DSA-AgenteImpressao/'.basename($arquivo), $conteudo);
        }
        $zip->addFromString('DSA-AgenteImpressao/config.json', json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $zip->close();

        return response()->download($zipPath, 'DSA-AgenteImpressao.zip', ['Content-Type' => 'application/zip'])->deleteFileAfterSend();
    }
}
