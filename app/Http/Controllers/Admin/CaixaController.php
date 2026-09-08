<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\NegocioException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Caixa\AbrirCaixaRequest;
use App\Http\Requests\Caixa\FecharCaixaRequest;
use App\Http\Requests\Caixa\MovimentarCaixaRequest;
use App\Models\Caixa;
use App\Models\Terminal;
use App\Services\CaixaService;
use App\Services\Relatorios\CaixaPdfExport;
use App\Services\VendaService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class CaixaController extends Controller
{
    public function __construct(
        protected CaixaService $caixaService,
        protected VendaService $vendaService,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', Caixa::class);

        $caixas = Caixa::with(['unidade', 'terminal', 'usuarioAbertura'])->latest('data_abertura')->paginate(15);

        $user = request()->user();
        $terminais = Terminal::with('unidade')->ativos()
            ->when($user->unidade_id, fn ($q, $unidadeId) => $q->where('unidade_id', $unidadeId))
            ->orderBy('nome')
            ->get();

        $terminaisComCaixaAbertoIds = Caixa::where('status', 'aberto')->pluck('terminal_id')->filter()->all();

        return view('caixas.index', compact('caixas', 'terminais', 'terminaisComCaixaAbertoIds'));
    }

    public function show(Caixa $caixa): View
    {
        $this->authorize('view', $caixa);

        $caixa->load(['movimentacoes.usuario', 'unidade', 'terminal', 'usuarioAbertura', 'usuarioFechamento']);

        return view('caixas.show', compact('caixa'));
    }

    public function pdf(Caixa $caixa, CaixaPdfExport $export): Response
    {
        $this->authorize('view', $caixa);

        return $export->gerar($caixa)->stream("caixa-{$caixa->id}.pdf");
    }

    public function abrir(AbrirCaixaRequest $request): RedirectResponse
    {
        try {
            $caixa = $this->caixaService->abrir(
                Terminal::findOrFail($request->validated()['terminal_id']),
                $request->user(),
                (float) $request->validated()['valor_abertura'],
            );
        } catch (NegocioException $e) {
            return back()->with('erro', $e->getMessage());
        }

        $reconciliadas = $this->vendaService->reconciliarVendasOnlinePendentes($caixa);
        $mensagem = 'Caixa aberto com sucesso.'.($reconciliadas > 0 ? " {$reconciliadas} venda(s) online pendente(s) lançada(s) neste caixa." : '');

        return redirect()->route('caixas.show', $caixa)->with('sucesso', $mensagem);
    }

    public function movimentar(MovimentarCaixaRequest $request, Caixa $caixa): RedirectResponse
    {
        try {
            $this->caixaService->registrarMovimentacao($caixa, [
                ...$request->validated(),
                'usuario_id' => $request->user()->id,
            ]);
        } catch (NegocioException $e) {
            return back()->with('erro', $e->getMessage());
        }

        return back()->with('sucesso', 'Movimentação registrada.');
    }

    public function fechar(FecharCaixaRequest $request, Caixa $caixa): RedirectResponse
    {
        try {
            $this->caixaService->fechar($caixa, $request->user(), (float) $request->validated()['valor_fechamento_informado']);
        } catch (NegocioException $e) {
            return back()->with('erro', $e->getMessage());
        }

        return redirect()->route('caixas.show', $caixa)->with('sucesso', 'Caixa fechado com sucesso.')->with('caixa_recem_fechado', $caixa->id);
    }
}
