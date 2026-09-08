<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Comissao;
use App\Services\ComissaoService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class ComissaoController extends Controller
{
    public function __construct(protected ComissaoService $comissaoService) {}

    public function index(): View
    {
        $this->authorize('viewAny', Comissao::class);

        $user = Auth::user();

        $comissoes = Comissao::with(['vendedor', 'venda', 'contrato.cliente'])
            ->when(! $user->can('comissoes.visualizar'), fn ($q) => $q->where('vendedor_id', $user->id))
            ->when(request('status'), fn ($q, $v) => $q->where('status', $v))
            ->when(request('vendedor_id'), fn ($q, $v) => $q->where('vendedor_id', $v))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('comissoes.index', compact('comissoes'));
    }

    public function pagar(Comissao $comissao): RedirectResponse
    {
        $this->authorize('pagar', $comissao);

        $this->comissaoService->marcarComoPaga($comissao);

        return back()->with('sucesso', 'Comissão marcada como paga.');
    }
}
