<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\NegocioException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hospedagem\CheckoutHospedagemRequest;
use App\Http\Requests\Hospedagem\StoreConsumoRequest;
use App\Http\Requests\Hospedagem\StoreHospedagemRequest;
use App\Models\Caixa;
use App\Models\Hospedagem;
use App\Models\Produto;
use App\Models\Quarto;
use App\Models\User;
use App\Services\CaixaService;
use App\Services\HospedagemService;
use App\Services\Relatorios\HospedagemPdfExport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

class HospedagemController extends Controller
{
    public function __construct(
        protected HospedagemService $hospedagemService,
        protected CaixaService $caixaService,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', Hospedagem::class);

        $hospedagens = Hospedagem::with(['quarto', 'cliente'])
            ->when(request('status'), fn ($q, $v) => $q->where('status', $v))
            ->latest('data_checkin_prevista')
            ->paginate(20)
            ->withQueryString();

        $resumo = $this->hospedagemService->resumo();

        return view('hospedagens.index', compact('hospedagens', 'resumo'));
    }

    public function relatorio(): View
    {
        $this->authorize('viewAny', Hospedagem::class);

        [$inicio, $fim] = $this->resolverPeriodoRelatorio();

        $relatorio = $this->hospedagemService->relatorioPeriodo($inicio, $fim);

        return view('hospedagens.relatorio', compact('relatorio', 'inicio', 'fim'));
    }

    public function relatorioPdf(HospedagemPdfExport $export): Response
    {
        $this->authorize('viewAny', Hospedagem::class);

        [$inicio, $fim] = $this->resolverPeriodoRelatorio();

        return $export->gerar($inicio, $fim)->download("relatorio-pousada-{$inicio->format('Y-m-d')}-a-{$fim->format('Y-m-d')}.pdf");
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function resolverPeriodoRelatorio(): array
    {
        $inicio = request('data_inicio') ? Carbon::parse(request('data_inicio'))->startOfDay() : now()->startOfMonth();
        $fim = request('data_fim') ? Carbon::parse(request('data_fim'))->endOfDay() : now()->endOfMonth();

        return [$inicio, $fim];
    }

    public function create(): View
    {
        $this->authorize('create', Hospedagem::class);

        $quartos = Quarto::ativos()->with('unidade')->orderBy('numero')->get();

        return view('hospedagens.create', compact('quartos'));
    }

    public function store(StoreHospedagemRequest $request): RedirectResponse
    {
        try {
            $hospedagem = $this->hospedagemService->reservar($request->validated(), $request->user());
        } catch (NegocioException $e) {
            return back()->withInput()->with('erro', $e->getMessage());
        }

        return redirect()->route('hospedagens.show', $hospedagem)->with('sucesso', 'Reserva criada com sucesso.');
    }

    public function show(Hospedagem $hospedagem): View
    {
        $this->authorize('view', $hospedagem);

        $hospedagem->load(['quarto', 'cliente', 'consumos.produto', 'consumos.registradoPor', 'registradoPor']);

        $produtos = Produto::ativos()->orderBy('nome')->get();

        return view('hospedagens.show', compact('hospedagem', 'produtos'));
    }

    public function checkin(Hospedagem $hospedagem): RedirectResponse
    {
        $this->authorize('checkin', $hospedagem);

        try {
            $this->hospedagemService->fazerCheckin($hospedagem);
        } catch (NegocioException $e) {
            return back()->with('erro', $e->getMessage());
        }

        return back()->with('sucesso', 'Check-in realizado com sucesso.');
    }

    public function consumos(StoreConsumoRequest $request, Hospedagem $hospedagem): RedirectResponse
    {
        try {
            $this->hospedagemService->adicionarConsumo($hospedagem, $request->validated(), $request->user());
        } catch (NegocioException $e) {
            return back()->with('erro', $e->getMessage());
        }

        return back()->with('sucesso', 'Consumo lançado com sucesso.');
    }

    public function checkoutForm(Hospedagem $hospedagem): View|RedirectResponse
    {
        $this->authorize('checkout', $hospedagem);

        if (! $hospedagem->estaHospedado()) {
            return redirect()->route('hospedagens.show', $hospedagem)->with('erro', 'Esta hospedagem não está com check-in em andamento.');
        }

        $hospedagem->load(['quarto', 'cliente', 'consumos']);

        [$caixaAberto, $caixasDisponiveis] = $this->resolverCaixaOperador(request()->user(), null);

        $noites = $this->hospedagemService->noites($hospedagem);
        $totalEstimado = $this->hospedagemService->calcularTotal($hospedagem);

        return view('hospedagens.checkout', compact('hospedagem', 'caixaAberto', 'caixasDisponiveis', 'noites', 'totalEstimado'));
    }

    public function checkout(CheckoutHospedagemRequest $request, Hospedagem $hospedagem): RedirectResponse
    {
        [$caixa] = $this->resolverCaixaOperador($request->user(), $request->input('caixa_id'));

        if (! $caixa) {
            return back()->with('erro', 'Abra o caixa antes de fazer o check-out.');
        }

        try {
            $this->hospedagemService->checkout(
                $hospedagem,
                $caixa,
                $request->user(),
                $request->validated()['forma_pagamento'],
                (float) ($request->validated()['desconto'] ?? 0),
            );
        } catch (NegocioException $e) {
            return back()->with('erro', $e->getMessage());
        }

        return redirect()->route('hospedagens.show', $hospedagem)->with('sucesso', 'Check-out realizado com sucesso.');
    }

    public function cancelar(Hospedagem $hospedagem): RedirectResponse
    {
        $this->authorize('cancelar', $hospedagem);

        try {
            $this->hospedagemService->cancelar($hospedagem);
        } catch (NegocioException $e) {
            return back()->with('erro', $e->getMessage());
        }

        return redirect()->route('hospedagens.index')->with('sucesso', 'Reserva cancelada.');
    }

    /**
     * Mesma lógica de VendaController::resolverCaixaOperador — operador com
     * unidade fixa usa os caixas abertos da própria unidade; sem unidade
     * fixa (admin/gerente "todas as unidades"), entre todos os da empresa.
     * Só pede pra escolher quando há mais de um aberto.
     *
     * @return array{0: ?Caixa, 1: Collection<int, Caixa>}
     */
    protected function resolverCaixaOperador(User $user, null|string|int $caixaIdEscolhido): array
    {
        $caixasDisponiveis = $user->unidade
            ? $this->caixaService->caixasAbertosDaUnidade($user->unidade_id)
            : $this->caixaService->caixasAbertosDaEmpresa($user->empresa_id);

        if ($caixasDisponiveis->count() <= 1) {
            return [$caixasDisponiveis->first(), $caixasDisponiveis];
        }

        $caixaEscolhido = $caixaIdEscolhido ? $caixasDisponiveis->firstWhere('id', (int) $caixaIdEscolhido) : null;

        return [$caixaEscolhido, $caixasDisponiveis];
    }
}
