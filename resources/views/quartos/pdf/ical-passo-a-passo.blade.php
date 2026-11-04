{{--
    Passo a passo da sincronização iCal (Booking.com/Airbnb) para a equipe
    da pousada: ativação por quarto e uso no dia a dia.
--}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Passo a passo — Booking.com no sistema</title>
    <style>
        @page { margin: 26px 34px 40px; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; font-size: 10.5px; line-height: 1.5; color: #1d2939; }
        h1 { font-size: 19px; margin: 0; color: #003580; }
        h2 { font-size: 13px; margin: 0 0 6px; color: #003580; }
        p { margin: 0 0 6px; }
        .topo { border-bottom: 3px solid #0071c2; padding-bottom: 10px; margin-bottom: 12px; }
        .sub { color: #475467; margin-top: 3px; }
        .parte { margin: 14px 0 8px; padding: 5px 10px; background: #003580; color: #fff; font-weight: bold; font-size: 12px; border-radius: 4px; }
        .caixa { border: 1px solid #d0d5dd; border-radius: 6px; padding: 9px 11px; margin-bottom: 10px; page-break-inside: avoid; }
        .etapa { border-left: 5px solid #0071c2; }
        /* dompdf ignora line-height em inline-block: centraliza com padding. */
        .num { display: inline-block; width: 20px; padding: 3px 0 2px; text-align: center; border-radius: 10px; background: #0071c2; color: #fff; font-weight: bold; font-size: 10px; line-height: 1; margin-right: 6px; }
        .onde { float: right; font-size: 9px; font-weight: bold; padding: 2px 7px; border-radius: 9px; }
        .onde-sistema { background: #d1fadf; color: #05603a; }
        .onde-booking { background: #d1e9ff; color: #194185; }
        ol, ul { margin: 2px 0 6px 18px; padding: 0; }
        li { margin-bottom: 2px; }
        .alerta { background: #fffaeb; border: 1px solid #fedf89; color: #7a2e0e; }
        .dica { background: #f0f9ff; border: 1px solid #b9e6fe; color: #0b4a6f; }
        .perigo { background: #fef3f2; border: 1px solid #fecdca; color: #912018; }
        table.t { width: 100%; border-collapse: collapse; }
        table.t td, table.t th { border: 1px solid #e4e7ec; padding: 4px 6px; font-size: 9.5px; vertical-align: top; text-align: left; }
        table.t th { background: #f2f4f7; }
        .check td { padding: 3px 4px; border-bottom: 1px solid #eaecf0; }
        .box { display: inline-block; width: 10px; height: 10px; border: 1px solid #667085; margin-right: 6px; }
        .tag { display: inline-block; padding: 0 5px; border-radius: 3px; font-weight: bold; font-size: 9px; }
        .tag-azul { background: #d1e9ff; color: #194185; }
        .tag-vermelha { background: #d92d20; color: #fff; }
        .rodape { position: fixed; bottom: -26px; left: 0; right: 0; font-size: 8.5px; color: #98a2b3; text-align: center; }
    </style>
</head>
<body>
    <div class="rodape">Passo a passo — Booking.com / Airbnb no sistema (calendário iCal) · {{ $empresa->nome }} · gerado em {{ now()->format('d/m/Y') }}</div>

    <div class="topo">
        <h1>Booking.com e Airbnb no sistema: ativação e uso no dia a dia</h1>
        <p class="sub">Guia para a recepção e a gerência da pousada. Com a sincronização ligada, o mesmo quarto não é vendido duas vezes: o que é reservado no balcão fecha a data no Booking.com, e o que é vendido no Booking.com bloqueia o quarto no sistema.</p>
    </div>

    <div class="caixa dica">
        <strong>Como funciona, em uma frase:</strong> cada quarto tem dois links. Um o Booking.com lê para saber quando o quarto está ocupado aqui; o outro o sistema lê para saber o que foi vendido lá.
        <ul style="margin-top: 4px">
            <li><strong>O que passa:</strong> só as datas ocupadas (entrada e saída).</li>
            <li><strong>O que não passa:</strong> nome do hóspede, telefone, valor e pagamento. Esses dados ficam no extranet ou no e-mail do Booking.com.</li>
            <li><strong>Tempo:</strong> o sistema lê o Booking.com a cada 15 minutos. O Booking.com lê o sistema no ritmo dele, normalmente entre 30 minutos e 1 hora.</li>
        </ul>
    </div>

    <div class="caixa">
        <h2>Antes de começar</h2>
        <ul>
            <li>Login do <strong>extranet do Booking.com</strong> (admin.booking.com) com acesso a "Tarifas e Disponibilidade".</li>
            <li>Usuário do sistema com permissão para <strong>editar quartos</strong>.</li>
            <li>Os quartos cadastrados no sistema (Hospedagem → Quartos), com o mesmo número ou nome usado no Booking.com.</li>
            <li>A configuração é feita <strong>uma vez por quarto</strong> e leva uns 2 minutos cada.</li>
        </ul>
        <div class="caixa alerta" style="margin: 6px 0 0">
            <strong>Importante:</strong> cada quarto do sistema deve corresponder a <strong>uma acomodação</strong> no Booking.com. Se no Booking.com um tipo de quarto representa várias unidades iguais (ex.: "Quarto Duplo — 5 unidades"), fale com o suporte antes de ativar.
        </div>
    </div>

    <div class="parte">PARTE 1 — ATIVAÇÃO (uma vez por quarto)</div>

    <div class="caixa etapa">
        <span class="onde onde-sistema">NO SISTEMA</span>
        <h2><span class="num">1</span>Copiar o link do quarto</h2>
        <ol>
            <li>Entre em <strong>Hospedagem → Quartos</strong> e clique em <strong>Editar</strong> no quarto.</li>
            <li>Desça até o quadro <strong>"Booking.com / Airbnb (calendário iCal)"</strong>.</li>
            <li>No item <strong>1. Link deste quarto</strong>, clique em <strong>Copiar</strong>.</li>
        </ol>
        <p>Deixe essa tela aberta; você volta nela no passo 4.</p>
    </div>

    <div class="caixa etapa">
        <span class="onde onde-booking">BOOKING.COM</span>
        <h2><span class="num">2</span>Importar o calendário do sistema no Booking.com</h2>
        <ol>
            <li>Em outra aba, entre no <strong>extranet do Booking.com</strong>.</li>
            <li>Vá em <strong>Tarifas e Disponibilidade → Sincronizar calendários</strong>.</li>
            <li>Escolha a acomodação correspondente ao quarto e clique em <strong>Importar calendário</strong>.</li>
            <li>Cole o link copiado no passo 1 e dê um nome, por exemplo <strong>"Sistema da pousada"</strong>. Salve.</li>
        </ol>
    </div>

    <div class="caixa etapa">
        <span class="onde onde-booking">BOOKING.COM</span>
        <h2><span class="num">3</span>Copiar o calendário do Booking.com</h2>
        <ol>
            <li>Na mesma tela de <strong>Sincronizar calendários</strong>, da mesma acomodação, clique em <strong>Exportar calendário</strong>.</li>
            <li>Copie o link que o Booking.com mostrar. Ele começa com <em>https://admin.booking.com/...</em> e termina com um código.</li>
        </ol>
    </div>

    <div class="caixa etapa">
        <span class="onde onde-sistema">NO SISTEMA</span>
        <h2><span class="num">4</span>Colar o link do Booking.com e sincronizar</h2>
        <ol>
            <li>Volte à tela do quarto, no item <strong>2. Link do calendário do Booking.com</strong>.</li>
            <li>Cole o link copiado no passo 3 e clique em <strong>Salvar e sincronizar</strong>.</li>
            <li>Confira a mensagem verde "Calendário sincronizado" e a linha <strong>"Última leitura"</strong> com a data e a hora de agora.</li>
        </ol>
        <p>Pronto: a partir daqui funciona sozinho. Repita os passos 1 a 4 para cada quarto.</p>
    </div>

    <div class="caixa">
        <h2>Usa também o Airbnb?</h2>
        <p>O caminho é o mesmo: no anúncio do Airbnb, em <strong>Disponibilidade → Sincronizar calendários</strong>, importe o link do passo 1 e copie o link de exportação do Airbnb. Por enquanto, cada quarto do sistema lê <strong>um</strong> calendário externo. Se o mesmo quarto é vendido no Booking.com <strong>e</strong> no Airbnb ao mesmo tempo, fale com o suporte antes de ativar.</p>
    </div>

    <div class="parte">PARTE 2 — USO NO DIA A DIA</div>

    <div class="caixa">
        <h2>Chegou uma reserva pelo Booking.com</h2>
        <ol>
            <li>Em até <strong>15 minutos</strong>, ela aparece no <strong>Mapa de Quartos</strong>, no card do quarto, como um aviso azul: <span class="tag tag-azul">Booking.com · 05/11 — 08/11</span>.</li>
            <li>As datas ficam <strong>bloqueadas</strong>: ninguém consegue reservar aquele quarto no balcão nesse período, e o sistema avisa "foi vendido no Booking.com".</li>
            <li>Quando o hóspede chegar (ou antes, se quiser deixar pronto), clique em <strong>Registrar hóspede</strong> no aviso.</li>
            <li>A reserva abre com <strong>quarto, datas e observação já preenchidos</strong>. Busque ou cadastre o hóspede (nome e CPF estão no extranet ou no e-mail do Booking.com) e salve.</li>
            <li>Daí em diante é uma reserva normal: <strong>check-in, consumo, check-out</strong> e nota fiscal, como sempre.</li>
        </ol>
    </div>

    <div class="caixa">
        <h2>Reserva feita no balcão, no telefone ou no WhatsApp</h2>
        <p>Faça a reserva no sistema como sempre. O Booking.com fecha essas datas sozinho na próxima leitura, normalmente em até 1 hora. Não precisa fechar nada no extranet.</p>
    </div>

    <div class="caixa">
        <h2>Cancelamentos e mudanças de data</h2>
        <ul>
            <li><strong>Cancelou no Booking.com:</strong> em até 15 minutos o bloqueio some e o quarto fica livre no sistema. Se o hóspede já estava registrado, cancele também a reserva dele no sistema.</li>
            <li><strong>Mudou as datas no Booking.com:</strong> o bloqueio se ajusta sozinho. Se o hóspede já estava registrado, ajuste as datas da reserva dele no sistema.</li>
            <li><strong>Cancelou no sistema:</strong> o Booking.com libera a data na próxima leitura dele.</li>
            <li><strong>Datas fechadas à mão no extranet</strong> também chegam ao sistema e bloqueiam o quarto.</li>
        </ul>
    </div>

    <div class="caixa perigo">
        <h2 style="color: #912018">Apareceu OVERBOOKING em vermelho</h2>
        <p>Significa que o Booking.com vendeu datas em que o quarto <strong>já tinha hóspede</strong> no sistema. Isso acontece quando as duas vendas caem no intervalo entre as leituras. O aviso <span class="tag tag-vermelha">OVERBOOKING</span> aparece no quarto e no mapa.</p>
        <ol>
            <li>Veja qual das duas reservas foi feita primeiro.</li>
            <li>Ofereça outro quarto a um dos hóspedes ou peça ao Booking.com a realocação ou o cancelamento sem multa, pelo extranet ou pelo suporte do Booking.</li>
            <li>Ajuste ou cancele a reserva no sistema conforme o combinado.</li>
        </ol>
    </div>

    <div class="caixa dica">
        <h2>Boas práticas na alta temporada</h2>
        <ul>
            <li>Antes de vender no balcão um quarto para <strong>hoje ou amanhã</strong>, abra o quarto e clique em <strong>Sincronizar agora</strong>. Isso traz na hora o que o Booking.com vendeu.</li>
            <li>Confira o Mapa de Quartos no começo de cada turno.</li>
            <li>Registre os hóspedes do Booking.com assim que a reserva chegar. Assim a ficha já fica pronta para o check-in.</li>
        </ul>
    </div>

    <div class="caixa">
        <h2>Problemas comuns</h2>
        <table class="t">
            <tr><th style="width: 34%">O que aparece</th><th>O que fazer</th></tr>
            <tr><td>"Erro na última leitura" no quarto</td><td>O link do Booking.com está errado ou foi trocado. Copie de novo em <strong>Exportar calendário</strong> (passo 3), cole no quarto e clique em Salvar e sincronizar.</td></tr>
            <tr><td>"O link precisa começar com https://"</td><td>Foi colado um texto incompleto. Copie o link inteiro do Booking.com.</td></tr>
            <tr><td>Reserva do Booking.com não apareceu</td><td>Aguarde até 15 minutos ou clique em <strong>Sincronizar agora</strong> no quarto. Confira se o link colado é da acomodação certa.</td></tr>
            <tr><td>Booking.com não fechou a data reservada no balcão</td><td>O Booking.com lê no ritmo dele (até cerca de 1 hora). No extranet, em Sincronizar calendários, use a opção de atualizar o calendário importado, se houver.</td></tr>
            <tr><td>O link do quarto foi enviado para quem não devia</td><td>No quarto, clique em <strong>gerar novo link</strong> e refaça o passo 2 no Booking.com com o link novo.</td></tr>
            <tr><td>Quero desligar a sincronização</td><td>No quarto, apague o link do Booking.com, clique em Salvar e remova o calendário importado no extranet.</td></tr>
            <tr><td>A pousada vai contratar um channel manager</td><td>Desligue a sincronização em todos os quartos antes. O Booking.com não deixa usar calendário importado junto com channel manager.</td></tr>
        </table>
    </div>

    <div class="caixa">
        <h2>Checklist de ativação</h2>
        <table class="check" width="100%">
            <tr><td><span class="box"></span>Login do extranet do Booking.com em mãos</td><td><span class="box"></span>Quartos do sistema conferidos com as acomodações do Booking.com</td></tr>
            <tr><td colspan="2"><span class="box"></span>Para cada quarto: link do sistema importado no Booking.com <strong>e</strong> link do Booking.com colado no sistema</td></tr>
            <tr><td><span class="box"></span>"Última leitura" aparecendo em todos os quartos</td><td><span class="box"></span>Equipe da recepção treinada no "Registrar hóspede"</td></tr>
        </table>
    </div>
</body>
</html>
