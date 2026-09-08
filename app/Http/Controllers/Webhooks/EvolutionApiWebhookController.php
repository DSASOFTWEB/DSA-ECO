<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Cobranca;
use App\Models\Empresa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Recebe callbacks de status de entrega/leitura da Evolution API
 * (ex.: mensagem entregue, lida, ou falha de envio) e atualiza o
 * registro de cobrança correspondente, quando aplicável. Endpoint
 * compartilhado por todas as empresas (mesma ideia do webhook do Mercado
 * Pago — ver MercadoPagoWebhookController).
 *
 * A Evolution API não garante um formato único de payload entre versões;
 * este handler é propositalmente tolerante (não falha se um campo
 * esperado não vier) e sempre responde 200 para evitar reentrega em loop.
 */
class EvolutionApiWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $apikeyRecebida = (string) $request->header('apikey');

        // Confere a apikey contra a da plataforma (.env) e/ou a de qualquer
        // empresa com instância própria configurada — descobre de quebra
        // qual empresa é, pra só atualizar Cobranca dela (nunca de outra).
        $empresaAutenticada = Empresa::whereNotNull('configuracoes->evolution->api_key')
            ->get()
            ->first(fn (Empresa $empresa) => hash_equals($empresa->credenciaisEvolution()['api_key'], $apikeyRecebida));

        $chaveDaPlataforma = (string) config('evolution.api_key');
        $autenticadoPelaPlataforma = $chaveDaPlataforma !== '' && hash_equals($chaveDaPlataforma, $apikeyRecebida);

        $existeChaveConfigurada = $chaveDaPlataforma !== '' || Empresa::whereNotNull('configuracoes->evolution->api_key')->exists();

        if (! $empresaAutenticada && ! $autenticadoPelaPlataforma && $existeChaveConfigurada) {
            Log::warning('Webhook Evolution API rejeitado: apikey inválida ou ausente', ['ip' => $request->ip()]);

            return response()->json(['message' => 'não autorizado'], 401);
        }

        Log::info('Webhook Evolution API recebido', ['payload' => $request->all()]);

        $messageId = $request->input('data.key.id') ?? $request->input('messageId');
        $status = $request->input('data.status') ?? $request->input('status');

        if ($messageId && $status) {
            $cobranca = Cobranca::where('resposta_gateway->key->id', $messageId)
                ->orWhere('resposta_gateway->id', $messageId)
                ->first();

            // Quando dá pra saber de qual empresa é o webhook (apikey de
            // uma empresa específica bateu), só atualiza se a cobrança for
            // dessa mesma empresa — evita que a instância de WhatsApp de
            // uma empresa mexa em cobrança de outra.
            $pertenceAEmpresaCorreta = ! $empresaAutenticada
                || (int) $cobranca?->mensalidade?->empresa_id === $empresaAutenticada->id;

            if ($cobranca && $pertenceAEmpresaCorreta) {
                $cobranca->update(['status' => str_contains(strtolower($status), 'read') ? 'lido' : 'enviado']);
            }
        }

        return response()->json(['status' => 'ok']);
    }
}
