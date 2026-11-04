<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\NegocioException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Quarto\StoreQuartoRequest;
use App\Models\Produto;
use App\Models\Quarto;
use App\Models\Unidade;
use App\Services\Hospedagem\IcalQuartoService;
use App\Services\HospedagemService;
use App\Services\QuartoEstoqueService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;

class QuartoController extends Controller
{
    public function __construct(
        protected HospedagemService $hospedagemService,
        protected QuartoEstoqueService $quartoEstoqueService,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', Quarto::class);

        $quartos = Quarto::with('unidade')->orderBy('unidade_id')->orderBy('numero')->paginate(15);

        return view('quartos.index', compact('quartos'));
    }

    public function create(): View
    {
        $this->authorize('create', Quarto::class);

        $unidades = Unidade::ativas()->orderBy('nome')->get();

        return view('quartos.create', compact('unidades'));
    }

    public function store(StoreQuartoRequest $request): RedirectResponse
    {
        $quarto = Quarto::create($request->validated());

        return redirect()->route('quartos.index')->with('sucesso', "Quarto \"{$quarto->numero}\" criado com sucesso.");
    }

    public function edit(Quarto $quarto): View
    {
        $this->authorize('update', $quarto);

        $unidades = Unidade::ativas()->orderBy('nome')->get();
        $bloqueios = $quarto->bloqueios()->ativos()->with('hospedagem.cliente')->orderBy('inicio')->get();

        return view('quartos.edit', compact('quarto', 'unidades', 'bloqueios'));
    }

    public function update(StoreQuartoRequest $request, Quarto $quarto): RedirectResponse
    {
        $quarto->update($request->validated());

        return redirect()->route('quartos.index')->with('sucesso', 'Quarto atualizado com sucesso.');
    }

    /** Salva o link do calendário do Booking.com e já sincroniza. */
    public function salvarIcal(Request $request, Quarto $quarto, IcalQuartoService $ical): RedirectResponse
    {
        $this->authorize('update', $quarto);

        $dados = $request->validate(['ical_importacao_url' => ['nullable', 'url', 'max:1000']]);
        $url = trim((string) ($dados['ical_importacao_url'] ?? ''));
        $quarto->forceFill(['ical_importacao_url' => $url ?: null, 'ical_erro' => null])->save();

        if ($url === '') {
            $quarto->bloqueios()->whereNull('hospedagem_id')->delete();

            return back()->with('sucesso', 'Sincronização com o calendário externo desligada para este quarto.');
        }

        return $this->executarSincronizacao($quarto, $ical);
    }

    public function sincronizarIcal(Quarto $quarto, IcalQuartoService $ical): RedirectResponse
    {
        $this->authorize('update', $quarto);

        return $this->executarSincronizacao($quarto, $ical);
    }

    /** Novo link de exportação (o antigo para de funcionar no Booking). */
    public function novoLinkIcal(Quarto $quarto): RedirectResponse
    {
        $this->authorize('update', $quarto);

        $quarto->forceFill(['ical_token' => \Illuminate\Support\Str::random(40)])->save();

        return back()->with('sucesso', 'Link novo gerado. Atualize o link importado no extranet do Booking.com.');
    }

    /** Passo a passo da ativação e do uso da sincronização com o Booking.com. */
    public function icalPassoAPasso(Request $request): \Illuminate\Http\Response
    {
        $this->authorize('viewAny', Quarto::class);

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('quartos.pdf.ical-passo-a-passo', [
            'empresa' => \App\Models\Empresa::findOrFail($request->user()->empresa_id),
        ])->setPaper('a4')->setOption('isFontSubsettingEnabled', true)->download('passo-a-passo-booking-ical.pdf');
    }

    protected function executarSincronizacao(Quarto $quarto, IcalQuartoService $ical): RedirectResponse
    {
        try {
            $r = $ical->sincronizar($quarto);
        } catch (NegocioException $e) {
            return back()->with('erro', $e->getMessage());
        }

        $msg = "Calendário sincronizado: {$r['criados']} nova(s), {$r['atualizados']} alterada(s), {$r['removidos']} cancelada(s).";

        return $r['conflitos'] > 0
            ? back()->with('sucesso', $msg)->with('erro', "Atenção: {$r['conflitos']} reserva(s) do canal caem em datas que já tinham hóspede no ERP (overbooking). Veja os bloqueios em vermelho.")
            : back()->with('sucesso', $msg);
    }

    public function limpar(Quarto $quarto): RedirectResponse
    {
        $this->authorize('limpar', $quarto);

        $this->hospedagemService->marcarQuartoLimpo($quarto);

        return back()->with('sucesso', "Quarto \"{$quarto->numero}\" marcado como limpo.");
    }

    /**
     * Visão geral: o que tem hoje em cada quarto (itens de comodato).
     */
    public function estoqueGeral(): View
    {
        $this->authorize('viewAny', Quarto::class);

        $quartos = $this->quartoEstoqueService->visaoGeral();

        return view('quartos.estoque-geral', compact('quartos'));
    }

    /**
     * Detalhe de um quarto: itens emprestados agora + form de emprestar +
     * histórico de movimentações (comodato/devolução) daquele produto.
     */
    public function estoque(Quarto $quarto): View
    {
        $this->authorize('view', $quarto);

        $itens = $this->quartoEstoqueService->itensDoQuarto($quarto);
        $produtos = Produto::ativos()->orderBy('nome')->get();

        $historico = \App\Models\MovimentacaoEstoque::whereIn('tipo', ['comodato', 'devolucao_comodato'])
            ->where('referencia_type', Quarto::class)
            ->where('referencia_id', $quarto->id)
            ->with(['produto', 'usuario'])
            ->latest()
            ->limit(30)
            ->get();

        return view('quartos.estoque', compact('quarto', 'itens', 'produtos', 'historico'));
    }

    public function emprestar(Request $request, Quarto $quarto): RedirectResponse
    {
        $this->authorize('comodato', $quarto);

        $dados = $request->validate([
            'produto_id' => ['required', Rule::exists('produtos', 'id')->where('empresa_id', $quarto->empresa_id)],
            'quantidade' => ['required', 'integer', 'min:1', 'max:9999'],
        ]);

        try {
            $this->quartoEstoqueService->emprestar($quarto, Produto::findOrFail($dados['produto_id']), (int) $dados['quantidade'], $request->user());
        } catch (NegocioException $e) {
            return back()->with('erro', $e->getMessage());
        }

        return back()->with('sucesso', 'Item emprestado ao quarto.');
    }

    public function devolver(Request $request, Quarto $quarto, Produto $produto): RedirectResponse
    {
        $this->authorize('comodato', $quarto);

        $dados = $request->validate([
            'quantidade' => ['required', 'integer', 'min:1', 'max:9999'],
        ]);

        try {
            $this->quartoEstoqueService->devolver($quarto, $produto, (int) $dados['quantidade'], $request->user());
        } catch (NegocioException $e) {
            return back()->with('erro', $e->getMessage());
        }

        return back()->with('sucesso', 'Item devolvido ao estoque geral.');
    }
}
