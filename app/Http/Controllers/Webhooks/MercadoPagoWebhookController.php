<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessarWebhookMercadoPagoJob;
use App\Models\Empresa;
use App\Models\WebhookMercadoPago;
use App\Services\Integrations\MercadoPagoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Endpoint público (sem autenticação de sessão — protegido pela validação
 * de assinatura do Mercado Pago) que recebe as notificações de pagamento.
 * Compartilhado por TODAS as empresas do SaaS (o Mercado Pago não sabe de
 * "empresas" — cada uma cadastra esta MESMA URL no próprio painel deles).
 *
 * Responsabilidade única deste controller: descobrir de qual empresa é o
 * webhook (batendo a assinatura contra o segredo de cada uma que tiver
 * configurado a própria conta), validar, GRAVAR o payload bruto
 * (idempotência/auditoria) e devolver 200 rapidamente — o processamento de
 * verdade (achar a mensalidade/venda, dar baixa) é feito de forma
 * assíncrona no Job, para não travar o webhook do Mercado Pago à espera de
 * queries/transactions.
 */
class MercadoPagoWebhookController extends Controller
{
    public function __invoke(Request $request, MercadoPagoService $mercadoPagoPlataforma): JsonResponse
    {
        $dataId = $request->input('data.id') ?? $request->input('id');
        $tipo = $request->input('type') ?? $request->input('topic');
        $xSignature = (string) $request->header('x-signature');
        $xRequestId = (string) $request->header('x-request-id');

        [$empresa, $assinaturaValida] = $this->identificarEmpresa($xSignature, $xRequestId, (string) $dataId, $mercadoPagoPlataforma);

        // Exige assinatura válida sempre que HOUVER algum segredo
        // configurado (da plataforma ou de qualquer empresa) — não
        // depender de APP_ENV=='production'. Sem segredo nenhum
        // configurado (dev local, sem credencial real do Mercado Pago)
        // segue sem bloquear.
        $existeSegredoConfigurado = filled(config('mercadopago.webhook_secret'))
            || Empresa::whereNotNull('configuracoes->mercadopago->webhook_secret')->exists();

        if (! $assinaturaValida && $existeSegredoConfigurado) {
            Log::warning('Webhook Mercado Pago rejeitado: assinatura inválida', [
                'ip' => $request->ip(),
                'data_id' => $dataId,
            ]);

            return response()->json(['message' => 'assinatura inválida'], 401);
        }

        $webhook = WebhookMercadoPago::create([
            'empresa_id' => $empresa?->id,
            'gateway_id' => $dataId,
            'tipo' => $tipo,
            'payload' => $request->all(),
            'status' => 'recebido',
        ]);

        ProcessarWebhookMercadoPagoJob::dispatch($webhook->id);

        // Mercado Pago espera um 2xx rápido; qualquer processamento demorado
        // é feito pelo Job em fila (evita timeout/reentrega desnecessária).
        return response()->json(['status' => 'recebido'], 200);
    }

    /**
     * Tenta achar qual empresa tem credencial PRÓPRIA cujo segredo bate com
     * a assinatura recebida; se nenhuma bater (ou nenhuma empresa tiver
     * credencial própria), tenta a credencial única da plataforma (.env) —
     * esse é o caso comum hoje, de uma empresa só usando o sistema.
     *
     * @return array{0: ?Empresa, 1: bool}
     */
    protected function identificarEmpresa(string $xSignature, string $xRequestId, string $dataId, MercadoPagoService $mercadoPagoPlataforma): array
    {
        $empresasComCredencialPropria = Empresa::whereNotNull('configuracoes->mercadopago->webhook_secret')->get();

        foreach ($empresasComCredencialPropria as $empresa) {
            if (MercadoPagoService::paraEmpresa($empresa)->validarAssinaturaWebhook($xSignature, $xRequestId, $dataId)) {
                return [$empresa, true];
            }
        }

        if ($mercadoPagoPlataforma->validarAssinaturaWebhook($xSignature, $xRequestId, $dataId)) {
            return [null, true];
        }

        return [null, false];
    }
}
