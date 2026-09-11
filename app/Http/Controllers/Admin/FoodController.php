<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Food\FecharAtendimentoRequest;
use App\Http\Requests\Food\StoreAtendimentoItemRequest;
use App\Http\Requests\Food\StorePontoAtendimentoRequest;
use App\Http\Requests\Food\TransferirAtendimentoRequest;
use App\Http\Requests\Food\UpdatePontoAtendimentoRequest;
use App\Models\Atendimento;
use App\Models\AtendimentoItem;
use App\Models\Caixa;
use App\Models\PontoAtendimento;
use App\Models\Produto;
use App\Models\Unidade;
use App\Services\Food\AtendimentoService;
use App\Services\Impressao\ContaAtendimentoEscPosBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class FoodController extends Controller
{
    public function __construct(protected AtendimentoService $atendimentos) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', PontoAtendimento::class);
        $unidades = Unidade::ativas()->orderBy('nome')->get();
        $unidadeId = $request->integer('unidade_id') ?: ($request->user()->unidade_id ?: $unidades->first()?->id);
        abort_unless($unidades->contains('id', $unidadeId), 404);
        $tipo = in_array($request->input('tipo'), ['mesa', 'comanda'], true) ? $request->input('tipo') : 'mesa';
        $pontos = PontoAtendimento::with(['atendimentoAtual.itensAtivos'])
            ->where('unidade_id', $unidadeId)->where('tipo', $tipo)
            ->orderBy('ordem')->orderBy('numero')->get();

        return view('food.mapa', compact('unidades', 'unidadeId', 'tipo', 'pontos'));
    }

    public function storePonto(StorePontoAtendimentoRequest $request): RedirectResponse
    {
        $resultado = $this->atendimentos->criarPontosEmFaixa($request->user(), $request->validated());

        $rotulo = $resultado['tipo'] === 'mesa' ? 'mesa(s)' : 'comanda(s)';
        $msg = $resultado['criados'] === 1
            ? '1 '.$rotulo.' cadastrada com sucesso (nº '.$resultado['inicial'].').'
            : $resultado['criados'].' '.$rotulo.' cadastradas (nº '.$resultado['inicial'].' a '.$resultado['final'].').';

        return redirect()
            ->route('food.index', [
                'unidade_id' => $request->integer('unidade_id'),
                'tipo' => $resultado['tipo'],
            ])
            ->with('sucesso', $msg);
    }

    public function updatePonto(UpdatePontoAtendimentoRequest $request, PontoAtendimento $ponto): RedirectResponse
    {
        $this->atendimentos->configurarPonto($ponto, $request->validated(), $request->user());

        return back()->with('sucesso', "{$ponto->fresh()->identificacao} atualizado com sucesso.");
    }

    public function abrir(Request $request, PontoAtendimento $ponto): RedirectResponse
    {
        $this->authorize('operate', $ponto);
        $dados = $request->validate(['quantidade_pessoas' => ['nullable', 'integer', 'min:1', 'max:999']]);
        $atendimento = $this->atendimentos->abrir($ponto, $request->user(), (int) ($dados['quantidade_pessoas'] ?? 1));

        return redirect()->route('food.atendimentos.show', $atendimento)->with('sucesso', 'Atendimento aberto.');
    }

    public function show(Atendimento $atendimento): View
    {
        $this->authorize('view', $atendimento);
        $atendimento->load(['ponto', 'unidade', 'cliente', 'abertoPor', 'itens.produto']);
        $produtos = Produto::ativos()->where(fn ($query) => $query
            ->whereNull('unidade_id')->orWhere('unidade_id', $atendimento->unidade_id))
            ->orderBy('nome')->get();
        $destinos = PontoAtendimento::where('unidade_id', $atendimento->unidade_id)
            ->where('id', '!=', $atendimento->ponto_atendimento_id)
            ->whereNotIn('status', ['reservada', 'bloqueada'])->orderBy('tipo')->orderBy('numero')->get();
        $caixas = Caixa::where('unidade_id', $atendimento->unidade_id)->where('status', 'aberto')->with('terminal')->get();

        return view('food.atendimento', compact('atendimento', 'produtos', 'destinos', 'caixas'));
    }

    public function adicionarItem(StoreAtendimentoItemRequest $request, Atendimento $atendimento): RedirectResponse
    {
        $this->atendimentos->adicionarItem($atendimento, $request->validated(), $request->user());

        return back()->with('sucesso', 'Item lançado na conta.');
    }

    public function cancelarItem(Request $request, Atendimento $atendimento, AtendimentoItem $item): RedirectResponse
    {
        $this->authorize('operate', $atendimento);
        abort_unless($item->atendimento_id === $atendimento->id, 404);
        $dados = $request->validate(['motivo' => ['required', 'string', 'min:3', 'max:500']]);
        $this->atendimentos->cancelarItem($item, $request->user(), $dados['motivo']);

        return back()->with('sucesso', 'Item cancelado e mantido no histórico.');
    }

    public function preFechar(Request $request, Atendimento $atendimento): RedirectResponse
    {
        $this->authorize('operate', $atendimento);
        $this->atendimentos->preFechar($atendimento, $request->user());

        return redirect()->route('food.atendimentos.conta', $atendimento)->with('sucesso', 'Pré-conta gerada.');
    }

    public function transferir(TransferirAtendimentoRequest $request, Atendimento $atendimento): RedirectResponse
    {
        $destino = PontoAtendimento::findOrFail($request->integer('ponto_destino_id'));
        $atendimentoDestino = $this->atendimentos->transferir($atendimento, $destino, $request->user());

        return redirect()->route('food.atendimentos.show', $atendimentoDestino)->with('sucesso', 'Conta transferida com sucesso.');
    }

    public function fechar(FecharAtendimentoRequest $request, Atendimento $atendimento): RedirectResponse
    {
        $atendimento = $this->atendimentos->fechar($atendimento, $request->validated(), $request->user());

        return redirect()->route('vendas.show', $atendimento->venda_id)->with('sucesso', 'Conta fechada e lançada no caixa.');
    }

    public function conta(Atendimento $atendimento): View
    {
        $this->authorize('view', $atendimento);
        $atendimento->load(['ponto', 'unidade', 'cliente', 'itens.produto']);

        return view('food.conta', compact('atendimento'));
    }

    public function contaEscpos(Atendimento $atendimento): Response
    {
        $this->authorize('view', $atendimento);
        $colunas = $atendimento->empresa?->configuracaoImpressao()['colunas'] ?? (int) config('parque.escpos_colunas', 48);
        $bytes = (new ContaAtendimentoEscPosBuilder($colunas))->montar($atendimento);

        return response($bytes, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="conta-atendimento-'.$atendimento->id.'.bin"',
            'X-Escpos-Columns' => (string) $colunas,
        ]);
    }
}
