<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\IntegrationException;
use App\Http\Controllers\Controller;
use App\Jobs\EnviarCobrancaWhatsappJob;
use App\Models\Mensalidade;
use App\Services\CaixaService;
use App\Services\Integrations\MercadoPagoService;
use App\Services\MensalidadeService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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
        $caixaAberto = $this->caixaService->caixaAbertoDaUnidade(request()->user()->unidade);

        return view('mensalidades.show', compact('mensalidade', 'caixaAberto'));
    }

    /**
     * Baixa manual: usada quando o cliente paga presencialmente
     * (dinheiro/cartão) na recepção, exigindo um caixa aberto na unidade.
     */
    public function baixarManual(Request $request, Mensalidade $mensalidade): RedirectResponse
    {
        $this->authorize('baixarManual', $mensalidade);

        $dados = $request->validate([
            'metodo_pagamento' => ['required', 'in:dinheiro,cartao_credito,cartao_debito,pix_manual'],
        ]);

        $caixa = $this->caixaService->caixaAbertoDaUnidade($request->user()->unidade);

        if (! $caixa) {
            return back()->with('erro', 'Abra o caixa da unidade antes de registrar o pagamento.');
        }

        $this->mensalidadeService->marcarComoPaga(
            $mensalidade,
            gateway: 'manual',
            metodoPagamento: $dados['metodo_pagamento'],
            caixa: $caixa,
            usuarioId: $request->user()->id,
        );

        return back()->with('sucesso', 'Pagamento registrado com sucesso.');
    }

    /**
     * Gera uma cobrança Pix via Mercado Pago para esta mensalidade e
     * devolve os dados do QR Code para exibição na tela (copia-e-cola + imagem).
     */
    public function gerarPix(Mensalidade $mensalidade, MercadoPagoService $mercadoPago): RedirectResponse
    {
        $this->authorize('view', $mensalidade);

        try {
            $cobranca = $mercadoPago->criarCobrancaPix(
                valor: (float) $mensalidade->valor_total,
                descricao: "Mensalidade {$mensalidade->competencia->format('m/Y')} - {$mensalidade->contrato->cliente->nome}",
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

        EnviarCobrancaWhatsappJob::dispatch($mensalidade->id);

        return back()->with('sucesso', 'Cobrança enviada para a fila de envio.');
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

            EnviarCobrancaWhatsappJob::dispatch($mensalidade->id);
            $enviadas++;
        }

        if ($enviadas === 0) {
            return back()->with('erro', 'Nenhuma cobrança elegível foi selecionada.');
        }

        return back()->with('sucesso', "{$enviadas} cobrança(s) enviada(s) para a fila de envio.");
    }
}
