<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 30px 40px; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; font-size: 11px; line-height: 1.5; color: #1d2939; }
        h1 { margin: 0 0 4px; font-size: 16px; text-align: center; color: #101828; text-transform: uppercase; }
        h2 { margin: 0 0 18px; font-size: 12px; text-align: center; color: #475467; text-transform: uppercase; }
        h3 { margin: 16px 0 4px; font-size: 11.5px; color: #101828; }
        p { margin: 0 0 6px; text-align: justify; }
        .linha { display: table; width: 100%; margin-bottom: 4px; }
        .linha .rotulo { display: table-cell; width: 90px; color: #667085; }
        .linha .campo { display: table-cell; border-bottom: 1px solid #98a2b3; }
        table.dependentes { width: 100%; border-collapse: collapse; margin: 6px 0 10px; }
        table.dependentes th, table.dependentes td { border: 1px solid #d0d5dd; padding: 4px 8px; text-align: left; font-size: 10.5px; }
        table.dependentes th { background: #f9fafb; }
        .assinaturas { margin-top: 40px; }
        .assinaturas .bloco { display: inline-block; width: 45%; text-align: center; margin-bottom: 30px; }
        .assinaturas .bloco.direita { margin-left: 4%; }
        .assinaturas .linha-assinatura { border-top: 1px solid #1d2939; margin-top: 34px; padding-top: 4px; }
        .rodape { margin-top: 24px; padding-top: 8px; border-top: 1px solid #eaecf0; font-size: 9px; color: #98a2b3; text-align: center; }
        .cabecalho-logo { text-align: center; margin-bottom: 10px; }
        .cabecalho-logo img { max-height: 60px; max-width: 220px; }
    </style>
</head>
<body>
    @if ($empresa->logoDataUri())
        <div class="cabecalho-logo"><img src="{{ $empresa->logoDataUri() }}" alt="{{ $empresa->nome }}"></div>
    @endif
    <h1>Contrato de Adesão — {{ $plano->nome }}</h1>
    <h2>{{ $empresa->nome }}</h2>

    <p>
        Pelo presente instrumento particular, de um lado <strong>{{ $empresa->nome }}</strong>
        @if ($empresa->cnpj), inscrita no CNPJ nº {{ $empresa->cnpj }}@endif
        @if (! empty($empresa->configuracoes['endereco'])), com sede em {{ $empresa->configuracoes['endereco'] }}@endif,
        doravante denominada <strong>CONTRATADA</strong>, e de outro lado:
    </p>

    <div class="linha"><span class="rotulo">Nome:</span><span class="campo">{{ $cliente->nome }}</span></div>
    <div class="linha"><span class="rotulo">CPF:</span><span class="campo">{{ $cliente->cpf }}</span></div>
    @if ($cliente->rg)<div class="linha"><span class="rotulo">RG:</span><span class="campo">{{ $cliente->rg }}</span></div>@endif
    <div class="linha"><span class="rotulo">Endereço:</span><span class="campo">{{ trim("{$cliente->endereco}, {$cliente->numero} {$cliente->bairro} - {$cliente->cidade}/{$cliente->uf}", ' ,-') ?: '—' }}</span></div>
    <div class="linha"><span class="rotulo">Contato:</span><span class="campo">{{ $cliente->telefone ?? $cliente->whatsapp ?? '—' }} {{ $cliente->email ? '· '.$cliente->email : '' }}</span></div>

    <p style="margin-top: 8px;">doravante denominado <strong>CONTRATANTE</strong>, têm entre si justo e contratado o que segue:</p>

    <h3>Cláusula 1ª — Do Objeto</h3>
    <p>O presente contrato tem por objeto a adesão do CONTRATANTE ao <strong>{{ $plano->nome }}</strong>, que oferece acesso e benefícios conforme descritos nas cláusulas seguintes, na unidade {{ $contrato->unidade->nome }}.</p>

    <h3>Cláusula 2ª — Dos Benefícios</h3>
    @if ($plano->descricao)
        <p>{!! nl2br(e($plano->descricao)) !!}</p>
    @else
        <p>Acesso ao parque {{ $plano->dias_acesso_semana }} dia(s) por semana, com direito a até {{ $plano->max_dependentes }} dependente(s) incluído(s) sem custo adicional.</p>
    @endif

    <h3>Cláusula 3ª — Das Condições de Adesão</h3>
    <p>Para adesão ao plano, o CONTRATANTE deve manter as mensalidades em dia de acordo com o vencimento escolhido, sob pena de suspensão dos benefícios por inadimplência. Os benefícios são válidos enquanto o plano estiver ativo e adimplente.</p>

    @if ($contrato->dependentes->isNotEmpty())
        <p style="margin-bottom: 4px;"><strong>Dependentes incluídos neste contrato:</strong></p>
        <table class="dependentes">
            <thead><tr><th>Nome</th><th>CPF</th><th>Parentesco</th></tr></thead>
            <tbody>
                @foreach ($contrato->dependentes as $dependente)
                    <tr><td>{{ $dependente->nome }}</td><td>{{ $dependente->cpf ?? '—' }}</td><td>{{ $dependente->parentesco ?? '—' }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h3>Cláusula 4ª — Dos Valores e Pagamento</h3>
    <p>
        No ato da contratação será cobrada uma caução, a título de entrada, no valor de
        <strong>R$ {{ number_format($contrato->valor_caucao, 2, ',', '.') }}</strong>.
        A primeira mensalidade vencerá em
        <strong>{{ $contrato->primeiro_vencimento?->format('d/m/Y') ?? 'data a definir' }}</strong>.
    </p>
    <p>
        O valor da mensalidade é de <strong>R$ {{ number_format($contrato->valor_mensal, 2, ',', '.') }}</strong>,
        @if ($contrato->desconto_percentual > 0) com desconto de {{ rtrim(rtrim(number_format($contrato->desconto_percentual, 2, ',', '.'), '0'), ',') }}% aplicado, @endif
        com vencimento todo dia <strong>{{ $contrato->dia_vencimento }}</strong> de cada mês. O valor poderá ser reajustado anualmente, conforme atualização administrativa e índice de correção definido pela CONTRATADA.
    </p>

    <h3>Cláusula 5ª — Do Prazo Contratual</h3>
    <p>
        O presente contrato tem início em {{ $contrato->data_inicio->format('d/m/Y') }}
        @if ($contrato->data_fim) e vigência até {{ $contrato->data_fim->format('d/m/Y') }}. @else , por prazo indeterminado, com renovação automática mensal caso não haja manifestação contrária de qualquer das partes. @endif
    </p>

    <h3>Cláusula 6ª — Das Regras de Uso</h3>
    <p>O CONTRATANTE e seus dependentes comprometem-se a cumprir as regras internas do estabelecimento, zelar pela conservação das instalações e respeito aos demais frequentadores, e seguir todas as normas de segurança e conduta, sob pena de advertência, suspensão ou cancelamento do plano.</p>

    <h3>Cláusula 7ª — Da Rescisão</h3>
    <p>O contrato poderá ser rescindido por descumprimento das cláusulas, atraso superior a 60 dias no pagamento das mensalidades, iniciativa de qualquer das partes mediante aviso prévio de 30 dias, ou por conduta inadequada. Em caso de rescisão, os valores já pagos não serão reembolsáveis, considerando-se os benefícios já usufruídos.</p>

    <h3>Cláusula 8ª — Proteção de Dados (LGPD)</h3>
    <p>Em conformidade com a Lei nº 13.709/2018 (LGPD), a CONTRATADA compromete-se a coletar, armazenar e tratar os dados pessoais do CONTRATANTE e de seus dependentes apenas para finalidades administrativas, contratuais e de segurança, garantindo o sigilo e a proteção das informações, sem compartilhá-los com terceiros exceto quando necessário para execução contratual ou obrigação legal.</p>

    <h3>Cláusula 9ª — Do Foro</h3>
    <p>Fica eleito o foro da comarca de {{ $contrato->unidade->cidade }}/{{ $contrato->unidade->uf }} para dirimir quaisquer dúvidas ou litígios oriundos deste contrato, renunciando as partes a qualquer outro, por mais privilegiado que seja.</p>

    <p style="margin-top: 20px;">{{ $contrato->unidade->cidade }}/{{ $contrato->unidade->uf }}, {{ $contrato->data_inicio->translatedFormat('d \d\e F \d\e Y') }}.</p>

    <div class="assinaturas">
        <div class="bloco">
            <div class="linha-assinatura">{{ $empresa->nome }} — CONTRATADA</div>
        </div>
        <div class="bloco direita">
            <div class="linha-assinatura">{{ $cliente->nome }} — CONTRATANTE</div>
        </div>
        <div class="bloco">
            <div class="linha-assinatura">Testemunha 1</div>
        </div>
        <div class="bloco direita">
            <div class="linha-assinatura">Testemunha 2</div>
        </div>
    </div>

    <p class="rodape">Contrato nº {{ $contrato->numero_contrato }} · Documento gerado automaticamente em {{ $geradoEm->format('d/m/Y H:i') }}.</p>
</body>
</html>
