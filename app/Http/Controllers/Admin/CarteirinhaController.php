<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\IntegrationException;
use App\Http\Controllers\Controller;
use App\Models\Carteirinha;
use App\Services\CarteirinhaService;
use App\Services\Integrations\EvolutionApiService;
use App\Services\Relatorios\CarteirinhaPdfExport;
use App\Support\Telefone;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class CarteirinhaController extends Controller
{
    public function __construct(protected CarteirinhaService $carteirinhaService) {}

    public function index(): View
    {
        $this->authorize('viewAny', Carteirinha::class);

        $carteirinhas = Carteirinha::with(['cliente.carteirinha', 'cliente.dependentes.carteirinha', 'dependente.cliente.carteirinha', 'dependente.cliente.dependentes.carteirinha'])
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

                // Achou o titular: traz também as carteirinhas dos dependentes
                // dele, para imprimir a família toda de uma vez.
                $q->where(function ($qq) use ($porNomeOuCpf) {
                    $qq->whereHas('cliente', $porNomeOuCpf)
                        ->orWhereHas('dependente', $porNomeOuCpf)
                        ->orWhereHas('dependente.cliente', $porNomeOuCpf);
                });
            })
            // Na busca, agrupa por família: titular primeiro, dependentes logo abaixo.
            ->when(request('busca'), fn ($q) => $q
                ->orderByRaw('COALESCE(carteirinhas.cliente_id, (SELECT d.cliente_id FROM dependentes d WHERE d.id = carteirinhas.dependente_id))')
                ->orderByRaw('carteirinhas.cliente_id IS NULL')
                ->orderBy('carteirinhas.id'),
                fn ($q) => $q->latest())
            ->paginate(20)
            ->withQueryString();

        $whatsappIntegrado = app(EvolutionApiService::class)->configurada();

        return view('carteirinhas.index', compact('carteirinhas', 'whatsappIntegrado'));
    }

    public function show(Carteirinha $carteirinha): View
    {
        $this->authorize('view', $carteirinha);

        $carteirinha->load(['cliente.carteirinha', 'cliente.dependentes.carteirinha', 'dependente.cliente.carteirinha', 'dependente.cliente.dependentes.carteirinha', 'acessos' => fn ($q) => $q->latest('registrado_em')->limit(30)]);

        $whatsappIntegrado = app(EvolutionApiService::class)->configurada();

        return view('carteirinhas.show', compact('carteirinha', 'whatsappIntegrado'));
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
     * Envia a carteirinha (ou a da família inteira) pelo WhatsApp: com a
     * Evolution API configurada vai o PDF como arquivo; sem ela, abre o
     * WhatsApp com a mensagem e um link temporário (7 dias) para o PDF.
     */
    public function enviarWhatsapp(Request $request, Carteirinha $carteirinha, CarteirinhaPdfExport $export, EvolutionApiService $whatsapp): RedirectResponse
    {
        $this->authorize('view', $carteirinha);
        $dados = $request->validate([
            'telefone' => ['required', 'string', 'max:20'],
            'familia' => ['sometimes', 'boolean'],
        ]);

        $numero = Telefone::paraWhatsapp($dados['telefone']);
        if (! $numero) {
            return back()->with('erro', 'Informe o WhatsApp com DDD, ex.: (83) 99999-9999.');
        }

        $familia = $request->boolean('familia');
        $cartoes = $this->cartoesParaEnvio($carteirinha, $familia);
        $titular = $carteirinha->titular();
        $nome = (string) ($titular?->nome ?? 'cliente');
        $empresa = $titular instanceof \App\Models\Dependente ? $titular->cliente?->empresa : $titular?->empresa;
        $primeiroNome = ucfirst(mb_strtolower((string) strtok($nome, ' ')));

        $mensagem = "Olá, {$primeiroNome}! "
            .($cartoes->count() > 1 ? 'Seguem as carteirinhas da sua família' : 'Segue a sua carteirinha')
            .($empresa ? " — {$empresa->nome}" : '')
            .'. Apresente o QR Code na entrada.';
        $arquivo = ($cartoes->count() > 1 ? 'Carteirinhas - ' : 'Carteirinha - ').Str::of($nome)->ascii()->replaceMatches('/[^A-Za-z0-9 ]/', '')->squish().'.pdf';

        if ($whatsapp->configurada()) {
            try {
                $whatsapp->enviarDocumento($numero, base64_encode($export->gerar($cartoes)->output()), $arquivo, 'application/pdf', $mensagem);

                return back()->with('sucesso', ($cartoes->count() > 1 ? "{$cartoes->count()} carteirinhas enviadas" : 'Carteirinha enviada')." pelo WhatsApp para +{$numero}.");
            } catch (IntegrationException $e) {
                report($e);
                // Segue para o link, para não deixar sem envio.
            }
        }

        $link = URL::temporarySignedRoute('carteirinhas.compartilhada', now()->addDays(7), array_filter([
            'carteirinha' => $carteirinha->id,
            'familia' => $familia ? 1 : null,
        ]));

        return redirect()->away('https://wa.me/'.$numero.'?text='.rawurlencode($mensagem."\n\nBaixe aqui: ".$link));
    }

    /** PDF aberto pelo link do WhatsApp (assinado, sem login, 7 dias). */
    public function compartilhada(Request $request, int $carteirinha, CarteirinhaPdfExport $export): Response
    {
        abort_unless($request->hasValidSignature(), 403);

        $carteirinha = Carteirinha::withoutGlobalScopes()->findOrFail($carteirinha);

        return $export->gerar($this->cartoesParaEnvio($carteirinha, $request->boolean('familia')))
            ->stream("carteirinha-{$carteirinha->codigo}.pdf");
    }

    /** A carteirinha, ou a do titular + as dos dependentes dele. */
    protected function cartoesParaEnvio(Carteirinha $carteirinha, bool $familia): \Illuminate\Support\Collection
    {
        if (! $familia) {
            return collect([$carteirinha]);
        }

        $cliente = $carteirinha->cliente ?? $carteirinha->dependente?->cliente;
        if (! $cliente) {
            return collect([$carteirinha]);
        }

        $cliente->loadMissing('carteirinha', 'dependentes.carteirinha');

        return collect([$cliente->carteirinha])
            ->merge($cliente->dependentes->pluck('carteirinha'))
            ->filter()
            ->unique('id')
            ->values();
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
