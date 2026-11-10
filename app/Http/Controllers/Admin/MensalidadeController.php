<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\IntegrationException;
use App\Exceptions\NegocioException;
use App\Http\Controllers\Controller;
use App\Services\CobrancaWhatsappFila;
use App\Models\Mensalidade;
use App\Services\CaixaService;
use App\Services\Integrations\MercadoPagoService;
use App\Services\MensalidadeService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class MensalidadeController extends Controller
{
    public function __construct(
        protected MensalidadeService $mensalidadeService,
        protected CaixaService $caixaService,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', Mensalidade::class);

        $filtros = request()->only(['status', 'competencia', 'contrato_id']);
        $mensalidades = Mensalidade::query()
            ->with('contrato.cliente')
            ->when($filtros['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filtros['competencia'] ?? null, fn ($q, $v) => $q->whereDate('competencia', $v))
            ->latest('data_vencimento')
            ->paginate(20)
            ->withQueryString();

        $resumo = [
            'pendentes' => Mensalidade::where('status', 'pendente')->count(),
            'em_dia' => Mensalidade::where('status', 'pago')->count(),
            'atrasadas' => Mensalidade::where('status', 'atrasado')->count(),
        ];

        return view('mensalidades.index', compact('mensalidades', 'resumo'));
    }

    public function show(Mensalidade $mensalidade): View
    {
        $this->authorize('view', $mensalidade);

        $mensalidade->load(['contrato.cliente', 'cobrancas', 'pagamentos']);
        $caixasAbertos = $this->caixasDisponiveis();
        // Já vem marcado o caixa em que o operador está (PDV/última baixa) ou o único aberto.
        $caixaSugerido = $caixasAbertos->firstWhere('id', session('pdv_caixa_id'))?->id
            ?? ($caixasAbertos->count() === 1 ? $caixasAbertos->first()->id : null);

        return view('mensalidades.show', compact('mensalidade', 'caixasAbertos', 'caixaSugerido'));
    }

    /**
     * Baixa manual: usada quando o cliente paga presencialmente
     * (dinheiro/cartão) na recepção. O operador escolhe em qual caixa/
     * terminal aberto o dinheiro entra (antes ia para o primeiro encontrado).
     */
    public function baixarManual(Request $request, Mensalidade $mensalidade): RedirectResponse
    {
        $this->authorize('baixarManual', $mensalidade);

        $dados = $request->validate([
            'metodo_pagamento' => ['required', 'in:dinheiro,cartao_credito,cartao_debito,pix_manual'],
            'caixa_id' => ['required', 'integer'],
        ], ['caixa_id.required' => 'Escolha em qual caixa/terminal o pagamento vai entrar.']);

        $caixa = $this->caixasDisponiveis()->firstWhere('id', (int) $dados['caixa_id']);

        if (! $caixa) {
            return back()->with('erro', 'O caixa escolhido não está aberto (ou é de outra unidade). Atualize a tela e escolha outro.');
        }

        // Lembra o terminal escolhido (o mesmo que o PDV usa) para as próximas baixas.
        session(['pdv_caixa_id' => $caixa->id]);

        $this->mensalidadeService->marcarComoPaga(
            $mensalidade,
            gateway: 'manual',
            metodoPagamento: $dados['metodo_pagamento'],
            caixa: $caixa,
            usuarioId: $request->user()->id,
        );

        return back()->with('sucesso', 'Pagamento registrado no caixa '.($caixa->terminal?->nome ?? '#'.$caixa->id).'.');
    }

    /** Caixas abertos que o usuário pode usar: os da unidade dele, ou da empresa toda (sem unidade fixa). */
    protected function caixasDisponiveis(): \Illuminate\Support\Collection
    {
        $user = request()->user();

        return ($user->unidade_id
            ? $this->caixaService->caixasAbertosDaUnidade((int) $user->unidade_id)
            : $this->caixaService->caixasAbertosDaEmpresa((int) $user->empresa_id))
            ->load('usuarioAbertura')
            ->sortBy(fn ($c) => $c->terminal?->nome)->values();
    }

    /**
     * Gera uma cobrança Pix via Mercado Pago para esta mensalidade e
     * devolve os dados do QR Code para exibição na tela (copia-e-cola + imagem).
     */
    public function gerarPix(Mensalidade $mensalidade, MercadoPagoService $mercadoPago): RedirectResponse
    {
        $this->authorize('view', $mensalidade);

        try {
            $descricao = $mensalidade->ehCaucao()
                ? "Caução do contrato {$mensalidade->contrato->numero_contrato} - {$mensalidade->contrato->cliente->nome}"
                : "Mensalidade {$mensalidade->competencia->format('m/Y')} - {$mensalidade->contrato->cliente->nome}";

            $cobranca = $mercadoPago->criarCobrancaPix(
                valor: (float) $mensalidade->valor_total,
                descricao: $descricao,
                referenciaExterna: "mensalidade:{$mensalidade->id}",
                emailPagador: $mensalidade->contrato->cliente->email ?: 'sememail@parqueaquatico.com.br',
            );
        } catch (IntegrationException $e) {
            return back()->with('erro', 'Não foi possível gerar o Pix agora: '.$e->getMessage());
        }

        $mensalidade->update(['gateway_transaction_id' => $cobranca['id'] ?? null]);

        return back()->with('pix', $cobranca['point_of_interaction']['transaction_data'] ?? null);
    }

    /**
     * Dispara uma cobrança de WhatsApp avulsa (fora da régua automática),
     * reaproveitando o mesmo job/notification do envio agendado.
     */
    public function cobrarWhatsapp(Mensalidade $mensalidade): RedirectResponse
    {
        $this->authorize('cobrar', $mensalidade);

        if ($mensalidade->estaPaga()) {
            return back()->with('erro', 'Esta mensalidade já está paga.');
        }

        if (! app(CobrancaWhatsappFila::class)->agendar($mensalidade)) {
            return back()->with('erro', 'Esta mensalidade já está na fila de envio do WhatsApp.');
        }

        return back()->with('sucesso', $this->mensagemFila(1, (int) $mensalidade->empresa_id));
    }

    /** Fila espaçada (anti-banimento): estimativa de quando a última sai. */
    protected function mensagemFila(int $agendadas, int $empresaId): string
    {
        $naFila = app(CobrancaWhatsappFila::class)->naFila($empresaId);
        $intervalo = (int) config('parque.cobranca_intervalo_minutos', 20);
        $minutos = max(0, $naFila - 1) * $intervalo;
        $previsao = $minutos < 60 ? "{$minutos} min" : intdiv($minutos, 60).'h'.str_pad((string) ($minutos % 60), 2, '0', STR_PAD_LEFT);

        return "{$agendadas} cobrança(s) na fila do WhatsApp. Para evitar bloqueio do número, sai 1 mensagem a cada {$intervalo} min"
            .' (das '.config('parque.cobranca_janela_inicio').'h às '.config('parque.cobranca_janela_fim')."h). Na fila agora: {$naFila} — previsão para esvaziar: {$previsao}.";
    }

    /**
     * Mesma coisa, em lote — recebe uma lista de ids selecionados na tela.
     * Cada mensalidade é reconferida (empresa + policy) antes de disparar,
     * pra não confiar apenas nos ids marcados no navegador.
     */
    public function cobrarWhatsappLote(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('financeiro.enviar_cobranca'), 403);

        $ids = array_filter(array_map('intval', explode(',', (string) $request->input('ids', ''))));

        $mensalidades = Mensalidade::whereIn('id', $ids)
            ->where('status', '!=', 'pago')
            ->get();

        $enviadas = 0;

        foreach ($mensalidades as $mensalidade) {
            if (! $request->user()->can('cobrar', $mensalidade)) {
                continue;
            }

            $enviadas += (int) (bool) app(CobrancaWhatsappFila::class)->agendar($mensalidade);
        }

        if ($enviadas === 0) {
            return back()->with('erro', 'Nenhuma cobrança nova para a fila (já pagas, já na fila ou sem seleção).');
        }

        return back()->with('sucesso', $this->mensagemFila($enviadas, (int) $request->user()->empresa_id));
    }

    public function alterarVencimento(Request $request, Mensalidade $mensalidade): RedirectResponse
    {
        $this->authorize('alterarVencimento', $mensalidade);

        $dados = $request->validate([
            'data_vencimento' => ['required', 'date'],
        ]);

        try {
            $this->mensalidadeService->alterarVencimento(
                $mensalidade,
                Carbon::parse($dados['data_vencimento'])
            );
        } catch (NegocioException $e) {
            return back()->with('erro', $e->getMessage());
        }

        return back()->with('sucesso', 'Data de vencimento atualizada.');
    }

    public function alterarVencimentoLote(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('contratos.editar'), 403);

        $dados = $request->validate([
            'ids' => ['required', 'string'],
            'data_vencimento' => ['required', 'date'],
        ]);

        $ids = array_filter(array_map('intval', explode(',', $dados['ids'])));
        $mensalidades = Mensalidade::query()->whereIn('id', $ids)->get();
        $autorizadas = [];

        foreach ($mensalidades as $mensalidade) {
            if ($request->user()->can('alterarVencimento', $mensalidade)) {
                $autorizadas[] = $mensalidade->id;
            }
        }

        if ($autorizadas === []) {
            return back()->with('erro', 'Nenhuma cobrança elegível foi selecionada.');
        }

        $resultado = $this->mensalidadeService->alterarVencimentoEmLote(
            $autorizadas,
            Carbon::parse($dados['data_vencimento'])
        );

        if ($resultado['alteradas'] === 0) {
            return back()->with('erro', 'Nenhuma cobrança em aberto pôde ser atualizada.');
        }

        $msg = "{$resultado['alteradas']} vencimento(s) atualizado(s).";
        if ($resultado['ignoradas'] > 0) {
            $msg .= " {$resultado['ignoradas']} ignorada(s) (já pagas/canceladas).";
        }

        return back()->with('sucesso', $msg);
    }
}
