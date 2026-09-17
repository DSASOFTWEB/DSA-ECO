<?php

namespace App\Services\Integrations;

use App\Exceptions\IntegrationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Consulta pública de CNPJ (publica.cnpj.ws → fallback ReceitaWS).
 */
class CnpjConsultaService
{
    /**
     * @return array{
     *   cnpj: string,
     *   nome: string,
     *   razao_social: string,
     *   ie: ?string,
     *   email: ?string,
     *   telefone: ?string,
     *   endereco: ?string,
     *   cep: ?string,
     *   codigo_municipio_ibge: ?string,
     *   cidade: ?string,
     *   uf: ?string,
     *   cnae: ?string,
     *   fonte: string
     * }
     */
    public function consultar(string $cnpj): array
    {
        $digitos = preg_replace('/\D+/', '', $cnpj) ?: '';
        if (strlen($digitos) !== 14) {
            throw new IntegrationException('Informe um CNPJ com 14 dígitos.', 'cnpj');
        }

        try {
            return $this->viaCnpjWs($digitos);
        } catch (\Throwable $e) {
            Log::warning('Falha consulta CNPJ publica.cnpj.ws: '.$e->getMessage());
        }

        try {
            return $this->viaReceitaWs($digitos);
        } catch (\Throwable $e) {
            Log::warning('Falha consulta CNPJ ReceitaWS: '.$e->getMessage());
        }

        throw new IntegrationException('Não foi possível consultar o CNPJ agora. Tente novamente ou preencha manualmente.', 'cnpj');
    }

    /**
     * @return array<string, mixed>
     */
    protected function viaCnpjWs(string $digitos): array
    {
        $response = Http::timeout(20)
            ->acceptJson()
            ->withUserAgent('ParqueAquaticoSaaS/1.0')
            ->get('https://publica.cnpj.ws/cnpj/'.$digitos);

        if (! $response->successful()) {
            throw new IntegrationException('CNPJ não encontrado (HTTP '.$response->status().').', 'cnpj');
        }

        $data = $response->json();
        if (! is_array($data) || empty($data['estabelecimento'])) {
            throw new IntegrationException('Resposta inválida da consulta de CNPJ.', 'cnpj');
        }

        $est = $data['estabelecimento'];
        $ie = '';
        $inscricoes = $est['inscricoes_estaduais'] ?? [];
        if (is_array($inscricoes) && $inscricoes !== []) {
            $ie = (string) ($inscricoes[0]['inscricao_estadual'] ?? '');
        }

        $logradouro = trim(((string) ($est['tipo_logradouro'] ?? '')).' '.((string) ($est['logradouro'] ?? '')));
        $numero = (string) ($est['numero'] ?? 'S/N');
        $bairro = (string) ($est['bairro'] ?? '');
        $cidade = (string) ($est['cidade']['nome'] ?? '');
        $uf = (string) ($est['estado']['sigla'] ?? ($est['cidade']['estado']['sigla'] ?? ''));
        $cep = $this->formatarCep((string) ($est['cep'] ?? ''));
        $ibge = isset($est['cidade']['ibge_id']) ? (string) $est['cidade']['ibge_id'] : null;

        $cnae = null;
        if (! empty($est['atividade_principal']['id'])) {
            $cnae = preg_replace('/\D+/', '', (string) $est['atividade_principal']['id']);
        }

        $razao = (string) ($data['razao_social'] ?? '');
        $fantasia = (string) ($est['nome_fantasia'] ?? $razao);

        return [
            'cnpj' => $this->formatarCnpj($digitos),
            'nome' => $fantasia !== '' ? $fantasia : $razao,
            'razao_social' => $razao,
            'ie' => $ie !== '' ? $ie : null,
            'email' => filled($est['email'] ?? null) ? (string) $est['email'] : null,
            'telefone' => filled($est['telefone1'] ?? null) ? (string) $est['telefone1'] : null,
            'endereco' => $this->montarEndereco($logradouro, $numero, $bairro, $cidade, $uf, $cep),
            'cep' => $cep,
            'codigo_municipio_ibge' => $ibge,
            'cidade' => $cidade !== '' ? $cidade : null,
            'uf' => $uf !== '' ? $uf : null,
            'cnae' => $cnae && strlen($cnae) >= 7 ? substr($cnae, 0, 7) : null,
            'fonte' => 'publica.cnpj.ws',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function viaReceitaWs(string $digitos): array
    {
        $response = Http::timeout(20)
            ->acceptJson()
            ->withUserAgent('ParqueAquaticoSaaS/1.0')
            ->get('https://www.receitaws.com.br/v1/cnpj/'.$digitos);

        if (! $response->successful()) {
            throw new IntegrationException('CNPJ não encontrado (HTTP '.$response->status().').', 'cnpj');
        }

        $data = $response->json();
        if (! is_array($data) || ($data['status'] ?? '') === 'ERROR') {
            throw new IntegrationException((string) ($data['message'] ?? 'CNPJ não encontrado.'), 'cnpj');
        }

        $cep = $this->formatarCep((string) ($data['cep'] ?? ''));
        $cidade = (string) ($data['municipio'] ?? '');
        $uf = (string) ($data['uf'] ?? '');
        $cnae = null;
        if (! empty($data['atividade_principal'][0]['code'])) {
            $cnae = preg_replace('/\D+/', '', (string) $data['atividade_principal'][0]['code']);
        }

        $razao = (string) ($data['nome'] ?? '');
        $fantasia = (string) ($data['fantasia'] ?? $razao);

        return [
            'cnpj' => $this->formatarCnpj($digitos),
            'nome' => $fantasia !== '' ? $fantasia : $razao,
            'razao_social' => $razao,
            'ie' => null,
            'email' => filled($data['email'] ?? null) ? (string) $data['email'] : null,
            'telefone' => filled($data['telefone'] ?? null) ? (string) $data['telefone'] : null,
            'endereco' => $this->montarEndereco(
                (string) ($data['logradouro'] ?? ''),
                (string) ($data['numero'] ?? 'S/N'),
                (string) ($data['bairro'] ?? ''),
                $cidade,
                $uf,
                $cep
            ),
            'cep' => $cep,
            'codigo_municipio_ibge' => null,
            'cidade' => $cidade !== '' ? $cidade : null,
            'uf' => $uf !== '' ? $uf : null,
            'cnae' => $cnae && strlen($cnae) >= 7 ? substr($cnae, 0, 7) : null,
            'fonte' => 'receitaws',
        ];
    }

    protected function formatarCnpj(string $digitos): string
    {
        return substr($digitos, 0, 2).'.'.substr($digitos, 2, 3).'.'.substr($digitos, 5, 3).'/'
            .substr($digitos, 8, 4).'-'.substr($digitos, 12, 2);
    }

    protected function formatarCep(string $cep): ?string
    {
        $digits = preg_replace('/\D+/', '', $cep) ?: '';
        if (strlen($digits) < 8) {
            return null;
        }

        return substr($digits, 0, 5).'-'.substr($digits, 5, 3);
    }

    protected function montarEndereco(string $logradouro, string $numero, string $bairro, string $cidade, string $uf, ?string $cep): ?string
    {
        $partes = array_filter([
            trim($logradouro),
            $numero !== '' ? $numero : null,
            $bairro !== '' ? $bairro : null,
            ($cidade !== '' || $uf !== '') ? trim($cidade.($uf !== '' ? '/'.$uf : '')) : null,
            $cep,
        ]);

        return $partes === [] ? null : implode(', ', $partes);
    }
}
