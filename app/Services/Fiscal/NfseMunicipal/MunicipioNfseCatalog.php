<?php

namespace App\Services\Fiscal\NfseMunicipal;

use App\Exceptions\NegocioException;
use Illuminate\Support\Facades\File;

/**
 * Lookup IBGE → provedor/URLs, espelhando ACBrNFSeXServicos.ini.
 */
class MunicipioNfseCatalog
{
    /** @var array{provedores?:array<string,array>,municipios?:array<string,array>}|null */
    protected static ?array $cache = null;

    /**
     * @return array{
     *   ibge:string,
     *   nome:string,
     *   uf:string,
     *   provedor:string,
     *   versao:string,
     *   params:list<string>,
     *   url_producao:string,
     *   url_homologacao:string
     * }
     */
    public function resolve(string $codigoIbge): array
    {
        $ibge = preg_replace('/\D+/', '', $codigoIbge) ?: '';
        if (strlen($ibge) !== 7) {
            throw new NegocioException('Informe o código IBGE do município (7 dígitos) em Dados da empresa.');
        }

        $data = $this->data();
        $m = $data['municipios'][$ibge] ?? null;
        if (! is_array($m)) {
            throw new NegocioException("Município IBGE {$ibge} não encontrado no catálogo NFS-e (ACBr).");
        }

        return [
            'ibge' => $ibge,
            'nome' => (string) ($m['nome'] ?? ''),
            'uf' => (string) ($m['uf'] ?? ''),
            'provedor' => (string) ($m['provedor'] ?? ''),
            'versao' => (string) ($m['versao'] ?? ''),
            'params' => array_values(array_map('strval', $m['params'] ?? [])),
            'url_producao' => (string) ($m['url_producao'] ?? ''),
            'url_homologacao' => (string) ($m['url_homologacao'] ?? ''),
        ];
    }

    public function temParam(array $municipio, string $param): bool
    {
        foreach ($municipio['params'] ?? [] as $p) {
            if (strcasecmp((string) $p, $param) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{provedores:array<string,array>,municipios:array<string,array>}
     */
    protected function data(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $path = resource_path('fiscal/nfse_municipios.json');
        if (! File::exists($path)) {
            throw new NegocioException('Catálogo NFS-e municipal ausente (resources/fiscal/nfse_municipios.json).');
        }

        $json = json_decode(File::get($path), true);
        if (! is_array($json) || ! isset($json['municipios']) || ! is_array($json['municipios'])) {
            throw new NegocioException('Catálogo NFS-e municipal inválido.');
        }

        self::$cache = $json;

        return self::$cache;
    }

    public static function clearCache(): void
    {
        self::$cache = null;
    }
}
