<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClienteResource;
use App\Models\Cliente;
use App\Services\ClienteService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API consumida pelo app mobile da equipe do parque (recepção/vendedores),
 * não pelo cliente final. Endpoints de leitura + busca — o cadastro
 * completo continua no painel web por ora.
 */
class ClienteController extends Controller
{
    public function __construct(protected ClienteService $clienteService) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Cliente::class);

        $clientes = $this->clienteService->listar(request()->only(['nome', 'cpf', 'status']), porPagina: 20);

        return ClienteResource::collection($clientes);
    }

    public function show(Cliente $cliente): JsonResource
    {
        $this->authorize('view', $cliente);

        $cliente->load(['dependentes', 'contratoAtivo.plano', 'carteirinha']);

        return ClienteResource::make($cliente);
    }
}
