<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\IntegrationException;
use App\Exceptions\NegocioException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Food\FecharAtendimentoRequest;
use App\Http\Requests\Hospedagem\CheckoutHospedagemRequest;
use App\Http\Requests\Venda\StoreVendaRequest;
use App\Models\Atendimento;
use App\Models\Caixa;
use App\Models\CobrancaPix;
use App\Models\Empresa;
use App\Models\Hospedagem;
use App\Models\Venda;
use App\Services\Impressao\PixQrEscPosBuilder;
use App\Services\PdvPixService;
use App\Services\QrCodeService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Tela de recebimento Pix do PDV (QR Code dinâmico do gateway da empresa).
 * O front faz polling em status() até o banco confirmar o pagamento.
 */
class PdvPixController extends Controller
{
    public function __construct(
        protected PdvPixService $pdvPixService,
        protected QrCodeService $qrCodeService,
    ) {}

    public function store(StoreVendaRequest $request): JsonResponse
    {
        $caixa = Caixa::query()
            ->whereKey($request->integer('caixa_id'))
            ->where('status', 'aberto')
            ->when($request->user()->unidade_id, fn ($q, $unidadeId) => $q->where('unidade_id', $unidadeId))
            ->first();

        if (! $caixa) {
            return response()->json(['message' => 'Abra o caixa da unidade antes de registrar uma venda.'], 422);
        }

        $forma = $request->formaPagamentoCadastrada();
        if ($request->filled('forma_pagamento_id') && ! $forma?->ehPixIntegrado()) {
            return response()->json(['message' => 'A forma de pagamento escolhida não é Pix integrado.'], 422);
        }

        try {
            $cobranca = $this->pdvPixService->iniciar($request->validated(), $request->user(), $caixa);
        } catch (NegocioException|IntegrationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->resposta($cobranca, comQrCode: true), 201);
    }

    /**
     * Pix integrado no fechamento de mesa/comanda: mesmo polling do PDV,
     * mas ao confirmar o pagamento fecha a conta em vez de criar venda avulsa.
     */
    public function storeAtendimento(FecharAtendimentoRequest $request, Atendimento $atendimento): JsonResponse
    {
        if (! $request->formaPagamentoCadastrada(0)?->ehPixIntegrado()) {
            return response()->json(['message' => 'A forma de pagamento escolhida não é Pix integrado.'], 422);
        }

        $caixa = Caixa::find($request->integer('caixa_id'));

        try {
            $cobranca = $this->pdvPixService->iniciarAtendimento($atendimento, $request->validated(), $request->user(), $caixa);
        } catch (NegocioException|IntegrationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->resposta($cobranca, comQrCode: true), 201);
    }

    /**
     * Pix integrado no check-out da hospedagem: ao confirmar o pagamento
     * finaliza a estadia e emite as notas marcadas.
     */
    public function storeHospedagem(CheckoutHospedagemRequest $request, Hospedagem $hospedagem): JsonResponse
    {
        if (! $request->formaPagamentoCadastrada()?->ehPixIntegrado()) {
            return response()->json(['message' => 'A forma de pagamento escolhida não é Pix integrado.'], 422);
        }

        $caixa = Caixa::query()
            ->whereKey($request->integer('caixa_id'))
            ->where('status', 'aberto')
            ->when($request->user()->unidade_id, fn ($q, $unidadeId) => $q->where('unidade_id', $unidadeId))
            ->first();

        if (! $caixa) {
            return response()->json(['message' => 'Abra o caixa antes de fazer o check-out.'], 422);
        }

        try {
            $cobranca = $this->pdvPixService->iniciarHospedagem($hospedagem, $request->validated(), $request->user(), $caixa);
        } catch (NegocioException|IntegrationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->resposta($cobranca, comQrCode: true), 201);
    }

    public function status(CobrancaPix $cobrancaPix): JsonResponse
    {
        $this->autorizar($cobrancaPix);

        try {
            $cobrancaPix = $this->pdvPixService->atualizar($cobrancaPix);
        } catch (NegocioException|IntegrationException $e) {
            return response()->json(array_merge($this->resposta($cobrancaPix), ['aviso' => $e->getMessage()]));
        }

        $resposta = $this->resposta($cobrancaPix);

        if (isset($resposta['redirect']) && $cobrancaPix->hospedagem_id) {
            $this->avisarCheckout($cobrancaPix);
        }

        return response()->json($resposta);
    }

    /**
     * QR Code da cobrança para imprimir na térmica 80mm e entregar ao cliente.
     * Abre numa janela à parte: o modal do Pix segue aguardando o pagamento.
     */
    public function imprimir(CobrancaPix $cobrancaPix): View
    {
        $this->autorizar($cobrancaPix);
        $empresa = Empresa::findOrFail($cobrancaPix->empresa_id);
        $impressao = $empresa->configuracaoImpressao();

        return view('vendas.pix-imprimir', [
            'cobranca' => $cobrancaPix,
            'empresaNome' => $empresa->nome,
            'referencia' => $this->referencia($cobrancaPix),
            'qrCode' => $this->qrCodeService->gerarDataUri($cobrancaPix->pix_copia_e_cola, 300),
            'impressaoModo' => $impressao['modo'],
            'escposColunas' => $impressao['colunas'],
            'escposAgenteUrl' => $impressao['agente_url'],
        ]);
    }

    public function imprimirEscpos(CobrancaPix $cobrancaPix): Response
    {
        $this->autorizar($cobrancaPix);
        $empresa = Empresa::findOrFail($cobrancaPix->empresa_id);
        $bytes = (new PixQrEscPosBuilder($empresa->configuracaoImpressao()['colunas']))
            ->montarCobranca($cobrancaPix, $empresa->nome, $this->referencia($cobrancaPix));

        return response($bytes, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="pix-qrcode-'.$cobrancaPix->id.'.bin"',
        ]);
    }

    /** Linha que identifica a cobrança no papel: venda do PDV, mesa/comanda ou quarto. */
    protected function referencia(CobrancaPix $cobranca): string
    {
        if ($cobranca->atendimento_id) {
            $ponto = Atendimento::withoutGlobalScopes()->with('ponto')->find($cobranca->atendimento_id)?->ponto;

            return $ponto ? ucfirst($ponto->identificacao) : 'Mesa/comanda';
        }

        if ($cobranca->hospedagem_id) {
            $quarto = Hospedagem::withoutGlobalScopes()->with('quarto')->find($cobranca->hospedagem_id)?->quarto;

            return 'Hospedagem'.($quarto ? ' - Quarto '.$quarto->numero : '');
        }

        return 'Venda PDV';
    }

    /**
     * Mesma mensagem do check-out comum, mostrada na tela da hospedagem
     * para onde o front redireciona.
     */
    protected function avisarCheckout(CobrancaPix $cobranca): void
    {
        $fiscal = $cobranca->dados_venda['fiscal'] ?? ['emitidos' => [], 'falhas' => []];

        session()->flash('sucesso', 'Pix confirmado e check-out realizado com sucesso.'
            .($fiscal['emitidos'] ? ' '.implode(' e ', $fiscal['emitidos']).' emitida(s).' : ''));

        if ($fiscal['falhas']) {
            session()->flash('erro', 'A conta foi fechada, mas houve falha fiscal: '.implode(' | ', $fiscal['falhas']));
        }
    }

    public function cancelar(CobrancaPix $cobrancaPix): JsonResponse
    {
        $this->autorizar($cobrancaPix);

        try {
            $cobrancaPix = $this->pdvPixService->cancelar($cobrancaPix);
        } catch (NegocioException|IntegrationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->resposta($cobrancaPix));
    }

    /**
     * Quem acompanha o Pix precisa da permissão da tela de origem: fechar a
     * mesa, fazer o check-out ou vender no PDV.
     */
    protected function autorizar(CobrancaPix $cobrancaPix): void
    {
        abort_unless((int) $cobrancaPix->empresa_id === (int) request()->user()->empresa_id, 404);

        match (true) {
            (bool) $cobrancaPix->atendimento_id => $this->authorize('close', Atendimento::withoutGlobalScopes()->findOrFail($cobrancaPix->atendimento_id)),
            (bool) $cobrancaPix->hospedagem_id => $this->authorize('checkout', Hospedagem::withoutGlobalScopes()->findOrFail($cobrancaPix->hospedagem_id)),
            default => $this->authorize('create', Venda::class),
        };
    }

    protected function resposta(CobrancaPix $cobranca, bool $comQrCode = false): array
    {
        return array_filter([
            'id' => $cobranca->id,
            'status' => $cobranca->status,
            'valor' => (float) $cobranca->valor,
            'expira_em' => $cobranca->expira_em?->toIso8601String(),
            'erro' => $cobranca->erro,
            'redirect' => match (true) {
                (bool) $cobranca->hospedagem_id => $this->pdvPixService->concluida($cobranca) ? route('hospedagens.show', $cobranca->hospedagem_id) : null,
                ! $cobranca->venda_id => null,
                (bool) $cobranca->atendimento_id => route('vendas.show', $cobranca->venda_id),
                default => route('vendas.comprovante', $cobranca->venda_id),
            },
            'copia_e_cola' => $comQrCode ? $cobranca->pix_copia_e_cola : null,
            'qr_code' => $comQrCode ? $this->qrCodeService->gerarDataUri($cobranca->pix_copia_e_cola, 260) : null,
            'status_url' => route('vendas.pix.status', $cobranca),
            'cancelar_url' => route('vendas.pix.cancelar', $cobranca),
            'imprimir_url' => route('vendas.pix.imprimir', $cobranca),
        ], fn ($valor) => $valor !== null);
    }
}
