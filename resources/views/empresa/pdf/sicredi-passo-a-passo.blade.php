{{--
    Passo a passo da adesão à API Pix Sicredi, para o cliente levar à
    cooperativa. Gerado com os dados da empresa (CNPJ, nome no certificado).
--}}
@php
    $cnpj = preg_replace('/\D+/', '', (string) $empresa->cnpj);
    $cnpjFmt = strlen($cnpj) === 14 ? preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $cnpj) : ($empresa->cnpj ?: '—');
    $cn = "api-parceiros-{$nomeParceiro}.sicredi.net";
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Passo a passo — API Pix Sicredi</title>
    <style>
        @page { margin: 26px 34px 40px; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; font-size: 10.5px; line-height: 1.5; color: #1d2939; }
        h1 { font-size: 19px; margin: 0; color: #0f5132; }
        h2 { font-size: 13px; margin: 0 0 6px; color: #0f5132; }
        p { margin: 0 0 6px; }
        .topo { border-bottom: 3px solid #3fa110; padding-bottom: 10px; margin-bottom: 12px; }
        .sub { color: #475467; font-size: 10.5px; margin-top: 3px; }
        .empresa { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .empresa td { border: 1px solid #d0d5dd; padding: 5px 8px; }
        .empresa td.r { background: #f2f4f7; font-weight: bold; width: 26%; }
        .caixa { border: 1px solid #d0d5dd; border-radius: 6px; padding: 9px 11px; margin-bottom: 10px; page-break-inside: avoid; }
        .etapa { border-left: 5px solid #3fa110; }
        /* dompdf ignora line-height em inline-block: centraliza com padding. */
        .num { display: inline-block; width: 20px; padding: 3px 0 2px; text-align: center; border-radius: 10px; background: #3fa110; color: #fff; font-weight: bold; font-size: 10px; line-height: 1; margin-right: 6px; }
        .quem { float: right; font-size: 9px; font-weight: bold; padding: 2px 7px; border-radius: 9px; }
        .quem-coop { background: #fef0c7; color: #93370d; }
        .quem-portal { background: #d1e9ff; color: #194185; }
        .quem-sistema { background: #d1fadf; color: #05603a; }
        ol, ul { margin: 2px 0 6px 18px; padding: 0; }
        li { margin-bottom: 2px; }
        .mono { font-family: DejaVu Sans Mono, monospace; font-size: 9.5px; }
        .alerta { background: #fffaeb; border: 1px solid #fedf89; color: #7a2e0e; }
        .dica { background: #f0f9ff; border: 1px solid #b9e6fe; color: #0b4a6f; }
        .msg { background: #f9fafb; border: 1px dashed #98a2b3; padding: 8px 10px; font-style: italic; }
        table.dados { width: 100%; border-collapse: collapse; }
        table.dados td { border: 1px solid #e4e7ec; padding: 3px 6px; font-size: 9.5px; }
        table.dados td.r { background: #f9fafb; width: 32%; }
        .check td { padding: 3px 4px; border-bottom: 1px solid #eaecf0; }
        .box { display: inline-block; width: 10px; height: 10px; border: 1px solid #667085; margin-right: 6px; }
        .rodape { position: fixed; bottom: -26px; left: 0; right: 0; font-size: 8.5px; color: #98a2b3; text-align: center; }
    </style>
</head>
<body>
    <div class="rodape">Passo a passo — cobrança Pix com QR Code pelo Sicredi · {{ $empresa->nome }} · gerado em {{ now()->format('d/m/Y') }}</div>

    <div class="topo">
        <h1>Como ativar a cobrança Pix (QR Code) com o Sicredi</h1>
        <p class="sub">Guia para a empresa solicitar ao Sicredi o acesso à <strong>API Pix</strong>. Com ele, o caixa gera um QR Code com o valor exato da venda e o sistema confirma o pagamento sozinho, sem conferir o extrato.</p>
    </div>

    <table class="empresa">
        <tr><td class="r">Empresa</td><td>{{ $empresa->razao_social ?: $empresa->nome }}</td></tr>
        <tr><td class="r">CNPJ</td><td>{{ $cnpjFmt }}</td></tr>
        <tr><td class="r">Onde configurar no sistema</td><td>Minha Empresa → aba <strong>Financeiro</strong> → Provedor: <strong>Sicredi — API Pix</strong></td></tr>
    </table>

    <div class="caixa dica">
        <strong>Antes de começar, tenha em mãos:</strong>
        <ul>
            <li>Conta <strong>PJ ativa no Sicredi</strong> e acesso ao Internet Banking.</li>
            <li>Uma <strong>chave Pix cadastrada nessa conta</strong> (de preferência o CNPJ). É nela que o dinheiro vai cair.</li>
            <li>Um <strong>e-mail</strong> da empresa para o cadastro no Portal do Desenvolvedor. Use o mesmo e-mail em todas as etapas.</li>
            <li>Acesso de <strong>administrador</strong> ao sistema (tela Minha Empresa).</li>
        </ul>
        Prazo comum: de alguns dias a 2 semanas, conforme a cooperativa. Tarifas por Pix recebido são combinadas com a sua cooperativa.
    </div>

    <div class="caixa etapa">
        <span class="quem quem-coop">COOPERATIVA</span>
        <h2><span class="num">1</span>Pedir a adesão à API Pix</h2>
        <ol>
            <li>Procure o gerente da sua conta na cooperativa Sicredi.</li>
            <li>Peça a <strong>adesão à API Pix — integração individual (sistema próprio)</strong> e assine o termo de adesão.</li>
            <li>Informe o e-mail que será usado no Portal do Desenvolvedor.</li>
            <li>Aguarde o e-mail do Sicredi com o <strong>ID de Adesão</strong>. Guarde esse número.</li>
        </ol>
        <p><strong>Mensagem pronta para enviar ao gerente:</strong></p>
        <div class="msg">
            Olá! Somos a {{ $empresa->razao_social ?: $empresa->nome }} (CNPJ {{ $cnpjFmt }}) e queremos aderir à API Pix do Sicredi, na modalidade
            de integração individual com nosso sistema de gestão, para gerar cobranças Pix imediatas (QR Code dinâmico) no caixa.
            Precisamos dos escopos de cobrança imediata (cob.write e cob.read). O e-mail para o Portal do Desenvolvedor é: ______________________.
            Podem nos enviar o termo de adesão? Obrigado!
        </div>
    </div>

    <div class="caixa etapa">
        <span class="quem quem-portal">PORTAL SICREDI</span>
        <h2><span class="num">2</span>Criar a conta no Portal do Desenvolvedor e pedir o acesso</h2>
        <ol>
            <li>Acesse <strong>developers.sicredi.com.br</strong> e crie a conta <strong>com o mesmo e-mail</strong> informado na adesão.</li>
            <li>No menu <strong>Suporte → Abrir chamado</strong>, escolha <strong>"Acesso à API Pix"</strong> e informe o <strong>ID de Adesão</strong>. Isso só é feito uma vez.</li>
            <li>Aguarde a liberação. O Sicredi avisa quando o acesso estiver ativo.</li>
        </ol>
    </div>

    <div class="caixa etapa">
        <span class="quem quem-sistema">NO SISTEMA</span>
        <h2><span class="num">3</span>Gerar o CSR (pedido de certificado) no sistema</h2>
        <p>O certificado digital da integração é pedido por meio de um arquivo <strong>.csr</strong>. O sistema gera esse arquivo e guarda a chave secreta com segurança, então não é preciso instalar nenhum programa.</p>
        <ol>
            <li>No sistema: <strong>Minha Empresa → Financeiro</strong> → Provedor <strong>"Sicredi — API Pix"</strong>.</li>
            <li>No quadro <strong>CSR</strong>, confira o nome do parceiro e clique em <strong>Gerar CSR</strong>.</li>
            <li>Clique em <strong>Baixar .csr</strong> e salve o arquivo.</li>
        </ol>
        <div class="caixa alerta" style="margin: 6px 0 0;">
            <strong>Importante:</strong> depois de enviar o .csr ao Sicredi, <strong>não gere um CSR novo</strong>. O certificado só funciona com o CSR que o originou. Se gerar outro por engano, envie o novo arquivo ao Portal e espere outro certificado.
        </div>
    </div>

    <div class="caixa etapa">
        <span class="quem quem-portal">PORTAL SICREDI</span>
        <h2><span class="num">4</span>Registrar o CSR e baixar o certificado</h2>
        <ol>
            <li>No Portal: <strong>Certificados e Credenciais → Registrar Novo CSR</strong>.</li>
            <li>Preencha o que for pedido e anexe o arquivo <strong>.csr</strong> baixado na etapa 3. Envie.</li>
            <li>Aguarde o Sicredi validar. Quando estiver pronto, <strong>baixe o certificado assinado</strong> (arquivo .cer, .crt ou .pem).</li>
        </ol>
        <p>Se o Portal pedir os dados do certificado, eles já estão dentro do .csr gerado pelo sistema:</p>
        <table class="dados">
            <tr><td class="r">CN (nome comum)</td><td class="mono">{{ $cn }}</td></tr>
            <tr><td class="r">O (organização)</td><td class="mono">Confederacao Interestadual das Cooperativas Ligadas ao Sicredi</td></tr>
            <tr><td class="r">OU (unidade)</td><td class="mono">API Pix Sicredi</td></tr>
            <tr><td class="r">L / ST / C</td><td class="mono">Porto Alegre / RS / BR</td></tr>
            <tr><td class="r">serialNumber</td><td class="mono">urn:cpfcnpj:{{ $cnpj ?: '(CNPJ da empresa)' }}</td></tr>
        </table>
    </div>

    <div class="caixa etapa">
        <span class="quem quem-portal">PORTAL SICREDI</span>
        <h2><span class="num">5</span>Gerar o Client ID e o Client Secret</h2>
        <ol>
            <li>Ainda em <strong>Certificados e Credenciais</strong>, gere as credenciais da aplicação: <strong>Client ID</strong> e <strong>Client Secret</strong>.</li>
            <li>Copie os dois e guarde em local seguro. O Client Secret é como uma senha: não envie por grupos ou e-mail aberto.</li>
        </ol>
    </div>

    <div class="caixa etapa">
        <span class="quem quem-sistema">NO SISTEMA</span>
        <h2><span class="num">6</span>Configurar e testar no sistema</h2>
        <ol>
            <li>Em <strong>Minha Empresa → Financeiro</strong>, com o provedor <strong>Sicredi</strong>, preencha:
                <ul>
                    <li><strong>Client ID</strong> e <strong>Client Secret</strong> (etapa 5);</li>
                    <li><strong>Tipo e chave Pix</strong> da conta Sicredi da empresa;</li>
                    <li>Ambiente: <strong>Produção</strong>;</li>
                    <li><strong>Certificado assinado pelo Sicredi</strong> (etapa 4). A chave privada não precisa ser enviada, porque já está guardada no sistema.</li>
                </ul>
            </li>
            <li>Clique em <strong>Salvar</strong> e depois em <strong>Testar conexão com o Sicredi</strong>. A mensagem verde confirma que está tudo certo.</li>
            <li>Faça uma venda de teste de <strong>R$ 1,00</strong> no PDV com a forma <strong>Pix QR Code</strong>, pague pelo celular e veja a venda ser concluída sozinha.</li>
        </ol>
    </div>

    <div class="caixa">
        <h2>Opcional: ambiente de testes (homologação)</h2>
        <p>Para testar sem dinheiro real, peça credenciais de homologação. Elas <strong>não</strong> saem pela geração normal:</p>
        <ul>
            <li>No Portal: <strong>Suporte → Abrir chamado → "Suporte Técnico API Pix"</strong>, motivo <strong>"Cadastro Ambiente Homologação API PIX"</strong>, informando o CNPJ; <strong>ou</strong></li>
            <li>No Internet Banking: <strong>Outros Serviços → Acesso à API Pix → Gerar Credenciais</strong>, escolhendo <strong>Homologação</strong>.</li>
        </ul>
        <p>O Sicredi informa uma <strong>URL de homologação</strong>. No sistema, escolha o ambiente "Homologação" e cole essa URL.</p>
    </div>

    <div class="caixa">
        <h2>Problemas comuns</h2>
        <table class="dados">
            <tr><td class="r">"Acesso negado" (403) no teste</td><td>O certificado não é o do último CSR gerado, ou a API Pix ainda não foi liberada. Confira com o Sicredi pelo chamado no Portal.</td></tr>
            <tr><td class="r">"Credenciais inválidas" (401)</td><td>Client ID ou Client Secret digitados errado, ou gerados em outro ambiente (homologação × produção).</td></tr>
            <tr><td class="r">Certificado vencendo</td><td>O sistema avisa na tela. Gere um CSR novo, registre no Portal e envie o certificado novo.</td></tr>
            <tr><td class="r">Dúvidas</td><td>Técnicas: chamado no Portal do Desenvolvedor. Cadastro e conta: sua cooperativa (SAC Sicredi 0800 724 7220).</td></tr>
        </table>
    </div>

    <div class="caixa">
        <h2>Checklist</h2>
        <table class="check" width="100%">
            <tr><td><span class="box"></span>Termo de adesão à API Pix assinado na cooperativa</td><td><span class="box"></span>ID de Adesão recebido: ______________</td></tr>
            <tr><td><span class="box"></span>Conta criada no Portal do Desenvolvedor</td><td><span class="box"></span>Chamado "Acesso à API Pix" aberto e liberado</td></tr>
            <tr><td><span class="box"></span>CSR gerado no sistema e registrado no Portal</td><td><span class="box"></span>Certificado assinado baixado</td></tr>
            <tr><td><span class="box"></span>Client ID e Client Secret gerados</td><td><span class="box"></span>Dados salvos no sistema e "Testar conexão" OK</td></tr>
            <tr><td colspan="2"><span class="box"></span>Venda de teste de R$ 1,00 paga e concluída no PDV</td></tr>
        </table>
    </div>
</body>
</html>
