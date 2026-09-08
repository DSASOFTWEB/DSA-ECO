<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Unidade\StoreUnidadeRequest;
use App\Models\Unidade;
use App\Services\QrCodeService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class UnidadeController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Unidade::class);

        $unidades = Unidade::withCount(['clientes', 'contratos'])->orderBy('nome')->paginate(15);

        return view('unidades.index', compact('unidades'));
    }

    /**
     * Página de acesso rápido ao link de venda online (checkout público) de
     * cada unidade — mesmo link já disponível na tela de Unidades, só que
     * num atalho próprio no menu, com QR pra imprimir/compartilhar.
     * Sem permissão dedicada de propósito: é o mesmo link que já é feito
     * pra ser repassado a qualquer cliente, não é informação sensível.
     */
    public function linkExterno(QrCodeService $qrCodeService): View
    {
        $unidades = Unidade::ativas()->orderBy('nome')->get();

        $links = $unidades->mapWithKeys(function (Unidade $unidade) use ($qrCodeService) {
            $url = route('checkout.index', $unidade);

            return [$unidade->id => ['url' => $url, 'qr' => $qrCodeService->gerarDataUri($url, 180)]];
        });

        return view('unidades.link-externo', compact('unidades', 'links'));
    }

    public function create(): View
    {
        $this->authorize('create', Unidade::class);

        return view('unidades.create');
    }

    public function store(StoreUnidadeRequest $request): RedirectResponse
    {
        $unidade = Unidade::create($request->validated());

        return redirect()->route('unidades.index')->with('sucesso', "Unidade \"{$unidade->nome}\" criada com sucesso.");
    }

    public function edit(Unidade $unidade): View
    {
        $this->authorize('update', $unidade);

        return view('unidades.edit', compact('unidade'));
    }

    public function update(StoreUnidadeRequest $request, Unidade $unidade): RedirectResponse
    {
        $unidade->update($request->validated());

        return redirect()->route('unidades.index')->with('sucesso', 'Unidade atualizada com sucesso.');
    }
}
