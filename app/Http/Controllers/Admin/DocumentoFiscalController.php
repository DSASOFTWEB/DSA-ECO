<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\NegocioException;
use App\Http\Controllers\Controller;
use App\Models\DocumentoFiscal;
use App\Models\Empresa;
use App\Services\Fiscal\DanfceService;
use App\Services\Impressao\DanfceEscPosBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 * Impressão do DANFE NFC-e (cupom fiscal 80mm) — da venda (PDV, mesa,
 * comanda) ou dos consumos da hospedagem. O documento tem escopo da
 * empresa: de outra empresa, o binding devolve 404.
 */
class DocumentoFiscalController extends Controller
{
    public function __construct(protected DanfceService $danfce) {}

    public function danfce(DocumentoFiscal $documento): View|RedirectResponse
    {
        try {
            $dados = $this->danfce->dados($documento);
        } catch (NegocioException $e) {
            return back()->with('erro', $e->getMessage());
        }

        $impressao = Empresa::findOrFail($documento->empresa_id)->configuracaoImpressao();

        return view('fiscal.danfce', [
            'documento' => $documento,
            'd' => $dados,
            'voltarUrl' => $this->voltarUrl($documento),
            'impressaoModo' => $impressao['modo'],
            'escposColunas' => $impressao['colunas'],
            'escposAgenteUrl' => $impressao['agente_url'],
        ]);
    }

    public function danfceEscpos(DocumentoFiscal $documento): Response
    {
        try {
            $dados = $this->danfce->dados($documento);
        } catch (NegocioException $e) {
            abort(422, $e->getMessage());
        }

        $colunas = Empresa::findOrFail($documento->empresa_id)->configuracaoImpressao()['colunas'];

        return response((new DanfceEscPosBuilder($colunas))->montarDanfce($dados), 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="danfce-'.$dados['numero'].'.bin"',
        ]);
    }

    /** Para onde o botão "Voltar" leva: PDV, mesas ou a hospedagem. */
    protected function voltarUrl(DocumentoFiscal $documento): string
    {
        return match (true) {
            (bool) $documento->hospedagem_id => route('hospedagens.show', $documento->hospedagem_id),
            $documento->origem === 'venda_mesa' => route('food.index'),
            default => route('vendas.create'),
        };
    }
}
