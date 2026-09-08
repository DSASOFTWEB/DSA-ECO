<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AcessoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint chamado pelo equipamento físico de controle de acesso
 * (catraca/totem/leitor QR) após ler o código da carteirinha. Autenticado
 * por um usuário de serviço (token Sanctum com a permissão
 * "acessos.validar" — ver CarteirinhaPolicy::validarAcesso).
 */
class AcessoController extends Controller
{
    public function __construct(protected AcessoService $acessoService) {}

    public function validar(Request $request): JsonResponse
    {
        $this->authorize('validarAcesso', \App\Models\Carteirinha::class);

        $dados = $request->validate([
            'codigo' => ['required', 'string'],
            'unidade_id' => ['required', 'exists:unidades,id'],
            'tipo' => ['nullable', 'in:entrada,saida'],
            'dispositivo' => ['nullable', 'string', 'max:255'],
        ]);

        $resultado = $this->acessoService->validarEEntrar(
            codigo: $dados['codigo'],
            unidadeId: $dados['unidade_id'],
            tipo: $dados['tipo'] ?? 'entrada',
            dispositivo: $dados['dispositivo'] ?? null,
        );

        return response()->json([
            'autorizado' => $resultado['autorizado'],
            'motivo' => $resultado['motivo'],
            'titular' => $resultado['titular']?->only(['id', 'nome']),
        ], $resultado['autorizado'] ? 200 : 403);
    }
}
