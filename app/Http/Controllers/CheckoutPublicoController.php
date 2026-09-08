<?php

namespace App\Http\Controllers;

use App\Exceptions\IntegrationException;
use App\Exceptions\NegocioException;
use App\Models\Acesso;
use App\Models\Cliente;
use App\Models\TipoEntrada;
use App\Models\Unidade;
use App\Models\Venda;
use App\Services\AcessoService;
use App\Services\Integrations\MercadoPagoService;
use App\Services\Relatorios\VoucherCheckinPdfExport;
use App\Services\Relatorios\VoucherEntradaPdfExport;
use App\Services\VendaService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Link público de autoatendimento: o próprio cliente escolhe o tipo de
 * entrada e a quantidade, paga via Pix (Mercado Pago) e, assim que o
 * pagamento é confirmado pelo webhook, os ingressos já valem e a venda cai
 * no caixa do dia da unidade — sem nenhum operador envolvido. Ver
 * App\Services\VendaService (criarVendaOnlinePendente/confirmarPagamentoOnline)
 * e App\Jobs\ProcessarWebhookMercadoPagoJob.
 */
class CheckoutPublicoController extends Controller
{
    public function __construct(
        protected VendaService $vendaService,
        protected AcessoService $acessoService,
    ) {}

    public function index(Unidade $unidade): View
    {
        $tiposEntrada = TipoEntrada::where('empresa_id', $unidade->empresa_id)->ativos()->orderBy('valor')->get();

        return view('checkout.index', compact('unidade', 'tiposEntrada'));
    }

    public function store(Request $request, Unidade $unidade): RedirectResponse
    {
        $dados = $request->validate([
            'tipo_entrada_id' => ['required', 'integer'],
            'quantidade' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        $tipoEntrada = TipoEntrada::where('empresa_id', $unidade->empresa_id)->ativos()->findOrFail($dados['tipo_entrada_id']);

        $venda = null;

        try {
            $venda = $this->vendaService->criarVendaOnlinePendente($unidade, $tipoEntrada, (int) $dados['quantidade']);

            $host = parse_url(config('app.url'), PHP_URL_HOST) ?: 'example.com';
            // Credencial da PRÓPRIA empresa da unidade quando configurada
            // (cada parque recebe na sua conta) — cai pro .env da
            // plataforma se a empresa não tiver configurado a sua.
            $mercadoPago = MercadoPagoService::paraEmpresa($unidade->empresa);
            $cobranca = $mercadoPago->criarCobrancaPix(
                valor: (float) $venda->valor_total,
                descricao: "{$venda->itens->first()->quantidade}x {$tipoEntrada->nome} - {$unidade->nome}",
                referenciaExterna: "venda:{$venda->id}",
                emailPagador: "checkout-unidade{$unidade->id}@{$host}",
            );

            $this->vendaService->registrarPagamentoPixPendente($venda, isset($cobranca['id']) ? (string) $cobranca['id'] : null, $cobranca);
        } catch (NegocioException $e) {
            $venda?->update(['status' => 'cancelado']);

            return back()->withInput()->with('erro', $e->getMessage());
        } catch (IntegrationException $e) {
            report($e);
            // A cobrança Pix não foi gerada — não faz sentido deixar este
            // pedido pendente parado para sempre na lista de vendas.
            $venda?->update(['status' => 'cancelado']);

            return back()->withInput()->with('erro', 'Não foi possível gerar o Pix agora. Tente novamente em instantes.');
        }

        return redirect(URL::signedRoute('checkout.pedido', ['venda' => $venda->id]));
    }

    public function pedido(Venda $venda): View
    {
        $venda->load(['unidade', 'itens.tipoEntrada', 'pagamentos']);

        $pagamento = $venda->pagamentos->last();
        $pix = $pagamento?->payload['point_of_interaction']['transaction_data'] ?? null;
        $statusUrl = URL::signedRoute('checkout.status', ['venda' => $venda->id]);
        $voucherUrl = URL::signedRoute('checkout.voucher', ['venda' => $venda->id]);

        return view('checkout.pedido', compact('venda', 'pix', 'statusUrl', 'voucherUrl'));
    }

    public function status(Venda $venda): JsonResponse
    {
        return response()->json(['status' => $venda->status]);
    }

    public function voucher(Venda $venda, VoucherEntradaPdfExport $export): Response
    {
        if ($venda->status !== 'pago') {
            abort(404);
        }

        return $export->gerar($venda)->stream("voucher-venda-{$venda->id}.pdf");
    }

    /**
     * Opção "cliente com plano" do link público: em vez de cobrar Pix, pede
     * CPF ou nome, confere se o plano está ativo e já registra a entrada
     * (dele e da família) — igual ao check-in feito na recepção, só que
     * disparado pelo próprio cliente. Busca é sempre exata (nunca lista
     * candidatos parecidos pra um visitante anônimo).
     */
    public function verificarPlano(Request $request, Unidade $unidade): RedirectResponse
    {
        $dados = $request->validate(['termo' => ['required', 'string', 'min:3', 'max:255']]);

        $cliente = $this->acessoService->buscarClienteExatoParaCheckinPublico($dados['termo'], $unidade->empresa_id);

        if (! $cliente) {
            return back()->withInput()->with('erro', 'Cliente não encontrado. Digite o CPF completo (só números) ou o nome completo, exatamente como está cadastrado.');
        }

        $resultado = $this->acessoService->registrarEntradaPlano($cliente, $unidade->id, null, 'checkin_online');
        $acessosIds = $resultado['acessos']->pluck('id')->implode(',');

        return redirect(URL::signedRoute('checkout.plano-resultado', [
            'unidade' => $unidade->id,
            'cliente' => $cliente->id,
            'acessos' => $acessosIds,
        ]));
    }

    public function planoResultado(Request $request, Unidade $unidade, Cliente $cliente): View
    {
        // Defesa em profundidade: a URL assinada já garante que ninguém
        // forjou essa combinação de unidade/cliente/acessos, mas como esta
        // rota é pública (sem usuário autenticado, o TenantScope de Cliente
        // não entra em ação sozinho) confere explicitamente mesmo assim.
        abort_unless($cliente->empresa_id === $unidade->empresa_id, 404);

        $ids = array_filter(explode(',', (string) $request->query('acessos', '')));
        $acessos = Acesso::with(['cliente', 'dependente', 'unidade'])->daEmpresa($unidade->empresa_id)->whereIn('id', $ids)->get();
        $situacao = $this->acessoService->situacaoPlano($cliente);
        $voucherUrl = URL::signedRoute('checkout.plano-voucher', [
            'unidade' => $unidade->id,
            'cliente' => $cliente->id,
            'acessos' => $request->query('acessos', ''),
        ]);

        return view('checkout.plano-resultado', compact('unidade', 'cliente', 'acessos', 'situacao', 'voucherUrl'));
    }

    public function planoVoucher(Request $request, Unidade $unidade, Cliente $cliente, VoucherCheckinPdfExport $export): Response
    {
        abort_unless($cliente->empresa_id === $unidade->empresa_id, 404);

        $ids = array_filter(explode(',', (string) $request->query('acessos', '')));
        $acessos = Acesso::with(['cliente', 'dependente', 'unidade'])->daEmpresa($unidade->empresa_id)->whereIn('id', $ids)->where('autorizado', true)->get();

        if ($acessos->isEmpty()) {
            abort(404);
        }

        return $export->gerar($acessos)->stream("voucher-checkin-{$cliente->id}.pdf");
    }
}
