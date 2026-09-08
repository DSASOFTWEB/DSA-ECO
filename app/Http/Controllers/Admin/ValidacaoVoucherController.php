<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AcessoService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Validação, na portaria, de vouchers gerados pelo link público (venda
 * avulsa online ou check-in online) — ver App\Models\Acesso::ORIGENS_QUE_EXIGEM_VALIDACAO
 * e App\Services\AcessoService::validarVoucher(). Cada código só passa uma
 * vez; a segunda tentativa com o mesmo QR/print é sinalizada como fraude.
 */
class ValidacaoVoucherController extends Controller
{
    public function __construct(protected AcessoService $acessoService) {}

    public function index(): View
    {
        Gate::authorize('checkin.realizar');

        return view('validacao-voucher.index');
    }

    public function validar(Request $request): JsonResponse
    {
        Gate::authorize('checkin.realizar');

        $dados = $request->validate(['codigo' => ['required', 'string', 'max:64']]);

        $resultado = $this->acessoService->validarVoucher($dados['codigo'], $request->user());

        return response()->json([
            'status' => $resultado['status'],
            'mensagem' => $resultado['mensagem'],
            'nome' => $resultado['acesso']?->nomeTitular(),
            'origem' => $resultado['acesso']?->origem,
            'registrado_em' => $resultado['acesso']?->registrado_em?->format('d/m/Y H:i'),
        ]);
    }

    /**
     * Mesma tela de validação, em layout enxuto (sem menu administrativo) —
     * é a URL que vira o "App Validador" instalável (ver layouts.validador).
     */
    public function app(): View
    {
        Gate::authorize('checkin.realizar');

        return view('validacao-voucher.app');
    }

    /**
     * Manifest do PWA, gerado por empresa (nome no app reflete o tenant
     * logado). Fica atrás do login de propósito: a instalação é sempre
     * feita a partir de uma sessão autenticada, então dá pra usar os
     * mesmos dados do usuário sem expor nada publicamente.
     */
    public function manifest(Request $request): JsonResponse
    {
        Gate::authorize('checkin.realizar');

        $empresa = $request->user()->empresa;

        return response()->json([
            'name' => 'Validador — '.($empresa->nome ?? config('app.name')),
            'short_name' => 'Validador',
            'description' => 'Validação de vouchers e cortesias na portaria.',
            'start_url' => route('app-validador.index'),
            'scope' => route('app-validador.index'),
            'display' => 'standalone',
            'orientation' => 'portrait',
            'background_color' => '#0f172a',
            'theme_color' => '#0284c7',
            'icons' => [
                ['src' => asset('pwa/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => asset('pwa/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => asset('pwa/icon-maskable-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'maskable'],
                ['src' => asset('pwa/icon-maskable-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json']);
    }
}
