---
name: parque-aquatico-saas
description: Evoluir, corrigir, testar e manter o repositório Parque Aquático SaaS em Laravel/Blade, incluindo multiempresa, clientes, contratos, mensalidades, acesso, PDV, estoque, financeiro, hospedagem, integrações, filas, Docker e interface Tailwind. Use ao trabalhar em D:\PROGRAMACAO\CLIENTES\DSA\parque-aquatico-saas. Não use para outros projetos Laravel.
---

# Parque Aquático SaaS

## Primeira leitura

1. Confirme que o workspace contém `composer.json` com o pacote `dsa/parque-aquatico-saas`.
2. Leia `README.md` para instalação e operação e `ARQUITETURA.md` para decisões de design e estado real dos módulos.
3. Identifique o domínio afetado e leia somente as referências pertinentes abaixo.
4. Antes de editar, inspecione o fluxo completo já existente: rota, Request, Controller, Policy, Service, Repository quando houver, Model, migration, view/API Resource e testes.

## Invariantes

- Preserve o isolamento multiempresa por `empresa_id`. Models do tenant devem usar `BelongsToTenant`; Policies também devem conferir a empresa do recurso.
- Em requisições autenticadas, `TenantScope` usa `Auth::hasUser()`. Não troque por `Auth::check()` ou `Auth::user()` dentro do scope, pois isso pode causar recursão durante a autenticação.
- Jobs, comandos, webhooks e fluxos públicos não têm necessariamente usuário autenticado; filtre `empresa_id` explicitamente e obtenha o tenant de uma fonte confiável.
- Nunca aceite `empresa_id`, preço, total, comissão ou status financeiro sensível como fonte de verdade do cliente. Derive esses valores de entidades já validadas no servidor.
- Mantenha regra de negócio em Services, validação/autorização em Form Requests e Policies, e Controllers finos.
- Use `DB::transaction()` em operações que alteram mais de um agregado ou combinam financeiro, estoque, caixa, acesso ou hospedagem.
- Preserve idempotência em pagamentos, mensalidades, webhooks e processamentos repetíveis de fila/scheduler.
- Não registre nem exponha tokens do Mercado Pago, chaves da Evolution API, segredos de webhook, senhas, payloads sensíveis ou dados completos de pagamento.
- Preserve a ordem das tarefas em `routes/console.php`; geração, atrasos, cobrança e bloqueios possuem dependências temporais.
- O banco suportado é MySQL 8.0.16+ ou MariaDB 10.2.1+. Não adapte testes para SQLite: as migrations dependem de `CHECK` via `ALTER TABLE`.

## Padrão de implementação

- Fluxo preferido: `Route -> FormRequest -> Controller -> Service -> Repository (somente quando útil) -> Model`.
- Repositórios existem deliberadamente apenas para Cliente, Contrato, Mensalidade, Produto e Venda. Não crie uma camada Repository por obrigação.
- Use `NegocioException` para violações esperadas de domínio e `IntegrationException` para falhas externas tratáveis pelo handler global.
- Para referências entre tenants em validações, restrinja `Rule::exists()` e `Rule::unique()` por `empresa_id`.
- Autorize tanto ações de classe (`viewAny`, `create`) quanto recursos concretos (`view`, `update`, `delete`) conforme o padrão local.
- No frontend, reutilize layouts e componentes Blade em `resources/views/components`; mantenha Tailwind/Alpine/Vite em vez de introduzir outro framework sem pedido explícito.
- Se alterar contratos HTTP, rotas públicas, permissões, scheduler, schema ou variáveis de ambiente, atualize a documentação correspondente.

## Referências por tarefa

- Leia [references/project-map.md](references/project-map.md) ao localizar arquivos, entender camadas ou adicionar um módulo.
- Leia [references/domain-rules.md](references/domain-rules.md) para multi-tenancy, autenticação, pagamentos, acesso, vendas, hospedagem, webhooks ou rotinas agendadas.
- Leia [references/commands.md](references/commands.md) antes de executar instalação, build, testes, análise estática ou operações Docker.
- Para desenho detalhado e estado completo versus placeholder, prefira o documento vivo `ARQUITETURA.md` do repositório.

## Conclusão da tarefa

Execute as validações proporcionais ao risco. Mudanças de domínio devem ter testes Feature cobrindo caminho feliz, autorização, isolamento entre empresas e falhas relevantes. Informe claramente o que foi validado e qualquer etapa que não pôde ser executada.
