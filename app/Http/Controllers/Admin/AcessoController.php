<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Acesso;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Apenas a CONSULTA do histórico de acessos (log) para o painel admin.
 * A validação em tempo real (catraca/totem) fica em
 * App\Http\Controllers\Api\AcessoController, chamada pelo próprio
 * equipamento de acesso via API.
 */
class AcessoController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', \App\Models\Carteirinha::class);

        $acessos = Acesso::with(['carteirinha.cliente', 'carteirinha.dependente', 'cliente', 'unidade'])
            ->daEmpresa($request->user()->empresa_id)
            ->when(request('unidade_id'), fn ($q, $v) => $q->where('unidade_id', $v))
            ->when(request('autorizado') !== null && request('autorizado') !== '', fn ($q) => $q->where('autorizado', request()->boolean('autorizado')))
            ->latest('registrado_em')
            ->paginate(30)
            ->withQueryString();

        return view('acessos.index', compact('acessos'));
    }
}
