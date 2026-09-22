<?php

use App\Http\Controllers\Admin\AcessoController;
use App\Http\Controllers\Admin\AuditoriaController;
use App\Http\Controllers\Admin\CaixaController;
use App\Http\Controllers\Admin\CarteirinhaController;
use App\Http\Controllers\Admin\CheckinController;
use App\Http\Controllers\Admin\CidadeController;
use App\Http\Controllers\Admin\ClienteController;
use App\Http\Controllers\Admin\ComissaoController;
use App\Http\Controllers\Admin\ContaPagarController;
use App\Http\Controllers\Admin\ContaReceberController;
use App\Http\Controllers\Admin\ContratoController;
use App\Http\Controllers\Admin\CortesiaController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DependenteController;
use App\Http\Controllers\Admin\EmpresaController;
use App\Http\Controllers\Admin\FinanceiroController;
use App\Http\Controllers\Admin\FoodController;
use App\Http\Controllers\Admin\HospedagemController;
use App\Http\Controllers\Admin\MensalidadeController;
use App\Http\Controllers\Admin\MovimentacaoController;
use App\Http\Controllers\Admin\PlanoController;
use App\Http\Controllers\Admin\ProdutoController;
use App\Http\Controllers\Admin\QuartoController;
use App\Http\Controllers\Admin\RelatorioController;
use App\Http\Controllers\Admin\RelatorioMovimentacaoController;
use App\Http\Controllers\Admin\TerminalController;
use App\Http\Controllers\Admin\TipoEntradaController;
use App\Http\Controllers\Admin\TransferenciaCaixaController;
use App\Http\Controllers\Admin\UnidadeController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\ValidacaoVoucherController;
use App\Http\Controllers\Admin\VendaController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\CadastroEmpresaController;
use App\Http\Controllers\CheckoutPublicoController;
use App\Http\Controllers\PaginaInicialController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas públicas (landing, login, autocadastro de nova empresa)
|--------------------------------------------------------------------------
| SaaS multi-tenant: qualquer novo parque pode criar a própria empresa
| (tenant) sem depender de um super_admin cadastrar manualmente — ver
| CadastroEmpresaController.
*/
Route::middleware('guest')->group(function () {
    Route::get('/', PaginaInicialController::class)->name('home');

    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:login')->name('login.attempt');

    Route::get('/cadastro', [CadastroEmpresaController::class, 'create'])->name('cadastro.create');
    Route::get('/cadastro/consultar-cnpj', [CadastroEmpresaController::class, 'consultarCnpj'])->middleware('throttle:20,1')->name('cadastro.consultar-cnpj');
    Route::post('/cadastro', [CadastroEmpresaController::class, 'store'])->middleware('throttle:cadastro-empresa')->name('cadastro.store');

    Route::get('/esqueci-senha', [PasswordResetController::class, 'showLinkRequestForm'])->name('password.request');
    Route::post('/esqueci-senha', [PasswordResetController::class, 'sendResetLinkEmail'])->middleware('throttle:password-reset')->name('password.email');
    Route::get('/redefinir-senha/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
    Route::post('/redefinir-senha', [PasswordResetController::class, 'reset'])->middleware('throttle:password-reset')->name('password.update');
});

Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Checkout público (link de autoatendimento, sem login) — cliente escolhe
| tipo de entrada + quantidade e paga via Pix. Throttle porque cada acesso
| chama a API do Mercado Pago (custo/limite de taxa do lado do gateway).
|--------------------------------------------------------------------------
*/
Route::middleware('throttle:20,1')->group(function () {
    Route::get('comprar/{unidade}', [CheckoutPublicoController::class, 'index'])->name('checkout.index');
    Route::post('comprar/{unidade}', [CheckoutPublicoController::class, 'store'])->name('checkout.store');

    // Assinadas: sem login, mas o id sozinho na URL não basta — evita que
    // qualquer um ande pelos ids e veja/baixe pedido, Pix ou voucher de
    // outro cliente (a assinatura cobre a URL inteira, inclusive querystring).
    Route::middleware('signed')->group(function () {
        Route::get('comprar/pedido/{venda}', [CheckoutPublicoController::class, 'pedido'])->name('checkout.pedido');
        Route::get('comprar/pedido/{venda}/status', [CheckoutPublicoController::class, 'status'])->name('checkout.status');
        Route::get('comprar/pedido/{venda}/voucher', [CheckoutPublicoController::class, 'voucher'])->name('checkout.voucher');
        Route::get('comprar/{unidade}/plano/{cliente}', [CheckoutPublicoController::class, 'planoResultado'])->name('checkout.plano-resultado');
        Route::get('comprar/{unidade}/plano/{cliente}/voucher', [CheckoutPublicoController::class, 'planoVoucher'])->name('checkout.plano-voucher');
    });

    Route::post('comprar/{unidade}/verificar-plano', [CheckoutPublicoController::class, 'verificarPlano'])->name('checkout.verificar-plano');
});

/*
|--------------------------------------------------------------------------
| Painel administrativo (autenticado)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('clientes/buscar', [ClienteController::class, 'buscar'])->name('clientes.buscar');
    Route::resource('clientes', ClienteController::class);
    Route::post('clientes/{cliente}/dependentes', [DependenteController::class, 'store'])->name('clientes.dependentes.store');
    Route::delete('clientes/{cliente}/dependentes/{dependente}', [DependenteController::class, 'destroy'])->name('clientes.dependentes.destroy');

    Route::resource('planos', PlanoController::class)->except(['show']);

    Route::resource('contratos', ContratoController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update']);
    Route::get('contratos/{contrato}/pdf', [ContratoController::class, 'pdf'])->name('contratos.pdf');
    Route::get('contratos/{contrato}/prorrogar', [ContratoController::class, 'prorrogarForm'])->name('contratos.prorrogar-form');
    Route::patch('contratos/{contrato}/prorrogar', [ContratoController::class, 'prorrogar'])->name('contratos.prorrogar');
    Route::post('contratos/{contrato}/cancelar', [ContratoController::class, 'cancelar'])->name('contratos.cancelar');
    Route::post('contratos/{contrato}/reativar', [ContratoController::class, 'reativar'])->name('contratos.reativar');

    Route::resource('mensalidades', MensalidadeController::class)->only(['index', 'show']);
    Route::post('mensalidades/{mensalidade}/baixar', [MensalidadeController::class, 'baixarManual'])->name('mensalidades.baixar');
    Route::post('mensalidades/{mensalidade}/gerar-pix', [MensalidadeController::class, 'gerarPix'])->name('mensalidades.gerar-pix');
    Route::post('mensalidades/{mensalidade}/cobrar-whatsapp', [MensalidadeController::class, 'cobrarWhatsapp'])->name('mensalidades.cobrar-whatsapp');
    Route::patch('mensalidades/{mensalidade}/vencimento', [MensalidadeController::class, 'alterarVencimento'])->name('mensalidades.alterar-vencimento');
    Route::post('mensalidades/cobrar-whatsapp-lote', [MensalidadeController::class, 'cobrarWhatsappLote'])->name('mensalidades.cobrar-whatsapp-lote');
    Route::post('mensalidades/alterar-vencimento-lote', [MensalidadeController::class, 'alterarVencimentoLote'])->name('mensalidades.alterar-vencimento-lote');

    Route::get('financeiro', [FinanceiroController::class, 'index'])->name('financeiro.index');
    Route::post('financeiro/contas-pagar', [ContaPagarController::class, 'store'])->name('contas-pagar.store');
    Route::put('financeiro/contas-pagar/{contaPagar}', [ContaPagarController::class, 'update'])->name('contas-pagar.update');
    Route::post('financeiro/contas-pagar/{contaPagar}/pagar', [ContaPagarController::class, 'pagar'])->name('contas-pagar.pagar');
    Route::post('financeiro/contas-pagar/{contaPagar}/estornar', [ContaPagarController::class, 'estornar'])->name('contas-pagar.estornar');
    Route::delete('financeiro/contas-pagar/{contaPagar}', [ContaPagarController::class, 'destroy'])->name('contas-pagar.destroy');

    Route::post('financeiro/contas-receber', [ContaReceberController::class, 'store'])->name('contas-receber.store');
    Route::put('financeiro/contas-receber/{contaReceber}', [ContaReceberController::class, 'update'])->name('contas-receber.update');
    Route::post('financeiro/contas-receber/{contaReceber}/receber', [ContaReceberController::class, 'receber'])->name('contas-receber.receber');
    Route::post('financeiro/contas-receber/{contaReceber}/estornar', [ContaReceberController::class, 'estornar'])->name('contas-receber.estornar');
    Route::delete('financeiro/contas-receber/{contaReceber}', [ContaReceberController::class, 'destroy'])->name('contas-receber.destroy');

    // "parameters" explícito: o singular automático de "terminais" dá
    // "terminai" (inflector do Laravel não conhece plural em português),
    // o que faria o model binding de {terminal} injetar silenciosamente um
    // Terminal vazio em vez do real — mesma classe de bug já vista com
    // route-model-binding no UserController.
    Route::resource('terminais', TerminalController::class, ['parameters' => ['terminais' => 'terminal']])
        ->only(['index', 'create', 'store', 'edit', 'update']);

    Route::resource('caixas', CaixaController::class)->only(['index', 'show']);
    Route::get('caixas/{caixa}/pdf', [CaixaController::class, 'pdf'])->name('caixas.pdf');
    Route::post('caixas/abrir', [CaixaController::class, 'abrir'])->name('caixas.abrir');
    Route::post('caixas/{caixa}/movimentar', [CaixaController::class, 'movimentar'])->name('caixas.movimentar');
    Route::post('caixas/{caixa}/fechar', [CaixaController::class, 'fechar'])->name('caixas.fechar');

    Route::get('financeiro/movimentacoes', [MovimentacaoController::class, 'index'])->name('movimentacoes.index');
    Route::get('financeiro/movimentacoes/imprimir', [MovimentacaoController::class, 'imprimir'])->name('movimentacoes.imprimir');
    Route::put('financeiro/movimentacoes/{movimentacao}', [MovimentacaoController::class, 'update'])->name('movimentacoes.update');
    Route::post('financeiro/movimentacoes/{movimentacao}/estornar', [MovimentacaoController::class, 'estornar'])->name('movimentacoes.estornar');

    Route::get('financeiro/transferencias', [TransferenciaCaixaController::class, 'index'])->name('transferencias.index');
    Route::post('financeiro/transferencias', [TransferenciaCaixaController::class, 'store'])->name('transferencias.store');

    Route::get('produtos/consultar-ean', [ProdutoController::class, 'consultarEan'])->name('produtos.consultar-ean');
    Route::get('produtos/ncm-autocomplete', [ProdutoController::class, 'autocompleteNcm'])->name('produtos.ncm-autocomplete');
    Route::post('produtos/sincronizar-ncm', [ProdutoController::class, 'sincronizarNcm'])->name('produtos.sincronizar-ncm');
    Route::resource('produtos', ProdutoController::class);
    Route::post('produtos/{produto}/ajustar-estoque', [ProdutoController::class, 'ajustarEstoque'])->name('produtos.ajustar-estoque');

    Route::resource('vendas', VendaController::class)->only(['index', 'create', 'store', 'show']);
    Route::get('vendas/{venda}/comprovante', [VendaController::class, 'comprovante'])->name('vendas.comprovante');
    Route::get('vendas/{venda}/comprovante.escpos', [VendaController::class, 'comprovanteEscpos'])->name('vendas.comprovante.escpos');
    Route::get('vendas/{venda}/voucher', [VendaController::class, 'voucher'])->name('vendas.voucher');
    Route::post('vendas/{venda}/cancelar', [VendaController::class, 'cancelar'])->name('vendas.cancelar');

    Route::get('food', [FoodController::class, 'index'])->name('food.index');
    Route::post('food/pontos', [FoodController::class, 'storePonto'])->name('food.pontos.store');
    Route::put('food/pontos/{ponto}', [FoodController::class, 'updatePonto'])->name('food.pontos.update');
    Route::post('food/pontos/{ponto}/abrir', [FoodController::class, 'abrir'])->name('food.pontos.abrir');
    Route::get('food/atendimentos/{atendimento}', [FoodController::class, 'show'])->name('food.atendimentos.show');
    Route::post('food/atendimentos/{atendimento}/itens', [FoodController::class, 'adicionarItem'])->name('food.atendimentos.itens.store');
    Route::delete('food/atendimentos/{atendimento}/itens/{item}', [FoodController::class, 'cancelarItem'])->name('food.atendimentos.itens.cancelar');
    Route::post('food/atendimentos/{atendimento}/pre-fechar', [FoodController::class, 'preFechar'])->name('food.atendimentos.pre-fechar');
    Route::post('food/atendimentos/{atendimento}/transferir', [FoodController::class, 'transferir'])->name('food.atendimentos.transferir');
    Route::post('food/atendimentos/{atendimento}/fechar', [FoodController::class, 'fechar'])->name('food.atendimentos.fechar');
    Route::get('food/atendimentos/{atendimento}/conta', [FoodController::class, 'conta'])->name('food.atendimentos.conta');
    Route::get('food/atendimentos/{atendimento}/conta.escpos', [FoodController::class, 'contaEscpos'])->name('food.atendimentos.conta.escpos');

    Route::get('quartos/estoque', [QuartoController::class, 'estoqueGeral'])->name('quartos.estoque-geral');
    Route::resource('quartos', QuartoController::class)->only(['index', 'create', 'store', 'edit', 'update']);
    Route::post('quartos/{quarto}/limpar', [QuartoController::class, 'limpar'])->name('quartos.limpar');
    Route::get('quartos/{quarto}/estoque', [QuartoController::class, 'estoque'])->name('quartos.estoque');
    Route::post('quartos/{quarto}/estoque', [QuartoController::class, 'emprestar'])->name('quartos.estoque.emprestar');
    Route::post('quartos/{quarto}/estoque/{produto}/devolver', [QuartoController::class, 'devolver'])->name('quartos.estoque.devolver');

    // Precisam vir ANTES do Route::resource abaixo: "hospedagens/{hospedagem}"
    // (show) casaria com essas rotas fixas primeiro, já que são resolvidas
    // na ordem de registro.
    Route::get('hospedagens/relatorio', [HospedagemController::class, 'relatorio'])->name('hospedagens.relatorio');
    Route::get('hospedagens/relatorio/pdf', [HospedagemController::class, 'relatorioPdf'])->name('hospedagens.relatorio.pdf');
    Route::get('hospedagens/mapa', [HospedagemController::class, 'mapa'])->name('hospedagens.mapa');
    Route::get('hospedagens/indicadores', [HospedagemController::class, 'indicadores'])->name('hospedagens.indicadores');
    Route::get('hospedagens/cafe-da-manha', [HospedagemController::class, 'cafeDaManha'])->name('hospedagens.cafe');

    // "parameters" explícito: o singular automático de "hospedagens" dá
    // "hospedagen" (inflector do Laravel não conhece plural em português),
    // o que faria o model binding de {hospedagem} injetar silenciosamente
    // uma Hospedagem vazia em vez da real — mesma classe de bug já vista em
    // UserController/TerminalController.
    Route::resource('hospedagens', HospedagemController::class, ['parameters' => ['hospedagens' => 'hospedagem']])
        ->only(['index', 'create', 'store', 'show']);
    Route::get('hospedagens/{hospedagem}/ficha', [HospedagemController::class, 'ficha'])->name('hospedagens.ficha');
    Route::post('hospedagens/{hospedagem}/checkin', [HospedagemController::class, 'checkin'])->name('hospedagens.checkin');
    Route::post('hospedagens/{hospedagem}/consumos', [HospedagemController::class, 'consumos'])->name('hospedagens.consumos');
    Route::post('hospedagens/{hospedagem}/solicitar-limpeza', [HospedagemController::class, 'solicitarLimpeza'])->name('hospedagens.solicitar-limpeza');
    Route::post('hospedagens/{hospedagem}/nao-perturbe', [HospedagemController::class, 'naoPerturbe'])->name('hospedagens.nao-perturbe');
    Route::get('hospedagens/{hospedagem}/checkout', [HospedagemController::class, 'checkoutForm'])->name('hospedagens.checkout');
    Route::post('hospedagens/{hospedagem}/checkout', [HospedagemController::class, 'checkout'])->name('hospedagens.checkout.store');
    Route::post('hospedagens/{hospedagem}/emitir-nfce', [HospedagemController::class, 'emitirNfce'])->name('hospedagens.emitir-nfce');
    Route::post('hospedagens/{hospedagem}/emitir-nfse', [HospedagemController::class, 'emitirNfse'])->name('hospedagens.emitir-nfse');
    Route::post('hospedagens/{hospedagem}/documentos-fiscais/{documento}/consultar-lote-nfse', [HospedagemController::class, 'consultarLoteNfse'])->name('hospedagens.documentos-fiscais.consultar-lote-nfse');
    Route::get('hospedagens/{hospedagem}/documentos-fiscais/{documento}/xml', [HospedagemController::class, 'downloadXmlFiscal'])->name('hospedagens.documentos-fiscais.xml');
    Route::post('hospedagens/{hospedagem}/cancelar', [HospedagemController::class, 'cancelar'])->name('hospedagens.cancelar');

    Route::get('tipos-entrada', [TipoEntradaController::class, 'index'])->name('tipos-entrada.index');
    Route::get('tipos-entrada/novo', [TipoEntradaController::class, 'create'])->name('tipos-entrada.create');
    Route::post('tipos-entrada', [TipoEntradaController::class, 'store'])->name('tipos-entrada.store');
    Route::get('tipos-entrada/{tipoEntrada}/editar', [TipoEntradaController::class, 'edit'])->name('tipos-entrada.edit');
    Route::put('tipos-entrada/{tipoEntrada}', [TipoEntradaController::class, 'update'])->name('tipos-entrada.update');
    Route::delete('tipos-entrada/{tipoEntrada}', [TipoEntradaController::class, 'destroy'])->name('tipos-entrada.destroy');

    Route::get('comissoes', [ComissaoController::class, 'index'])->name('comissoes.index');
    Route::post('comissoes/{comissao}/pagar', [ComissaoController::class, 'pagar'])->name('comissoes.pagar');

    Route::get('carteirinhas/imprimir-lote', [CarteirinhaController::class, 'imprimirLote'])->name('carteirinhas.imprimir-lote');
    Route::resource('carteirinhas', CarteirinhaController::class)->only(['index', 'show']);
    Route::get('carteirinhas/{carteirinha}/qrcode', [CarteirinhaController::class, 'qrcode'])->name('carteirinhas.qrcode');
    Route::get('carteirinhas/{carteirinha}/imprimir', [CarteirinhaController::class, 'imprimir'])->name('carteirinhas.imprimir');
    Route::post('carteirinhas/{carteirinha}/bloquear', [CarteirinhaController::class, 'bloquear'])->name('carteirinhas.bloquear');
    Route::post('carteirinhas/{carteirinha}/desbloquear', [CarteirinhaController::class, 'desbloquear'])->name('carteirinhas.desbloquear');

    Route::get('acessos', [AcessoController::class, 'index'])->name('acessos.index');

    Route::get('checkin', [CheckinController::class, 'index'])->name('checkin.index');
    Route::get('checkin/buscar', [CheckinController::class, 'buscar'])->name('checkin.buscar');
    Route::get('checkin/{cliente}', [CheckinController::class, 'show'])->name('checkin.show');
    Route::post('checkin/{cliente}/entrar', [CheckinController::class, 'registrar'])->name('checkin.registrar');
    Route::get('checkin/{cliente}/comprovante', [CheckinController::class, 'comprovante'])->name('checkin.comprovante');
    Route::get('checkin/{cliente}/voucher-pdf', [CheckinController::class, 'voucherPdf'])->name('checkin.voucher-pdf');

    Route::get('validar-voucher', [ValidacaoVoucherController::class, 'index'])->name('validacao-voucher.index');
    Route::post('validar-voucher', [ValidacaoVoucherController::class, 'validar'])->name('validacao-voucher.validar');

    // PWA instalável pro porteiro — mesma tela/lógica de validação, só que
    // numa URL própria (start_url do manifest) com layout enxuto (sem menu
    // administrativo), pra virar um "app" de verdade na tela inicial dele.
    Route::get('app-validador', [ValidacaoVoucherController::class, 'app'])->name('app-validador.index');
    Route::get('app-validador/manifest.webmanifest', [ValidacaoVoucherController::class, 'manifest'])->name('app-validador.manifest');

    Route::get('cortesias', [CortesiaController::class, 'index'])->name('cortesias.index');
    Route::post('cortesias', [CortesiaController::class, 'store'])->name('cortesias.store');
    Route::get('cortesias/resultado', [CortesiaController::class, 'resultado'])->name('cortesias.resultado');
    Route::get('cortesias/voucher', [CortesiaController::class, 'voucher'])->name('cortesias.voucher');

    Route::get('empresa', [EmpresaController::class, 'edit'])->name('empresa.edit');
    Route::put('empresa', [EmpresaController::class, 'update'])->name('empresa.update');
    Route::post('empresa/limpar-financeiro', [EmpresaController::class, 'limparFinanceiro'])->name('empresa.limpar-financeiro');
    Route::get('empresa/consultar-cnpj', [EmpresaController::class, 'consultarCnpj'])->name('empresa.consultar-cnpj');
    Route::get('empresa/certificado', [EmpresaController::class, 'downloadCertificado'])->name('empresa.certificado.download');
    Route::delete('empresa/certificado', [EmpresaController::class, 'destroyCertificado'])->name('empresa.certificado.destroy');

    Route::get('cidades', [CidadeController::class, 'index'])->name('cidades.index');
    Route::post('cidades/sincronizar', [CidadeController::class, 'sincronizar'])->name('cidades.sincronizar');
    Route::get('cidades/autocomplete', [CidadeController::class, 'autocomplete'])->name('cidades.autocomplete');

    Route::resource('unidades', UnidadeController::class)->only(['index', 'create', 'store', 'edit', 'update']);
    Route::get('link-vendas', [UnidadeController::class, 'linkExterno'])->name('unidades.link-externo');
    // "parameters" explícito: evita o bug clássico do inflector com plurais
    // em português (model binding injetar User vazio → Policy nega com 403).
    Route::resource('usuarios', UserController::class, ['parameters' => ['usuarios' => 'usuario']])
        ->except(['show']);

    Route::get('auditoria', [AuditoriaController::class, 'index'])->name('auditoria.index');

    Route::get('relatorios', [RelatorioController::class, 'index'])->name('relatorios.index');
    Route::get('relatorios/financeiro.pdf', [RelatorioController::class, 'financeiroPdf'])->name('relatorios.financeiro.pdf');
    Route::get('relatorios/financeiro.xlsx', [RelatorioController::class, 'financeiroExcel'])->name('relatorios.financeiro.excel');
    Route::get('relatorios/movimentacoes', [RelatorioMovimentacaoController::class, 'index'])->name('relatorios.movimentacoes');
    Route::get('relatorios/movimentacoes/pdf', [RelatorioMovimentacaoController::class, 'pdf'])->name('relatorios.movimentacoes.pdf');
});
