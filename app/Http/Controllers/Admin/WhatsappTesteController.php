<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\IntegrationException;
use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Services\Integrations\EvolutionApiService;
use App\Support\Telefone;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Botão "Testar" da Evolution API (Minha Empresa → Gerencial): confere a
 * conexão da instância e, com um número, envia um texto (como a régua de
 * mensalidade) e um PDF (como a carteirinha). Usa a configuração salva.
 */
class WhatsappTesteController extends Controller
{
    public function testar(Request $request): JsonResponse
    {
        Gate::authorize('empresa.gerenciar');
        $dados = $request->validate(['telefone' => ['nullable', 'string', 'max:20']]);

        $empresa = Empresa::findOrFail($request->user()->empresa_id);
        $api = EvolutionApiService::paraEmpresa($empresa);

        if (! $api->configurada()) {
            return response()->json(['message' => 'Preencha URL, API Key e nome da instância e clique em "Salvar alterações" antes de testar.'], 422);
        }

        $numero = null;
        if (filled($dados['telefone'] ?? null) && ! ($numero = Telefone::paraWhatsapp($dados['telefone']))) {
            return response()->json(['message' => 'Número inválido. Use DDD, ex.: (83) 99999-9999.'], 422);
        }

        $etapas = [];

        // 1) Conexão da instância (URL, API Key, nome e celular pareado).
        try {
            $status = $api->statusConexao();
            $estado = (string) (data_get($status, 'instance.state') ?? data_get($status, 'state') ?? '');
            $etapas[] = match ($estado) {
                'open' => ['ok' => true, 'etapa' => 'Conexão', 'texto' => 'Instância conectada ao WhatsApp.'],
                'connecting' => ['ok' => false, 'etapa' => 'Conexão', 'texto' => 'Instância conectando — aguarde ou leia o QR Code de novo no painel da Evolution.'],
                'close' => ['ok' => false, 'etapa' => 'Conexão', 'texto' => 'Instância DESCONECTADA: leia o QR Code com o celular da empresa no painel da Evolution.'],
                default => ['ok' => false, 'etapa' => 'Conexão', 'texto' => 'A Evolution respondeu, mas sem o estado da instância ('.($estado ?: 'vazio').').'],
            };
        } catch (IntegrationException $e) {
            $etapas[] = ['ok' => false, 'etapa' => 'Conexão', 'texto' => $api->explicar($e)->getMessage()];
        }

        // 2) e 3) Envio real para o número informado.
        if ($numero) {
            try {
                $api->enviarTexto($numero, "✅ Teste do sistema ({$empresa->nome}): o WhatsApp da empresa está enviando mensagens.");
                $etapas[] = ['ok' => true, 'etapa' => 'Mensagem de texto', 'texto' => "Enviada para +{$numero} (como a cobrança de mensalidade)."];
            } catch (IntegrationException $e) {
                $etapas[] = ['ok' => false, 'etapa' => 'Mensagem de texto', 'texto' => $api->explicar($e)->getMessage()];
            }

            try {
                $pdf = Pdf::loadHTML('<h2 style="font-family:DejaVu Sans">Teste de envio de arquivo</h2><p style="font-family:DejaVu Sans">'
                    .e($empresa->nome).' — '.now()->format('d/m/Y H:i').'</p>')->output();
                $api->enviarDocumento($numero, base64_encode($pdf), 'Teste-WhatsApp.pdf', 'application/pdf', 'Teste de envio de arquivo (como a carteirinha).');
                $etapas[] = ['ok' => true, 'etapa' => 'Arquivo PDF', 'texto' => "Enviado para +{$numero} (como a carteirinha)."];
            } catch (IntegrationException $e) {
                $etapas[] = ['ok' => false, 'etapa' => 'Arquivo PDF', 'texto' => $e->getMessage()];
            }
        }

        return response()->json(['ok' => collect($etapas)->every('ok'), 'etapas' => $etapas]);
    }
}
