<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Carteirinha;
use App\Services\CarteirinhaService;
use App\Services\Relatorios\CarteirinhaPdfExport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CarteirinhaController extends Controller
{
    public function __construct(protected CarteirinhaService $carteirinhaService) {}

    public function index(): View
    {
        $this->authorize('viewAny', Carteirinha::class);

        $carteirinhas = Carteirinha::with(['cliente', 'dependente.cliente'])
            ->tap(fn ($q) => $this->escopoPorEmpresa($q, request()->user()->empresa_id))
            ->when(request('status'), fn ($q, $v) => $q->where('status', $v))
            ->when(request('codigo'), fn ($q, $v) => $q->where('codigo', 'like', "%{$v}%"))
            ->when(request('tipo') === 'titular', fn ($q) => $q->whereNotNull('cliente_id'))
            ->when(request('tipo') === 'dependente', fn ($q) => $q->whereNotNull('dependente_id'))
            ->when(request('busca'), function ($q, $v) {
                $cpfLimpo = preg_replace('/\D/', '', $v);

                $porNomeOuCpf = function ($query) use ($v, $cpfLimpo) {
                    $query->where('nome', 'like', "%{$v}%");

                    if ($cpfLimpo !== '') {
                        $query->orWhere('cpf', 'like', "%{$cpfLimpo}%");
                    }
                };

                $q->where(function ($qq) use ($porNomeOuCpf) {
                    $qq->whereHas('cliente', $porNomeOuCpf)
                        ->orWhereHas('dependente', $porNomeOuCpf);
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('carteirinhas.index', compact('carteirinhas'));
    }

    public function show(Carteirinha $carteirinha): View
    {
        $this->authorize('view', $carteirinha);

        $carteirinha->load(['cliente', 'dependente.cliente', 'acessos' => fn ($q) => $q->latest('registrado_em')->limit(30)]);

        return view('carteirinhas.show', compact('carteirinha'));
    }

    public function qrcode(Carteirinha $carteirinha): Response
    {
        $this->authorize('view', $carteirinha);

        $caminho = $this->carteirinhaService->gerarImagemQrCode($carteirinha);

        return response(\Illuminate\Support\Facades\Storage::disk('public')->get($caminho))
            ->header('Content-Type', 'image/png');
    }

    public function imprimir(Carteirinha $carteirinha, CarteirinhaPdfExport $export): Response
    {
        $this->authorize('view', $carteirinha);

        return $export->gerar(collect([$carteirinha]))->stream("carteirinha-{$carteirinha->codigo}.pdf");
    }

    public function imprimirLote(Request $request, CarteirinhaPdfExport $export): Response
    {
        $this->authorize('viewAny', Carteirinha::class);

        $ids = array_filter(explode(',', (string) $request->query('ids', '')));

        if (empty($ids)) {
            abort(404);
        }

        $carteirinhas = Carteirinha::whereIn('id', $ids)
            ->tap(fn ($q) => $this->escopoPorEmpresa($q, $request->user()->empresa_id))
            ->get();

        return $export->gerar($carteirinhas)->stream('carteirinhas-lote.pdf');
    }

    /**
     * Carteirinha não tem empresa_id próprio — restringe pela empresa do
     * titular (cliente direto ou cliente do dependente), evitando que um
     * usuário monte uma URL com ids de outra empresa.
     */
    protected function escopoPorEmpresa($query, ?int $empresaId): void
    {
        $query->where(function ($q) use ($empresaId) {
            $q->whereHas('cliente', fn ($qq) => $qq->where('empresa_id', $empresaId))
                ->orWhereHas('dependente.cliente', fn ($qq) => $qq->where('empresa_id', $empresaId));
        });
    }

    public function bloquear(Request $request, Carteirinha $carteirinha): RedirectResponse
    {
        $this->authorize('bloquear', $carteirinha);

        $dados = $request->validate(['motivo_bloqueio' => ['required', 'string', 'max:255']]);

        $this->carteirinhaService->bloquear($carteirinha, $dados['motivo_bloqueio']);

        return back()->with('sucesso', 'Carteirinha bloqueada.');
    }

    public function desbloquear(Carteirinha $carteirinha): RedirectResponse
    {
        $this->authorize('bloquear', $carteirinha);

        $this->carteirinhaService->desbloquear($carteirinha);

        return back()->with('sucesso', 'Carteirinha desbloqueada.');
    }
}
