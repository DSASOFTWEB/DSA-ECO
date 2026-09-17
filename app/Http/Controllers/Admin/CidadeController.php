<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\IntegrationException;
use App\Http\Controllers\Controller;
use App\Models\Cidade;
use App\Services\IbgeCidadeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CidadeController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('empresa.gerenciar');

        $nome = trim((string) $request->query('nome', ''));
        $uf = strtoupper(trim((string) $request->query('uf', '')));

        $cidades = Cidade::query()
            ->when($nome !== '', fn ($q) => $q->where('nome', 'like', '%'.$nome.'%'))
            ->when($uf !== '' && in_array($uf, Cidade::estados(), true), fn ($q) => $q->where('uf', $uf))
            ->orderBy('nome')
            ->paginate(50)
            ->withQueryString();

        return view('cidades.index', [
            'cidades' => $cidades,
            'total' => Cidade::count(),
            'estados' => Cidade::estados(),
            'filtroNome' => $nome,
            'filtroUf' => $uf,
        ]);
    }

    public function sincronizar(Request $request, IbgeCidadeService $service): RedirectResponse
    {
        Gate::authorize('empresa.gerenciar');

        $uf = strtoupper(trim((string) $request->input('uf', '')));
        $uf = $uf !== '' ? $uf : null;

        try {
            $resultado = $service->sincronizarApi($uf);
            $msg = sprintf(
                'Cidades atualizadas via IBGE. API: %s | Inseridas: %s | Atualizadas: %s',
                number_format($resultado['total_api'], 0, ',', '.'),
                number_format($resultado['inseridos'], 0, ',', '.'),
                number_format($resultado['atualizados'], 0, ',', '.')
            );

            return redirect()->route('cidades.index')->with('sucesso', $msg);
        } catch (IntegrationException $e) {
            return redirect()->route('cidades.index')->with('erro', $e->getMessage());
        }
    }

    public function autocomplete(Request $request): JsonResponse
    {
        Gate::authorize('empresa.gerenciar');

        $busca = trim((string) $request->query('busca', ''));
        if (mb_strlen($busca) < 2) {
            return response()->json([]);
        }

        $digits = preg_replace('/\D/', '', $busca) ?: '';
        $uf = strtoupper(trim((string) $request->query('uf', '')));

        $query = Cidade::query()
            ->orderBy('nome')
            ->limit(25);

        if ($uf !== '' && in_array($uf, Cidade::estados(), true)) {
            $query->where('uf', $uf);
        }

        $query->where(function ($q) use ($busca, $digits) {
            if ($digits !== '') {
                $q->where('codigo', 'like', $digits.'%');
            }
            $q->orWhere('nome', 'like', '%'.$busca.'%');
        });

        return response()->json(
            $query->get(['codigo', 'nome', 'uf'])->map(fn (Cidade $c) => [
                'codigo' => $c->codigo,
                'nome' => $c->nome,
                'uf' => $c->uf,
                'text' => $c->codigo.' — '.$c->nome.'/'.$c->uf,
            ])->values()
        );
    }
}
