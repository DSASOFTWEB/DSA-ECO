<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\NegocioException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Venda\StoreVendaRequest;
use App\Models\Caixa;
use App\Models\Produto;
use App\Models\TipoEntrada;
use App\Models\User;
use App\Models\Venda;
use App\Services\BarcodeService;
use App\Services\CaixaService;
use App\Services\QrCodeService;
use App\Services\Relatorios\VoucherEntradaPdfExport;
use App\Services\VendaService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

class VendaController extends Controller
{
    public function __construct(
        protected VendaService $vendaService,
        protected CaixaService $caixaService,
        protected QrCodeService $qrCodeService,
        protected BarcodeService $barcodeService,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', Venda::class);

        $vendas = $this->vendaService->listar(request()->only(['status', 'vendedor_id', 'data_inicio', 'data_fim']));

        return view('vendas.index', compact('vendas'));
    }

    public function create(): View
    {
        $this->authorize('create', Venda::class);

        $user = request()->user();
        [$caixaAberto, $caixasDisponiveis] = $this->resolverCaixaOperador($user, request()->query('caixa_id'));

        $produtos = Produto::ativos()->orderBy('nome')->get();
        $tiposEntrada = TipoEntrada::ativos()->orderBy('nome')->get();

        $produtosJson = $produtos->map(fn (Produto $produto) => [
            'id' => $produto->id,
            'sku' => $produto->sku,
            'nome' => $produto->nome,
            'preco' => (float) $produto->preco_venda,
        ])->values();

        $tiposEntradaJson = $tiposEntrada->map(fn (TipoEntrada $tipo) => [
            'id' => $tipo->id,
            'nome' => $tipo->nome,
            'preco' => (float) $tipo->valor,
        ])->values();

        return view('vendas.create', compact('produtos', 'tiposEntrada', 'caixaAberto', 'produtosJson', 'tiposEntradaJson', 'caixasDisponiveis'));
    }

    public function store(StoreVendaRequest $request): RedirectResponse
    {
        [$caixa] = $this->resolverCaixaOperador($request->user(), $request->input('caixa_id'));

        if (! $caixa) {
            return back()->withInput()->with('erro', 'Abra o caixa da unidade antes de registrar uma venda.');
        }

        try {
            $venda = $this->vendaService->criar($request->validated(), $request->user(), $caixa);
        } catch (NegocioException $e) {
            return back()->withInput()->with('erro', $e->getMessage());
        }

        return redirect()->route('vendas.comprovante', $venda)->with('sucesso', 'Venda registrada com sucesso.');
    }

    public function show(Venda $venda): View
    {
        $this->authorize('view', $venda);

        $venda->load(['itens.produto', 'itens.tipoEntrada', 'cliente', 'vendedor', 'pagamentos']);

        return view('vendas.show', compact('venda'));
    }

    /**
     * Tela pós-venda do PDV: mesma ideia do comprovante de check-in
     * (bobina/PDF) — o operador aperta Enter/F8, a venda é registrada e cai
     * direto aqui pra imprimir ou baixar o voucher (quando aplicável), em
     * vez de ir para a tela de detalhe administrativa.
     */
    public function comprovante(Venda $venda): View
    {
        $this->authorize('view', $venda);

        $venda->load(['itens.produto', 'itens.tipoEntrada', 'cliente', 'unidade']);

        // Um QR/código de barras por ticket (por Acesso), igual ao voucher em
        // PDF — mesma ideia de VoucherEntradaPdfExport, só que embutido
        // direto na bobina em vez de um PDF separado.
        $acessos = $venda->acessos()->whereNotNull('venda_item_id')->with('vendaItem.tipoEntrada')->get();

        $qrCodes = $acessos->mapWithKeys(fn ($acesso) => [
            $acesso->id => $acesso->codigo_validacao ? $this->qrCodeService->gerarDataUri($acesso->codigo_validacao, 130) : null,
        ]);

        $barcodes = $acessos->mapWithKeys(fn ($acesso) => [
            $acesso->id => $acesso->codigo_validacao ? $this->barcodeService->gerarDataUri($acesso->codigo_validacao) : null,
        ]);

        return view('vendas.comprovante', compact('venda', 'acessos', 'qrCodes', 'barcodes'));
    }

    public function voucher(Venda $venda, VoucherEntradaPdfExport $export): Response
    {
        $this->authorize('view', $venda);

        $venda->loadMissing('itens');

        if ($venda->status !== 'pago' || ! $venda->ehEntradaAvulsa()) {
            abort(404);
        }

        return $export->gerar($venda)->stream("voucher-venda-{$venda->id}.pdf");
    }

    /**
     * Descobre o caixa aberto a usar no PDV. Cada unidade pode ter vários
     * terminais com caixa aberto ao mesmo tempo, então mesmo um operador
     * com unidade fixa pode ter mais de uma opção (um por terminal). Se só
     * houver um caixa aberto disponível pro operador, usa direto; se houver
     * mais de um, precisa do $caixaIdEscolhido (a tela pede pra ele
     * escolher em qual terminal está vendendo).
     *
     * @return array{0: ?Caixa, 1: Collection<int, Caixa>}
     */
    protected function resolverCaixaOperador(User $user, null|string|int $caixaIdEscolhido): array
    {
        $caixasDisponiveis = $user->unidade
            ? $this->caixaService->caixasAbertosDaUnidade($user->unidade_id)
            : $this->caixaService->caixasAbertosDaEmpresa($user->empresa_id);

        if ($caixasDisponiveis->count() <= 1) {
            return [$caixasDisponiveis->first(), $caixasDisponiveis];
        }

        $caixaEscolhido = $caixaIdEscolhido ? $caixasDisponiveis->firstWhere('id', (int) $caixaIdEscolhido) : null;

        return [$caixaEscolhido, $caixasDisponiveis];
    }

    public function cancelar(Venda $venda): RedirectResponse
    {
        $this->authorize('cancelar', $venda);

        try {
            $this->vendaService->cancelar($venda, request()->user()->id);
        } catch (NegocioException $e) {
            return back()->with('erro', $e->getMessage());
        }

        return back()->with('sucesso', 'Venda cancelada e estoque estornado.');
    }
}
