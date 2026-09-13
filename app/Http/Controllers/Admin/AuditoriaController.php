<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Spatie\Activitylog\Models\Activity;

class AuditoriaController extends Controller
{
    /**
     * O log de auditoria (spatie/laravel-activitylog) não tem coluna
     * empresa_id própria — sem filtrar, um admin/gerente/financeiro (todos
     * têm auditoria.visualizar) veria o histórico de mudanças de TODAS as
     * empresas do SaaS. Restringe a "subject" (o registro alterado) sendo
     * de algum model que pertence à empresa do usuário logado — cobre
     * todos os models de negócio tenant-scoped que de fato aparecem no log.
     */
    protected const MODELOS_TENANT = [
        \App\Models\Cliente::class, \App\Models\Contrato::class, \App\Models\Mensalidade::class,
        \App\Models\Plano::class, \App\Models\Produto::class, \App\Models\Venda::class,
        \App\Models\Caixa::class, \App\Models\Comissao::class, \App\Models\TipoEntrada::class,
        \App\Models\Unidade::class, \App\Models\User::class, \App\Models\ContaPagar::class,
        \App\Models\ContaReceber::class, \App\Models\Pagamento::class,
        \App\Models\CaixaMovimentacao::class, \App\Models\TransferenciaCaixa::class,
    ];

    /**
     * CaixaMovimentacao não tem empresa_id própria (só chega à empresa via
     * caixa_id, ver CaixaMovimentacao::scopeDaEmpresa) — os demais models
     * desta lista têm a coluna direto, então usam o filtro genérico.
     */
    protected const MODELOS_VIA_RELACAO = [
        \App\Models\CaixaMovimentacao::class => 'caixa',
    ];

    public function index(): View
    {
        Gate::authorize('auditoria.visualizar');

        $empresaId = request()->user()->empresa_id;

        $atividades = Activity::with('causer')
            ->where(function ($query) use ($empresaId) {
                foreach (self::MODELOS_TENANT as $modelo) {
                    $relacao = self::MODELOS_VIA_RELACAO[$modelo] ?? null;

                    $query->orWhereHasMorph('subject', [$modelo], function ($q) use ($empresaId, $relacao) {
                        $relacao
                            ? $q->whereHas($relacao, fn ($qq) => $qq->where('empresa_id', $empresaId))
                            : $q->where('empresa_id', $empresaId);
                    });
                }
            })
            ->when(request('log_name'), fn ($q, $v) => $q->where('log_name', $v))
            ->when(request('causer_id'), fn ($q, $v) => $q->where('causer_id', $v))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('auditoria.index', compact('atividades'));
    }
}
