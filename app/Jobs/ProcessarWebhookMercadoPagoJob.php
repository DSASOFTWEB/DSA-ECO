<?php

namespace App\Jobs;

use App\Models\Mensalidade;
use App\Models\Venda;
use App\Models\WebhookMercadoPago;
use App\Services\Integrations\MercadoPagoService;
use App\Services\MensalidadeService;
use App\Services\VendaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Processa de forma assíncrona um webhook do Mercado Pago já persistido em
 * `webhooks_mercadopago` (ver MercadoPagoWebhookController). Consulta o
 * pagamento real na API (nunca confia cegamente no payload do webhook,
 * conforme recomendação oficial do Mercado Pago) e dá baixa na mensalidade
 * ou venda correspondente via `external_reference`.
 */
class ProcessarWebhookMercadoPagoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public array $backoff = [30, 60, 300, 900, 3600];

    public function __construct(protected int $webhookId) {}

    public function handle(MensalidadeService $mensalidadeService, VendaService $vendaService): void
    {
        $webhook = WebhookMercadoPago::find($this->webhookId);

        if (! $webhook || $webhook->status === 'processado') {
            return; // já processado (idempotência) ou removido
        }

        if ($webhook->tipo && ! str_contains($webhook->tipo, 'payment')) {
            // Este sistema só reage a eventos de pagamento (não a merchant_order, etc.)
            $webhook->update(['status' => 'ignorado', 'processado_em' => now()]);

            return;
        }

        // Mesma credencial que validou a assinatura na hora do recebimento
        // (ver MercadoPagoWebhookController::identificarEmpresa) — consultar
        // com o token errado (de outra empresa, ou o global quando o
        // pagamento é de uma empresa com conta própria) dá 404/403 no
        // Mercado Pago, já que o token só enxerga os pagamentos da própria conta.
        $mercadoPago = MercadoPagoService::paraEmpresa($webhook->empresa);

        try {
            $pagamentoRemoto = $mercadoPago->consultarPagamento((string) $webhook->gateway_id);
            $referenciaExterna = $pagamentoRemoto['external_reference'] ?? null;
            $status = $pagamentoRemoto['status'] ?? null; // approved, pending, rejected, refunded...

            if ($status !== 'approved') {
                $webhook->update([
                    'status' => 'processado',
                    'processado_em' => now(),
                    'erro' => "Pagamento com status '{$status}' — nenhuma baixa realizada.",
                ]);

                return;
            }

            DB::transaction(function () use ($referenciaExterna, $webhook, $pagamentoRemoto, $mensalidadeService, $vendaService) {
                [$tipo, $id] = array_pad(explode(':', (string) $referenciaExterna, 2), 2, null);

                if ($tipo === 'mensalidade' && $id) {
                    $mensalidade = Mensalidade::find($id);

                    if ($mensalidade) {
                        $mensalidadeService->marcarComoPaga(
                            $mensalidade,
                            gateway: 'mercadopago',
                            gatewayPaymentId: (string) $webhook->gateway_id,
                            metodoPagamento: $pagamentoRemoto['payment_method_id'] ?? 'pix',
                            payload: $pagamentoRemoto,
                        );
                    }
                } elseif ($tipo === 'venda' && $id) {
                    $venda = Venda::find($id);

                    if ($venda) {
                        $vendaService->confirmarPagamentoOnline($venda, (string) $webhook->gateway_id, $pagamentoRemoto);
                    }
                }

                $webhook->update(['status' => 'processado', 'processado_em' => now()]);
            });
        } catch (\Throwable $e) {
            $webhook->increment('tentativas');
            $webhook->update(['status' => 'erro', 'erro' => $e->getMessage()]);

            Log::error('Falha ao processar webhook Mercado Pago', [
                'webhook_id' => $webhook->id,
                'erro' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
