<?php

namespace App\Services;

use App\Exceptions\IntegrationException;
use App\Models\Cidade;
use Illuminate\Support\Facades\Http;

/**
 * Sincroniza municípios brasileiros a partir da API pública do IBGE.
 *
 * @see https://servicodados.ibge.gov.br/api/docs/localidades
 */
class IbgeCidadeService
{
    public const URL_MUNICIPIOS = 'https://servicodados.ibge.gov.br/api/v1/localidades/municipios';

    /**
     * @return array{total_api: int, inseridos: int, atualizados: int, ignorados: int}
     */
    public function sincronizarApi(?string $uf = null): array
    {
        set_time_limit(0);

        $uf = $uf !== null ? strtoupper(trim($uf)) : null;
        if ($uf !== null && $uf !== '' && ! in_array($uf, Cidade::estados(), true)) {
            throw new IntegrationException('UF inválida para sincronização de cidades.', 'ibge_cidades');
        }

        $url = self::URL_MUNICIPIOS;
        if ($uf) {
            $url = "https://servicodados.ibge.gov.br/api/v1/localidades/estados/{$uf}/municipios";
        }

        $response = Http::retry(2, 1000)
            ->timeout(180)
            ->acceptJson()
            ->withUserAgent('ParqueAquaticoSaaS/1.0 (sincronizacao-cidades-ibge)')
            ->get($url);

        if (! $response->successful()) {
            throw new IntegrationException(
                'Erro ao baixar municípios do IBGE: HTTP '.$response->status(),
                'ibge_cidades',
            );
        }

        $itens = $response->json();
        if (! is_array($itens) || count($itens) === 0) {
            throw new IntegrationException('Resposta da API do IBGE inválida ou vazia.', 'ibge_cidades');
        }

        $existentes = Cidade::query()
            ->when($uf, fn ($q) => $q->where('uf', $uf))
            ->pluck('id', 'codigo')
            ->all();

        $inseridos = 0;
        $atualizados = 0;
        $ignorados = 0;
        $temCodigoValido = false;
        $agora = now();
        $lote = [];

        foreach ($itens as $item) {
            if (! is_array($item)) {
                continue;
            }

            $codigo = preg_replace('/\D/', '', (string) ($item['id'] ?? ''));
            if (strlen($codigo) !== 7) {
                continue;
            }
            $temCodigoValido = true;

            $nome = mb_substr(trim((string) ($item['nome'] ?? '')), 0, 120);
            $sigla = $this->extrairUf($item);
            if ($nome === '' || $sigla === null) {
                $ignorados++;

                continue;
            }

            if (isset($existentes[$codigo])) {
                $atualizados++;
            } else {
                $inseridos++;
                $existentes[$codigo] = true;
            }

            $lote[] = [
                'nome' => $nome,
                'uf' => $sigla,
                'codigo' => $codigo,
                'created_at' => $agora,
                'updated_at' => $agora,
            ];

            if (count($lote) >= 500) {
                Cidade::upsert($lote, ['codigo'], ['nome', 'uf', 'updated_at']);
                $lote = [];
            }
        }

        if (count($lote) > 0) {
            Cidade::upsert($lote, ['codigo'], ['nome', 'uf', 'updated_at']);
        }

        if (! $temCodigoValido) {
            throw new IntegrationException('Resposta da API do IBGE não contém municípios válidos.', 'ibge_cidades');
        }

        return [
            'total_api' => count($itens),
            'inseridos' => $inseridos,
            'atualizados' => $atualizados,
            'ignorados' => $ignorados,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function extrairUf(array $item): ?string
    {
        $sigla = data_get($item, 'microrregiao.mesorregiao.UF.sigla')
            ?? data_get($item, 'regiao-imediata.regiao-intermediaria.UF.sigla')
            ?? data_get($item, 'UF.sigla');

        if (! is_string($sigla) || strlen($sigla) !== 2) {
            return null;
        }

        return strtoupper($sigla);
    }
}
