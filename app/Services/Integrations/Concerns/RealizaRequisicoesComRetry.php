<?php

namespace App\Services\Integrations\Concerns;

use App\Exceptions\IntegrationException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

/**
 * Concentra a política de resiliência (timeout + retry com backoff
 * exponencial + logging estruturado) que toda integração externa deste
 * sistema precisa seguir, conforme exigido pelo escopo do projeto:
 * "Integrações desacopladas em Services com timeout/retry/logs".
 *
 * Usada por EvolutionApiService e MercadoPagoService — qualquer nova
 * integração externa (gateway fiscal, outro provedor de WhatsApp, etc.)
 * deve reutilizar esta trait em vez de repetir a lógica de retry.
 */
trait RealizaRequisicoesComRetry
{
    protected function client(int $timeoutSegundos, array $opcoesBase = []): Client
    {
        return new Client(array_merge([
            'timeout' => $timeoutSegundos,
            'connect_timeout' => min(5, $timeoutSegundos),
            'http_errors' => true,
        ], $opcoesBase));
    }

    /**
     * @throws IntegrationException quando todas as tentativas falham
     */
    protected function comRetry(string $servico, string $operacao, callable $requisicao, int $maxTentativas, int $delayMs): mixed
    {
        $tentativa = 0;
        $ultimaExcecao = null;

        while ($tentativa < $maxTentativas) {
            $tentativa++;

            try {
                $inicio = microtime(true);
                $resultado = $requisicao();

                Log::channel(config('logging.default'))->info("[{$servico}] {$operacao} - sucesso", [
                    'tentativa' => $tentativa,
                    'duracao_ms' => (int) ((microtime(true) - $inicio) * 1000),
                ]);

                return $resultado;
            } catch (ConnectException|RequestException $e) {
                $ultimaExcecao = $e;
                $status = $e instanceof RequestException ? $e->getResponse()?->getStatusCode() : null;

                Log::warning("[{$servico}] {$operacao} - falha na tentativa {$tentativa}/{$maxTentativas}", [
                    'status_http' => $status,
                    'erro' => $e->getMessage(),
                ]);

                // Erros 4xx (exceto 429) são erro do cliente/payload: repetir não vai adiantar.
                if ($status !== null && $status >= 400 && $status < 500 && $status !== 429) {
                    break;
                }

                if ($tentativa < $maxTentativas) {
                    usleep($delayMs * 1000 * (2 ** ($tentativa - 1))); // backoff exponencial
                }
            } catch (GuzzleException $e) {
                $ultimaExcecao = $e;
                Log::error("[{$servico}] {$operacao} - erro inesperado", ['erro' => $e->getMessage()]);
                break;
            }
        }

        throw new IntegrationException(
            "Falha ao comunicar com {$servico} ({$operacao}) após {$tentativa} tentativa(s).",
            servico: $servico,
            previous: $ultimaExcecao,
        );
    }
}
