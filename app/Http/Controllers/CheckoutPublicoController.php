<?php

namespace App\Http\Controllers;

use App\Exceptions\IntegrationException;
use App\Exceptions\NegocioException;
use App\Models\Acesso;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Pagamento;
use App\Models\TipoEntrada;
use App\Models\Unidade;
use App\Models\Venda;
use App\Services\AcessoService;
use App\Services\Integrations\MercadoPagoService;
use App\Services\Integrations\Pix\GatewayPix;
use App\Services\Integrations\Pix\GatewayPixResolver;
use App\Services\QrCodeService;
use App\Services\Relatorios\VoucherCheckinPdfExport;
use App\Services\Relatorios\VoucherEntradaPdfExport;
use App\Services\VendaService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Link público de autoatendimento: o próprio cliente escolhe o tipo de
 * entrada e a quantidade, paga via Pix no gateway configurado na empresa
 * (Mercado Pago ou Itaú) e, assim que o pagamento é confirmado, os
 * ingressos já valem e a venda cai no caixa do dia da unidade — sem nenhum
 * operador envolvido. Mercado Pago confirma pelo webhook
 * (App\Jobs\ProcessarWebhookMercadoPagoJob); o Itaú não tem webhook, então
 * a consulta de status da tela do pedido pergunta ao banco.
 */
class CheckoutPublicoController extends Controller
{
    public function __construct(
        protected VendaService $vendaService,
        protected AcessoService $acessoService,
        protected GatewayPixResolver $gatewayResolver,
        protected QrCodeService $qrCodeService,
    ) {}

    public function index(Unidade $unidade): View
    {
        $tiposEntrada = TipoEntrada::where('empresa_id', $unidade->empresa_id)->ativos()->orderBy('valor')->get();
        $pixDisponivel = $unidade->empresa->gatewayPixAtivo();

        return view('checkout.index', compact('unidade', 'tiposEntrada', 'pixDisponivel'));
    }

    public function store(Request $request, Unidade $unidade): RedirectResponse
    {
        $dados = $request->validate([
            'tipo_entrada_id' => ['required', 'integer'],
            'quantidade' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        $tipoEntrada = TipoEntrada::where('empresa_id', $unidade->empresa_id)->ativos()->findOrFail($dados['tipo_entrada_id']);

        $empresa = $unidade->empresa;
        $gateway = $this->gatewayResolver->paraEmpresa($empresa);

        if (! $gateway) {
            return back()->withInput()->with('erro', 'A compra online com Pix está indisponível no momento. Procure a recepção do parque.');
        }

        $venda = null;

        try {
            $venda = $this->vendaService->criarVendaOnlinePendente($unidade, $tipoEntrada, (int) $dados['quantidade']);
            $descricao = "{$venda->itens->first()->quantidade}x {$tipoEntrada->nome} - {$unidade->nome}";

            if ($gateway->nome() === Empresa::GATEWAY_PIX_MERCADOPAGO) {
                // external_reference "venda:{id}" é o que o webhook usa para confirmar.
                $host = parse_url(config('app.url'), PHP_URL_HOST) ?: 'example.com';
                $cobranca = MercadoPagoService::paraEmpresa($empresa)->criarCobrancaPix(
                    valor: (float) $venda->valor_total,
                    descricao: $descricao,
                    referenciaExterna: "venda:{$venda->id}",
                    emailPagador: "checkout-unidade{$unidade->id}@{$host}",
                );

                $this->vendaService->registrarPagamentoPixPendente($venda, isset($cobranca['id']) ? (string) $cobranca['id'] : null, $cobranca);
            } else {
                $cobranca = $gateway->criarCobranca((float) $venda->valor_total, $descricao);

                $this->vendaService->registrarPagamentoPixPendente($venda, $cobranca['txid'], [
                    'copia_e_cola' => $cobranca['copia_e_cola'],
                    'expira_em' => now()->addSeconds((int) $cobranca['expiracao_segundos'])->toIso8601String(),
                    'gateway' => $cobranca['payload'],
                ], $gateway->nome());
            }
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

        $pix = $this->dadosPix($venda->pagamentos->last());
        $statusUrl = URL::signedRoute('checkout.status', ['venda' => $venda->id]);
        $voucherUrl = URL::signedRoute('checkout.voucher', ['venda' => $venda->id]);

        return view('checkout.pedido', compact('venda', 'pix', 'statusUrl', 'voucherUrl'));
    }

    public function status(Venda $venda): JsonResponse
    {
        if ($venda->status === 'pendente') {
            $this->consultarGatewaySemWebhook($venda);
        }

        return response()->json(['status' => $venda->status]);
    }

    /**
     * Normaliza o QR do pagamento para a view: o Mercado Pago já devolve a
     * imagem; para os demais gateways a imagem é gerada do copia e cola.
     *
     * @return array{qr_code: string, qr_code_base64: string}|null
     */
    protected function dadosPix(?Pagamento $pagamento): ?array
    {
        if (! $pagamento) {
            return null;
        }

        if ($pagamento->gateway === Empresa::GATEWAY_PIX_MERCADOPAGO) {
            return $pagamento->payload['point_of_interaction']['transaction_data'] ?? null;
        }

        $copiaECola = (string) ($pagamento->payload['copia_e_cola'] ?? '');

        if ($copiaECola === '') {
            return null;
        }

        $dataUri = $this->qrCodeService->gerarDataUri($copiaECola, 260);

        return [
            'qr_code' => $copiaECola,
            'qr_code_base64' => substr($dataUri, strlen('data:image/png;base64,')),
        ];
    }

    /**
     * Gateways sem webhook (Itaú): confirma o Pix consultando o banco a cada
     * polling da tela do pedido, no máximo uma vez a cada 3 s por venda.
     */
    protected function consultarGatewaySemWebhook(Venda $venda): void
    {
        $pagamento = $venda->pagamentos()->latest('id')->first();

        if (! $pagamento || $pagamento->status !== 'pendente' || ! $pagamento->gateway_payment_id
            || $pagamento->gateway === Empresa::GATEWAY_PIX_MERCADOPAGO) {
            return;
        }

        if (! Cache::add("checkout-pix-consulta:{$venda->id}", true, 3)) {
            return;
        }

        $gateway = $this->gatewayResolver->porNome($venda->empresa, $pagamento->gateway);

        if (! $gateway) {
            return;
        }

        try {
            $consulta = $gateway->consultarCobranca($pagamento->gateway_payment_id);
        } catch (IntegrationException $e) {
            report($e);

            return;
        }

        if ($consulta['status'] === GatewayPix::STATUS_PAGA) {
            $this->vendaService->confirmarPagamentoOnline(
                $venda,
                $consulta['e2eid'] ?: $pagamento->gateway_payment_id,
                array_merge($pagamento->payload ?? [], ['gateway' => $consulta['payload']]),
            );
            $venda->refresh();

            return;
        }

        $expiraEm = $pagamento->payload['expira_em'] ?? null;
        $expirou = $expiraEm && Carbon::parse($expiraEm)->addMinute()->isPast();

        if ($consulta['status'] === GatewayPix::STATUS_CANCELADA || $expirou) {
            $venda->update(['status' => 'cancelado']);
            $pagamento->update(['status' => 'recusado']);
        }
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
