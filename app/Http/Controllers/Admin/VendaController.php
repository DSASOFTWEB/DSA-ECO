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
use App\Services\Impressao\EscPosCupomBuilder;
use App\Services\QrCodeService;
use App\Services\Relatorios\VoucherEntradaPdfExport;
use App\Services\VendaService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

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

        if (request()->boolean('trocar_terminal')) {
            session()->forget('pdv_caixa_id');
        }

        [$caixaAberto, $caixasDisponiveis] = $this->resolverCaixaOperador($user, request()->query('caixa_id'));

        $produtos = Produto::ativos()->orderBy('nome')->get();
        $tiposEntrada = TipoEntrada::ativos()->orderBy('nome')->get();

        $produtosJson = $produtos->map(fn (Produto $produto) => [
            'id' => $produto->id,
            'sku' => $produto->sku,
            'nome' => $produto->nome,
            'preco' => (float) $produto->preco_venda,
            'controla_estoque' => (bool) $produto->controla_estoque,
            'estoque' => (int) $produto->estoque_atual,
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

        $venda->load(['itens.produto', 'itens.tipoEntrada', 'cliente', 'unidade', 'vendedor', 'empresa']);

        $acessos = $venda->acessos()->whereNotNull('venda_item_id')->with('vendaItem.tipoEntrada')->get();

        $qrCodes = $acessos->mapWithKeys(fn ($acesso) => [
            $acesso->id => $acesso->codigo_validacao ? $this->qrCodeService->gerarDataUri($acesso->codigo_validacao, 130) : null,
        ]);

        $barcodes = $acessos->mapWithKeys(fn ($acesso) => [
            $acesso->id => $acesso->codigo_validacao ? $this->barcodeService->gerarDataUri($acesso->codigo_validacao) : null,
        ]);

        $meta = $this->metaCupom($venda);
        $impressao = $venda->empresa?->configuracaoImpressao() ?? [
            'modo' => 'dom',
            'colunas' => (int) config('parque.escpos_colunas', 48),
            'agente_url' => (string) config('parque.escpos_agente_url', 'http://127.0.0.1:9110'),
            'auto_imprimir' => false,
        ];

        return view('vendas.comprovante', [
            'venda' => $venda,
            'acessos' => $acessos,
            'qrCodes' => $qrCodes,
            'barcodes' => $barcodes,
            'empresaNome' => $meta['empresaNome'],
            'empresaCnpj' => $meta['empresaCnpj'],
            'empresaTelefone' => $meta['empresaTelefone'],
            'empresaEndereco' => $meta['empresaEndereco'],
            'valorRecebido' => $meta['valorRecebido'],
            'troco' => $meta['troco'],
            'obsLimpa' => $meta['obsLimpa'],
            'escposUrl' => route('vendas.comprovante.escpos', $venda),
            'escposAgenteUrl' => $impressao['agente_url'],
            'escposColunas' => $impressao['colunas'],
            'impressaoModo' => $impressao['modo'],
            'impressaoAuto' => $impressao['auto_imprimir'],
        ]);
    }

    /**
     * Cupom em bytes ESC/POS (bobina 80mm). O browser pode:
     * - baixar o .bin
     * - enviar via Web Serial
     * - POST no agente local (ESCPOS_AGENTE_URL)
     */
    public function comprovanteEscpos(Venda $venda): Response
    {
        $this->authorize('view', $venda);

        $venda->load(['itens.produto', 'itens.tipoEntrada', 'cliente', 'unidade', 'vendedor', 'empresa']);

        $colunas = $venda->empresa?->configuracaoImpressao()['colunas']
            ?? (int) config('parque.escpos_colunas', EscPosCupomBuilder::COLUNAS_80MM);

        $bytes = (new EscPosCupomBuilder($colunas))->montar($venda, $this->metaCupom($venda));

        $nome = 'cupom-venda-'.str_pad((string) $venda->id, 6, '0', STR_PAD_LEFT).'.bin';

        return response($bytes, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="'.$nome.'"',
            'X-Escpos-Columns' => (string) $colunas,
        ]);
    }

    /**
     * @return array{empresaNome: string, empresaCnpj: ?string, empresaTelefone: ?string, empresaEndereco: ?string, valorRecebido: ?float, troco: ?float, obsLimpa: ?string}
     */
    protected function metaCupom(Venda $venda): array
    {
        $empresa = $venda->empresa;
        $unidade = $venda->unidade;

        [$valorRecebido, $troco, $obsLimpa] = $this->extrairTrocoDaObservacao($venda->observacao);

        return [
            'empresaNome' => $empresa?->nome ?? $unidade?->nome ?? config('app.name'),
            'empresaCnpj' => $unidade?->cnpj ?: $empresa?->cnpj,
            'empresaTelefone' => $unidade?->telefone ?: $empresa?->telefone,
            'empresaEndereco' => collect([
                $unidade?->endereco,
                $unidade?->numero,
                $unidade?->bairro,
                $unidade?->cidade && $unidade?->uf ? "{$unidade->cidade}/{$unidade->uf}" : ($unidade?->cidade ?? null),
            ])->filter()->implode(', ') ?: null,
            'valorRecebido' => $valorRecebido,
            'troco' => $troco,
            'obsLimpa' => $obsLimpa,
        ];
    }

    /**
     * O PDV grava troco na observação no formato
     * "Recebido: R$ 50,00 | Troco: R$ 5,00" (ou misturado com texto livre).
     *
     * @return array{0: ?float, 1: ?float, 2: ?string}
     */
    protected function extrairTrocoDaObservacao(?string $observacao): array
    {
        if (! $observacao) {
            return [null, null, null];
        }

        $valorRecebido = null;
        $troco = null;

        if (preg_match('/Recebido:\s*R\$\s*([\d.,]+)/iu', $observacao, $m)) {
            $valorRecebido = (float) str_replace(['.', ','], ['', '.'], $m[1]);
        }
        if (preg_match('/Troco:\s*R\$\s*([\d.,]+)/iu', $observacao, $m)) {
            $troco = (float) str_replace(['.', ','], ['', '.'], $m[1]);
        }

        $obsLimpa = trim(preg_replace('/\s*\|\s*Recebido:.*?Troco:\s*R\$\s*[\d.,]+/iu', '', $observacao) ?? '');
        $obsLimpa = trim(preg_replace('/Recebido:\s*R\$\s*[\d.,]+\s*\|\s*Troco:\s*R\$\s*[\d.,]+/iu', '', $obsLimpa) ?? '');
        $obsLimpa = $obsLimpa !== '' ? $obsLimpa : null;

        return [$valorRecebido, $troco, $obsLimpa];
    }

    public function voucher(Venda $venda, VoucherEntradaPdfExport $export): SymfonyResponse
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
     * Uma vez escolhido, o terminal fica lembrado na sessão do operador
     * (`pdv_caixa_id`) — sem isso, toda vez que "Nova venda" volta pro PDV
     * (depois de cada venda) a pergunta apareceria de novo, o que na
     * prática travaria o fluxo do caixa. Só pergunta de novo se: o
     * operador clicar em "Trocar terminal" (limpa a sessão), ou o caixa
     * escolhido tiver fechado nesse meio tempo (deixa de existir entre os
     * $caixasDisponiveis, então a busca abaixo já retorna null sozinha).
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

        $caixaIdEscolhido ??= session('pdv_caixa_id');

        $caixaEscolhido = $caixaIdEscolhido ? $caixasDisponiveis->firstWhere('id', (int) $caixaIdEscolhido) : null;

        if ($caixaEscolhido) {
            session(['pdv_caixa_id' => $caixaEscolhido->id]);
        }

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
