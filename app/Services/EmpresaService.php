<?php

namespace App\Services;

use App\Models\Empresa;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class EmpresaService
{
    public function atualizar(Empresa $empresa, array $dados, ?UploadedFile $logo): Empresa
    {
        $endereco = $dados['endereco'] ?? null;

        $camposIntegracao = [
            'mercadopago_access_token', 'mercadopago_public_key', 'mercadopago_webhook_secret',
            'evolution_base_url', 'evolution_api_key', 'evolution_instance',
        ];
        $integracoes = array_intersect_key($dados, array_flip($camposIntegracao));

        unset($dados['endereco'], $dados['logo']);
        foreach ($camposIntegracao as $campo) {
            unset($dados[$campo]);
        }

        if ($logo) {
            if ($empresa->logo_path) {
                Storage::disk('public')->delete($empresa->logo_path);
            }

            $dados['logo_path'] = $logo->store('logos', 'public');
        }

        $configuracoes = $empresa->configuracoes ?? [];
        $configuracoes['endereco'] = $endereco;
        $configuracoes['mercadopago'] = $this->mesclarSemApagar($configuracoes['mercadopago'] ?? [], [
            'access_token' => $integracoes['mercadopago_access_token'] ?? null,
            'public_key' => $integracoes['mercadopago_public_key'] ?? null,
            'webhook_secret' => $integracoes['mercadopago_webhook_secret'] ?? null,
        ]);
        $configuracoes['evolution'] = $this->mesclarSemApagar($configuracoes['evolution'] ?? [], [
            'base_url' => $integracoes['evolution_base_url'] ?? null,
            'api_key' => $integracoes['evolution_api_key'] ?? null,
            'instance' => $integracoes['evolution_instance'] ?? null,
        ]);

        $dados['configuracoes'] = $configuracoes;

        $empresa->update($dados);

        return $empresa;
    }

    /**
     * Campo enviado em branco no formulário = mantém o valor já salvo (não
     * apaga a credencial sem querer só porque o operador deixou o campo de
     * senha/token vazio ao salvar outra coisa na mesma tela).
     */
    protected function mesclarSemApagar(array $atual, array $novo): array
    {
        foreach ($novo as $chave => $valor) {
            if (filled($valor)) {
                $atual[$chave] = $valor;
            }
        }

        return $atual;
    }
}
