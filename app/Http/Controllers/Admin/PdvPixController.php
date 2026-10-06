<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\IntegrationException;
use App\Exceptions\NegocioException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Venda\StoreVendaRequest;
use App\Models\Caixa;
use App\Models\CobrancaPix;
use App\Models\Venda;
use App\Services\PdvPixService;
use App\Services\QrCodeService;
use Illuminate\Http\JsonResponse;

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

        try {
            $cobranca = $this->pdvPixService->iniciar($request->validated(), $request->user(), $caixa);
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

        return response()->json($this->resposta($cobrancaPix));
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

    protected function autorizar(CobrancaPix $cobrancaPix): void
    {
        $this->authorize('create', Venda::class);
        abort_unless((int) $cobrancaPix->empresa_id === (int) request()->user()->empresa_id, 404);
    }

    protected function resposta(CobrancaPix $cobranca, bool $comQrCode = false): array
    {
        return array_filter([
            'id' => $cobranca->id,
            'status' => $cobranca->status,
            'valor' => (float) $cobranca->valor,
            'expira_em' => $cobranca->expira_em?->toIso8601String(),
            'erro' => $cobranca->erro,
            'redirect' => $cobranca->venda_id ? route('vendas.comprovante', $cobranca->venda_id) : null,
            'copia_e_cola' => $comQrCode ? $cobranca->pix_copia_e_cola : null,
            'qr_code' => $comQrCode ? $this->qrCodeService->gerarDataUri($cobranca->pix_copia_e_cola, 260) : null,
            'status_url' => route('vendas.pix.status', $cobranca),
            'cancelar_url' => route('vendas.pix.cancelar', $cobranca),
        ], fn ($valor) => $valor !== null);
    }
}
