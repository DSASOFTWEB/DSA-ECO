# Regras de domínio e segurança

## Multiempresa e multiunidade

- `Empresa` é o tenant; `Unidade` é uma filial operacional do tenant.
- `BelongsToTenant` registra `TenantScope` e preenche `empresa_id` no contexto autenticado.
- O scope não filtra automaticamente fora de uma sessão autenticada. Jobs, scheduler, webhooks, cadastro e checkout público precisam aplicar o tenant explicitamente.
- `unidade_id` não substitui `empresa_id`. Valide que a unidade pertence à mesma empresa de todas as entidades envolvidas.
- Ao usar `withoutGlobalScope()` ou `withoutGlobalScopes()`, documente o motivo e recoloque filtros explícitos antes de acessar ou alterar dados.
- O `super_admin` recebe autorização global via `Gate::before`; não remova as verificações de tenant das Policies comuns por causa disso.

## Valores e transações

- Recalcule preços, totais, descontos permitidos, comissões e troco no servidor.
- Derive `empresa_id` da entidade raiz confiável: cliente/contrato, vendedor/venda, unidade/tipo de entrada ou mensalidade/pagamento.
- Trave ou revalide registros em concorrência quando estoque, caixa, quarto, webhook ou pagamento puder ser processado simultaneamente.
- Uma falha no meio de contrato, venda, baixa financeira, checkout ou consumo não pode deixar efeitos parciais.

## Contratos, mensalidades e acesso

- Criar contrato coordena plano, cliente, unidade, dependentes, mensalidade e carteirinha; mantenha limites do plano e consistência de tenant.
- Baixa de mensalidade deve ser idempotente, registrar pagamento e respeitar caixa/gateway.
- Cancelamento não deve reescrever histórico financeiro já liquidado; preserve o comportamento atual para mensalidades futuras.
- Acesso depende de carteirinha válida, contrato/beneficiário, inadimplência e unidade/regras aplicáveis. Registre tentativas autorizadas e negadas conforme o fluxo existente.
- QR Codes e vouchers devem usar identificadores/assinaturas gerados no servidor e expiração de `config/parque.php`.

## Vendas, estoque, caixa e hospedagem

- Venda e cancelamento coordenam itens, estoque, caixa, pagamento e comissão dentro de transações.
- Nunca permita estoque negativo sem uma regra explícita do domínio; preserve o histórico de movimentações.
- Operações de caixa pertencem a terminal, unidade, empresa e operador coerentes.
- Reserva/check-in/consumo/checkout devem validar capacidade, disponibilidade, datas, produto e caixa no mesmo tenant.
- Contas a pagar/receber e relatórios devem respeitar tenant, unidade e período; totais financeiros devem vir de consultas do servidor.

## Integrações, webhooks e filas

- Webhooks são públicos, mas não confiáveis: valide assinatura/segredo, associe o evento à empresa e mantenha deduplicação por identificador do provedor.
- Responda rapidamente ao provedor e envie processamento demorado à fila quando o padrão existente assim fizer.
- Retries não podem duplicar baixa, venda, comissão, cobrança ou notificação. Registre tentativas e resultado sem segredos.
- Diferencie erro operacional externo (`IntegrationException`) de violação de regra (`NegocioException`).
- Preserve timeout, retry/backoff e logs centralizados em `RealizaRequisicoesComRetry`.

## Banco e migrations

- Use InnoDB, `utf8mb4` e tipos monetários `DECIMAL`; não use ponto flutuante para dinheiro.
- MySQL trata múltiplos `NULL` como distintos em índices únicos. O SKU é único por `(empresa_id, sku)` inclusive para soft-deleted; Requests devem refletir a constraint.
- Preserve as `CHECK constraints`; MySQL anterior a 8.0.16 não é suportado.
- Migrations publicadas devem evoluir por nova migration, não por edição destrutiva da história, salvo pedido explícito para um banco ainda descartável.
