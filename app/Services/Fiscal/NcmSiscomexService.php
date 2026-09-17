<?php

namespace App\Services\Fiscal;

use App\Exceptions\IntegrationException;
use App\Models\Ncm;
use Illuminate\Support\Facades\Http;

/**
 * Baixa a nomenclatura NCM oficial do Portal Único Siscomex
 * e grava códigos de 8 dígitos na tabela local `ncms`.
 */
class NcmSiscomexService
{
    public const URL_SISCOMEX = 'https://portalunico.siscomex.gov.br/classif/api/publico/nomenclatura/download/json';

    /**
     * @return array{total_api: int, inseridos: int, ignorados: int}
     */
    public function sincronizarApi(bool $apenasNovos = true): array
    {
        set_time_limit(0);

        $response = Http::retry(2, 1000)
            ->timeout(300)
            ->acceptJson()
            ->withUserAgent('ParqueAquaticoSaaS/1.0 (sincronizacao-ncm)')
            ->get(self::URL_SISCOMEX, ['perfil' => 'PUBLICO']);

        if (! $response->successful()) {
            throw new IntegrationException(
                'Erro ao baixar NCM Siscomex: HTTP '.$response->status(),
                'siscomex_ncm',
            );
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            $body = preg_replace('/^\xEF\xBB\xBF/', '', trim($response->body()));
            $payload = json_decode($body, true);
        }

        $itens = $payload['Nomenclaturas'] ?? $payload['nomenclaturas'] ?? $payload;
        if (! is_array($itens) || count($itens) === 0) {
            throw new IntegrationException('Resposta da API Siscomex inválida ou vazia.', 'siscomex_ncm');
        }

        $existentes = [];
        if ($apenasNovos) {
            $existentes = Ncm::query()
                ->select('ncm', 'ex')
                ->get()
                ->mapWithKeys(fn ($row) => [$row->ncm.'|'.$row->ex => true])
                ->all();
        } else {
            Ncm::query()->delete();
        }

        $inseridos = 0;
        $ignorados = 0;
        $temCodigoValido = false;
        $lote = [];
        $agora = now();

        foreach ($itens as $item) {
            if (! is_array($item)) {
                continue;
            }

            $codigo = preg_replace('/\D/', '', (string) ($item['Codigo'] ?? $item['codigo'] ?? ''));
            if (strlen($codigo) !== 8) {
                continue;
            }
            $temCodigoValido = true;

            $ex = '';
            $chave = $codigo.'|'.$ex;
            if ($apenasNovos && isset($existentes[$chave])) {
                $ignorados++;

                continue;
            }

            $lote[] = [
                'ncm' => $codigo,
                'ex' => $ex,
                'descricao' => mb_strtoupper(mb_substr(trim((string) ($item['Descricao'] ?? $item['descricao'] ?? '')), 0, 500)),
                'fonte' => 'API',
                'created_at' => $agora,
                'updated_at' => $agora,
            ];

            if (count($lote) >= 500) {
                $inseridos += $this->inserirLote($lote, $apenasNovos);
                $lote = [];
            }
        }

        if (count($lote) > 0) {
            $inseridos += $this->inserirLote($lote, $apenasNovos);
        }

        if (! $temCodigoValido) {
            throw new IntegrationException('Resposta da API Siscomex não contém NCMs válidos.', 'siscomex_ncm');
        }

        return [
            'total_api' => count($itens),
            'inseridos' => $inseridos,
            'ignorados' => $ignorados,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lote
     */
    private function inserirLote(array $lote, bool $apenasNovos): int
    {
        if ($apenasNovos) {
            Ncm::insertOrIgnore($lote);
        } else {
            Ncm::insert($lote);
        }

        return count($lote);
    }
}
