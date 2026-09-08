<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\NegocioException;
use App\Http\Controllers\Controller;
use App\Models\Acesso;
use App\Models\Cliente;
use App\Models\TipoEntrada;
use App\Services\AcessoService;
use App\Services\Relatorios\VoucherCortesiaPdfExport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Entradas de cortesia (presente/promocional): a recepção emite N entradas
 * sem gerar venda nem receita. Cada uma sai com QR de validação — a pessoa
 * costuma usar em outro dia, então é validada na portaria como qualquer
 * voucher (ver ValidacaoVoucherController).
 */
class CortesiaController extends Controller
{
    public function __construct(protected AcessoService $acessoService) {}

    public function index(Request $request): View
    {
        Gate::authorize('acessos.cortesia');

        $tiposEntrada = TipoEntrada::ativos()->orderBy('nome')->get();
        $contadorMes = $this->acessoService->contarCortesias($request->user()->empresa_id, now()->startOfMonth());
        $contadorTotal = $this->acessoService->contarCortesias($request->user()->empresa_id);

        $status = $request->query('status');
        $cortesias = Acesso::with(['cliente', 'tipoEntrada', 'unidade'])
            ->daEmpresa($request->user()->empresa_id)
            ->where('origem', 'cortesia')
            ->when($status === 'pendente', fn ($q) => $q->whereNull('validado_em'))
            ->when($status === 'validada', fn ($q) => $q->whereNotNull('validado_em'))
            ->latest('registrado_em')
            ->paginate(20)
            ->withQueryString();

        return view('cortesias.index', compact('tiposEntrada', 'contadorMes', 'contadorTotal', 'cortesias'));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('acessos.cortesia');

        $dados = $request->validate([
            'tipo_entrada_id' => ['nullable', 'exists:tipos_entrada,id'],
            'cliente_id' => ['nullable', 'exists:clientes,id'],
            'quantidade' => ['required', 'integer', 'min:1', 'max:50'],
            'observacao' => ['nullable', 'string', 'max:255'],
            'validade_dias' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $unidadeId = $request->user()->unidade_id;

        if (! $unidadeId) {
            return back()->withInput()->with('erro', 'Seu usuário não está vinculado a uma unidade. Não é possível emitir a cortesia.');
        }

        try {
            $acessos = $this->acessoService->gerarCortesias(
                unidadeId: $unidadeId,
                quantidade: (int) $dados['quantidade'],
                operador: $request->user(),
                tipoEntrada: isset($dados['tipo_entrada_id']) ? TipoEntrada::find($dados['tipo_entrada_id']) : null,
                cliente: isset($dados['cliente_id']) ? Cliente::find($dados['cliente_id']) : null,
                observacao: $dados['observacao'] ?? null,
                validadeDias: isset($dados['validade_dias']) ? (int) $dados['validade_dias'] : null,
            );
        } catch (NegocioException $e) {
            return back()->withInput()->with('erro', $e->getMessage());
        }

        return redirect()->route('cortesias.resultado', ['acessos' => $acessos->pluck('id')->implode(',')])
            ->with('sucesso', "{$acessos->count()} entrada(s) de cortesia gerada(s) com sucesso.");
    }

    public function resultado(Request $request): View
    {
        Gate::authorize('acessos.cortesia');

        $ids = array_filter(explode(',', (string) $request->query('acessos', '')));
        $acessos = Acesso::with(['cliente', 'tipoEntrada', 'unidade'])
            ->daEmpresa($request->user()->empresa_id)
            ->where('origem', 'cortesia')->whereIn('id', $ids)->get();

        return view('cortesias.resultado', compact('acessos'));
    }

    public function voucher(Request $request, VoucherCortesiaPdfExport $export): Response
    {
        Gate::authorize('acessos.cortesia');

        $ids = array_filter(explode(',', (string) $request->query('acessos', '')));
        $acessos = Acesso::with(['cliente', 'tipoEntrada', 'unidade.empresa'])
            ->daEmpresa($request->user()->empresa_id)
            ->where('origem', 'cortesia')->whereIn('id', $ids)->get();

        if ($acessos->isEmpty()) {
            abort(404);
        }

        return $export->gerar($acessos)->stream('voucher-cortesia.pdf');
    }
}
