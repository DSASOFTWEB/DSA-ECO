<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\NegocioException;
use App\Http\Controllers\Controller;
use App\Models\Acesso;
use App\Models\Cliente;
use App\Services\AcessoService;
use App\Services\BarcodeService;
use App\Services\QrCodeService;
use App\Services\Relatorios\VoucherCheckinPdfExport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Check-in manual de cliente com plano no PDV: o operador localiza o cliente
 * por CPF, código, nome ou cartão/QR (em vez de passar a carteirinha na
 * catraca) e registra a entrada. Nunca gera venda/receita — é só controle
 * de acesso/frequência, igual à leitura de QR (ver App\Services\AcessoService).
 */
class CheckinController extends Controller
{
    public function __construct(
        protected AcessoService $acessoService,
        protected QrCodeService $qrCodeService,
        protected BarcodeService $barcodeService,
    ) {}

    public function index(): View
    {
        $this->authorize('checkin.realizar');

        return view('checkin.index');
    }

    public function buscar(Request $request): JsonResponse
    {
        $this->authorize('checkin.realizar');

        $clientes = $this->acessoService->pesquisarClientes((string) $request->query('termo', ''), $request->user()->empresa_id);

        return response()->json($clientes->map(fn (Cliente $cliente) => [
            'id' => $cliente->id,
            'nome' => $cliente->nome,
            'cpf' => $cliente->cpf,
        ]));
    }

    public function show(Cliente $cliente): View
    {
        $this->authorize('checkin.realizar');

        $situacao = $this->acessoService->situacaoPlano($cliente);

        return view('checkin.show', compact('cliente', 'situacao'));
    }

    public function registrar(Request $request, Cliente $cliente): RedirectResponse
    {
        $this->authorize('checkin.realizar');

        $unidadeId = $request->user()->unidade_id ?? $cliente->unidade_id;

        if (! $unidadeId) {
            return back()->with('erro', 'Seu usuário não está vinculado a uma unidade. Não é possível registrar a entrada.');
        }

        // Presente no formulário só quando o cliente tem dependentes (ver
        // checkin/show.blade.php) — o operador desmarca quem não veio hoje.
        // Ausente do request = sem restrição (comportamento de sempre).
        $dependentesIds = $request->has('dependentes')
            ? array_map('intval', (array) $request->input('dependentes', []))
            : null;

        try {
            $resultado = $this->acessoService->registrarEntradaPlano($cliente, $unidadeId, $request->user(), dependentesIds: $dependentesIds);
        } catch (NegocioException $e) {
            return back()->with('erro', $e->getMessage());
        }

        if (! $resultado['autorizado']) {
            return back()->with('erro', 'Entrada NEGADA: '.$resultado['motivo']);
        }

        $idsAcessos = $resultado['acessos']->pluck('id')->implode(',');

        return redirect()->route('checkin.comprovante', ['cliente' => $cliente, 'acessos' => $idsAcessos])
            ->with('sucesso', 'Entrada liberada — cliente com plano ativo.');
    }

    /**
     * Tela pós check-in: mostra o(s) voucher(s) do titular + dependentes que
     * acabaram de entrar juntos, com opção de imprimir na bobina (impressora
     * térmica, via diálogo de impressão do navegador) ou baixar em PDF.
     */
    public function comprovante(Request $request, Cliente $cliente): View
    {
        $this->authorize('checkin.realizar');

        $acessos = $this->acessosDoCheckin($request, $cliente);

        $qrCodes = $acessos->mapWithKeys(fn ($acesso) => [
            $acesso->id => $acesso->codigo_validacao ? $this->qrCodeService->gerarDataUri($acesso->codigo_validacao, 130) : null,
        ]);

        $barcodes = $acessos->mapWithKeys(fn ($acesso) => [
            $acesso->id => $acesso->codigo_validacao ? $this->barcodeService->gerarDataUri($acesso->codigo_validacao) : null,
        ]);

        return view('checkin.comprovante', compact('cliente', 'acessos', 'qrCodes', 'barcodes'));
    }

    public function voucherPdf(Request $request, Cliente $cliente, VoucherCheckinPdfExport $export): Response
    {
        $this->authorize('checkin.realizar');

        $acessos = $this->acessosDoCheckin($request, $cliente);

        return $export->gerar($acessos)->stream("voucher-checkin-{$cliente->id}.pdf");
    }

    /**
     * Restringe os ids de acesso vindos da querystring ao titular/família do
     * $cliente da própria rota e à empresa do operador logado — sem isso,
     * qualquer id na URL (Acesso não tem TenantScope) exibiria/baixaria o
     * comprovante de check-in de outro cliente, inclusive de outra empresa.
     */
    protected function acessosDoCheckin(Request $request, Cliente $cliente)
    {
        $ids = array_filter(explode(',', (string) $request->query('acessos', '')));
        $dependentesIds = $cliente->dependentes()->pluck('id');

        return Acesso::with(['cliente', 'dependente', 'unidade'])
            ->daEmpresa($request->user()->empresa_id)
            ->whereIn('id', $ids)
            ->where(function ($q) use ($cliente, $dependentesIds) {
                $q->where('cliente_id', $cliente->id)->orWhereIn('dependente_id', $dependentesIds);
            })
            ->get();
    }
}
