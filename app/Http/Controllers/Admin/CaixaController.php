<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\NegocioException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Caixa\AbrirCaixaRequest;
use App\Http\Requests\Caixa\FecharCaixaRequest;
use App\Http\Requests\Caixa\MovimentarCaixaRequest;
use App\Models\Caixa;
use App\Models\CaixaMovimentacao;
use App\Models\Terminal;
use App\Services\CaixaService;
use App\Services\Impressao\CaixaEscPosBuilder;
use App\Services\Relatorios\CaixaFechamentoResumo;
use App\Services\Relatorios\CaixaPdfExport;
use App\Services\VendaService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    /**
     * Fechamento (ou parcial) na bobina 80mm: imprime sozinho ao abrir,
     * pelo navegador ou ESC/POS, conforme a configuração de impressão.
     */
    public function bobina(Caixa $caixa, CaixaFechamentoResumo $resumo): View
    {
        $this->authorize('view', $caixa);

        $impressao = $caixa->empresa->configuracaoImpressao();

        return view('caixas.bobina', [
            'r' => $resumo->montar($caixa),
            'impressaoModo' => $impressao['modo'],
            'escposColunas' => $impressao['colunas'],
            'escposAgenteUrl' => $impressao['agente_url'],
            'comMovimentacoes' => ! request()->boolean('resumido'),
        ]);
    }

    public function bobinaEscpos(Caixa $caixa, CaixaFechamentoResumo $resumo): Response
    {
        $this->authorize('view', $caixa);

        $bytes = (new CaixaEscPosBuilder($caixa->empresa->configuracaoImpressao()['colunas']))
            ->montarFechamento($resumo->montar($caixa), ! request()->boolean('resumido'));

        return response($bytes, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => "attachment; filename=\"fechamento-caixa-{$caixa->id}.bin\"",
        ]);
    }

    /** Dinheiro na gaveta agora (janela de sangria/suprimento). */
    public function gaveta(Caixa $caixa): JsonResponse
    {
        $this->authorize('view', $caixa);

        return response()->json(['dinheiro' => $this->caixaService->dinheiroEmGaveta($caixa)]);
    }

    /**
     * Sangria (tira dinheiro da gaveta) ou suprimento (reforço de troco):
     * sempre em dinheiro, com comprovante 80mm para assinar. Responde JSON
     * para o PDV e redireciona na tela do caixa.
     */
    public function sangriaSuprimento(Request $request, Caixa $caixa, string $tipo): JsonResponse|RedirectResponse
    {
        $this->authorize('registrarMovimentacao', $caixa);

        $dados = $request->validate([
            'valor' => ['required', 'numeric', 'min:0.01', 'max:9999999'],
            'motivo' => [$tipo === 'sangria' ? 'required' : 'nullable', 'string', 'max:255'],
        ], ['motivo.required' => 'Informe o motivo da sangria (ex.: recolhimento para o cofre).']);

        try {
            $mov = $tipo === 'sangria'
                ? $this->caixaService->sangria($caixa, (float) $dados['valor'], trim($dados['motivo']), $request->user())
                : $this->caixaService->suprimento($caixa, (float) $dados['valor'], isset($dados['motivo']) ? trim($dados['motivo']) : null, $request->user());
        } catch (NegocioException $e) {
            return $request->expectsJson()
                ? response()->json(['message' => $e->getMessage()], 422)
                : back()->withInput()->with('erro', $e->getMessage());
        }

        $rotulo = $tipo === 'sangria' ? 'Sangria' : 'Suprimento';
        $mensagem = "{$rotulo} de R$ ".number_format((float) $mov->valor, 2, ',', '.').' registrada.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $mensagem,
                'dinheiro' => $this->caixaService->dinheiroEmGaveta($caixa),
                'comprovante_url' => route('caixas.movimentacoes.comprovante', [$caixa, $mov]),
            ], 201);
        }

        return back()->with('sucesso', $mensagem)->with('comprovante_movimentacao', $mov->id);
    }

    /** Comprovante 80mm da sangria/suprimento, com linhas de assinatura. */
    public function comprovanteMovimentacao(Caixa $caixa, CaixaMovimentacao $movimentacao): View
    {
        $this->authorize('view', $caixa);
        abort_unless((int) $movimentacao->caixa_id === (int) $caixa->id, 404);

        $impressao = $caixa->empresa->configuracaoImpressao();

        return view('caixas.comprovante-movimentacao', [
            'caixa' => $caixa->loadMissing('unidade', 'terminal', 'empresa'),
            'mov' => $movimentacao->loadMissing('usuario'),
            'gaveta' => $this->caixaService->dinheiroEmGaveta($caixa),
            'impressaoModo' => $impressao['modo'],
            'escposColunas' => $impressao['colunas'],
            'escposAgenteUrl' => $impressao['agente_url'],
        ]);
    }

    public function comprovanteMovimentacaoEscpos(Caixa $caixa, CaixaMovimentacao $movimentacao): Response
    {
        $this->authorize('view', $caixa);
        abort_unless((int) $movimentacao->caixa_id === (int) $caixa->id, 404);

        $bytes = (new CaixaEscPosBuilder($caixa->empresa->configuracaoImpressao()['colunas']))
            ->montarComprovanteMovimentacao($caixa->loadMissing('unidade', 'terminal', 'empresa'), $movimentacao->loadMissing('usuario'));

        return response($bytes, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => "attachment; filename=\"{$movimentacao->categoria}-{$movimentacao->id}.bin\"",
        ]);
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
        $dados = $request->validated();

        try {
            // Sangria/suprimento pelo formulário genérico seguem as mesmas regras
            // dos botões próprios: sempre em dinheiro e sangria limitada à gaveta.
            if ($dados['tipo'] === 'saida' && $dados['categoria'] === 'sangria') {
                $this->caixaService->sangria($caixa, (float) $dados['valor'], trim((string) ($dados['descricao'] ?? '')) ?: 'Sangria', $request->user());
            } elseif ($dados['tipo'] === 'entrada' && $dados['categoria'] === 'suprimento') {
                $this->caixaService->suprimento($caixa, (float) $dados['valor'], $dados['descricao'] ?? null, $request->user());
            } else {
                $this->caixaService->registrarMovimentacao($caixa, [...$dados, 'usuario_id' => $request->user()->id]);
            }
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
