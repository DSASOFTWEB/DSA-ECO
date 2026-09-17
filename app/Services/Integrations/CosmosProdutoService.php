<?php

namespace App\Services\Integrations;

use App\Exceptions\IntegrationException;
use App\Models\Empresa;
use Illuminate\Support\Facades\Http;

/**
 * Consulta produto por EAN/GTIN na API Cosmos (Bluesoft) — gratuita com token.
 * Token: empresa.bluesoft_token → fallback COSMOS_TOKEN (.env).
 *
 * Docs: https://cosmos.bluesoft.com.br/api
 */
class CosmosProdutoService
{
    public function consultarPorEan(string $ean, ?Empresa $empresa = null): array
    {
        $ean = preg_replace('/\D+/', '', $ean) ?? '';
        if (strlen($ean) < 8 || strlen($ean) > 14) {
            throw new IntegrationException(
                'Informe um EAN/GTIN válido (8 a 14 dígitos).',
                servico: 'cosmos',
            );
        }

        $token = $empresa ? $empresa->tokenCosmos() : (string) config('parque.cosmos_token', '');
        if ($token === '') {
            throw new IntegrationException(
                'Token Cosmos não configurado na empresa nem em COSMOS_TOKEN (.env).',
                servico: 'cosmos',
            );
        }

        $base = rtrim((string) config('parque.cosmos_base_url', 'https://api.cosmos.bluesoft.com.br'), '/');

        $response = Http::timeout((int) config('parque.cosmos_timeout', 15))
            ->withHeaders([
                'X-Cosmos-Token' => $token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
            ->get("{$base}/gtins/{$ean}.json");

        if ($response->status() === 404) {
            throw new IntegrationException(
                'EAN não encontrado na base Cosmos.',
                servico: 'cosmos',
                respostaBruta: $response->json(),
            );
        }

        if (! $response->successful()) {
            throw new IntegrationException(
                'Falha na consulta Cosmos (HTTP '.$response->status().').',
                servico: 'cosmos',
                respostaBruta: ['status' => $response->status(), 'body' => $response->body()],
            );
        }

        $data = $response->json() ?? [];

        $ncm = data_get($data, 'ncm.code')
            ?? data_get($data, 'ncm')
            ?? null;
        if (is_array($ncm)) {
            $ncm = $ncm['code'] ?? null;
        }
        $ncm = $ncm ? preg_replace('/\D+/', '', (string) $ncm) : null;

        $imagem = data_get($data, 'thumbnail')
            ?? data_get($data, 'gpc.image')
            ?? data_get($data, 'image')
            ?? null;

        $unidade = data_get($data, 'net_weight.unit');
        $unidade = is_string($unidade) && $unidade !== '' ? strtoupper($unidade) : 'UN';

        return [
            'ean' => (string) (data_get($data, 'gtin') ?? $ean),
            'nome' => (string) (data_get($data, 'description') ?? data_get($data, 'description_type') ?? ''),
            'marca' => (string) (data_get($data, 'brand.name') ?? (is_string(data_get($data, 'brand')) ? data_get($data, 'brand') : '')),
            'ncm' => $ncm && strlen($ncm) === 8 ? $ncm : null,
            'imagem_url' => is_string($imagem) ? $imagem : null,
            'unidade_comercial' => $unidade,
        ];
    }
}
