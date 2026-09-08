# Prompt para Claude Code — Migração de UI para TailAdmin (Tailwind CSS)

> Cole este prompt inteiro no Claude Code, na raiz do projeto `parque-aquatico-saas`.

---

## Contexto do projeto

Este é um sistema ERP em Laravel (`laravel/framework ^11.31`, PHP 8.3, rodando via
Docker: PHP-Apache + MySQL, com estágio de build separado pra assets via
Node/Vite). O front-end hoje é **Blade puro com Bootstrap "cru"** (sem
componentização, classes escritas na mão, sem design system). O objetivo é
migrar a UI inteira para o padrão visual do **TailAdmin Laravel**
(https://github.com/tailadmin/tailadmin-laravel — Tailwind CSS v4 + Alpine.js +
Vite + Blade), **sem tocar em nenhuma regra de negócio**.

O projeto já tem ~55 rotas nomeadas cobrindo: dashboard, clientes, contratos,
planos, mensalidades, caixas, vendas (PDV), produtos/estoque, comissões,
carteirinhas, controle de acesso, relatórios (PDF/Excel), unidades, usuários e
auditoria. Multi-tenant não se aplica aqui (é single-tenant por unidade/franquia
via `unidade_id`).

## Objetivo

Refatorar **apenas a camada de apresentação** (`resources/views/`,
`resources/css/`, `resources/js/`) para adotar o design system do TailAdmin
Laravel, mantendo 100% das rotas, controllers, models, migrations, seeders,
policies/permissions e regras de negócio intactas.

## Restrições obrigatórias — leia antes de começar

1. **NÃO altere nenhum arquivo dentro de**: `app/Http/Controllers/`,
   `app/Models/`, `app/Services/`, `database/migrations/`,
   `database/seeders/`, `routes/`. A migração é 100% de front-end.
2. **NÃO renomeie nem remova nenhuma rota nomeada** (`clientes.show`,
   `contratos.cancelar`, etc.) — as views novas devem continuar usando as
   mesmas rotas via `route()`.
3. **NÃO quebre nenhum formulário existente** — todo `<form>` migrado precisa
   manter os mesmos `name` de input, o mesmo método HTTP (via `@method`), e o
   mesmo `@csrf`, pra não quebrar validação/FormRequest no backend.
4. **Trabalhe em branch própria** (`git checkout -b feature/migracao-tailadmin`)
   e faça commits pequenos e atômicos, um por tela ou por componente
   compartilhado — nunca um commit gigante trocando tudo de uma vez.
5. **Migração incremental, não big-bang**: comece pelo layout base
   (sidebar/header/estrutura), depois um módulo simples por vez (sugestão de
   ordem: Dashboard → Clientes → Contratos → Planos → o resto), validando cada
   módulo antes de seguir pro próximo.
6. **Preserve toda mensagem flash e validação de erro** (`session('success')`,
   `$errors->any()`, etc.) — só troque o estilo visual delas, não a lógica.
   Atenção especial: existe um bug já corrigido em
   `resources/views/partials/flash.blade.php` que usava `$errors->any()` sem
   checar `isset($errors)` — ao recriar esse partial no novo layout, mantenha
   a checagem defensiva `@if (isset($errors) && $errors->any())`.
7. Sempre que uma view usar dado calculado (`RelatorioService`, contagens,
   somas), preserve exatamente a variável passada pelo controller — não
   invente novo cálculo na view.

## Stack técnica alvo

- Tailwind CSS v4 (confirme a versão atual em `package.json` antes de decidir
  se migra de v3→v4 ou mantém v3 adaptando os componentes do TailAdmin)
- Alpine.js para interatividade (dropdown, sidebar toggle, dark mode, modais)
- Vite como bundler (o projeto já usa Vite — reaproveite a config existente)
- Blade Components (`resources/views/components/`) para elementos reutilizáveis
  (card, botão, badge de status, tabela, paginação, modal, input, select)

## Plano de execução

### Fase 0 — Preparação
1. Confirme a versão atual do Tailwind/Vite no `package.json`.
2. Clone `https://github.com/tailadmin/tailadmin-laravel` numa pasta temporária
   fora do projeto (ex: `../tailadmin-referencia`), só para servir de
   referência de código — não é uma dependência a instalar via Composer.
3. Instale o Alpine.js: `npm install alpinejs`.
4. Liste todas as views atuais em `resources/views/` e mapeie cada uma para o
   módulo/rota correspondente, para termos um checklist de migração.

### Fase 1 — Layout base
1. Crie/reescreva `resources/views/layouts/app.blade.php` com a estrutura do
   TailAdmin (sidebar + header + área de conteúdo + slot), adaptando o menu
   lateral para os itens que já existem hoje (Dashboard, Clientes, Contratos,
   Planos, Mensalidades, Caixa, Vendas (PDV), Estoque, Comissões,
   Carteirinhas, Controle de Acesso, Relatórios, Unidades, Usuários,
   Auditoria), preservando os `@can`/checagens de permissão que já existirem
   em cada item de menu.
2. Recrie `resources/views/partials/flash.blade.php` com o estilo visual do
   TailAdmin, respeitando a restrição 6 acima.
3. Extraia componentes Blade reutilizáveis: `<x-card>`, `<x-badge>`,
   `<x-button>`, `<x-table>`, `<x-modal>`, `<x-input>`, `<x-select>`.
4. Rode `npm run build` e valide visualmente o layout vazio antes de seguir.

### Fase 2 — Migração módulo a módulo
Para cada módulo (na ordem sugerida acima):
1. Reescreva as views do módulo usando o novo layout e os componentes criados
   na Fase 1.
2. Rode `php artisan view:clear` após cada alteração.
3. Teste manualmente (ou via `php artisan route:list` + requisição de teste)
   que a tela carrega sem erro 500 e que os formulários submetem corretamente.
4. Faça commit isolado desse módulo antes de seguir para o próximo.

### Fase 3 — Validação final
1. Rode um checklist final passando por todas as ~55 rotas nomeadas,
   confirmando 200/302 esperado (nunca 500).
2. Confirme que `APP_DEBUG` está `false` no ambiente de build final.
3. Gere um resumo (markdown) do que foi migrado, o que ficou pendente, e
   qualquer decisão de design tomada (ex: paleta de cores escolhida,
   convenções de nomeação de componentes) para documentar no `README.md` ou
   num `docs/migracao-tailadmin.md`.

## Critérios de aceite

- Nenhuma rota quebrada (nenhum 500 no checklist da Fase 3).
- Nenhuma mudança em `app/`, `database/`, `routes/`.
- Todas as telas usando o mesmo layout base e os mesmos componentes
  compartilhados (nada de CSS duplicado tela a tela).
- Dark mode funcionando (recurso nativo do TailAdmin) em todas as telas
  migradas.
- Commits organizados por módulo, com mensagens descritivas em português,
  seguindo o padrão que o resto do projeto já usa.

## Como você deve trabalhar

Antes de codar, apresente o plano de migração módulo a módulo (ordem, o que
será extraído como componente compartilhado) para eu confirmar. Depois disso,
execute fase por fase, sempre parando ao final de cada módulo da Fase 2 para
eu validar visualmente antes de seguir para o próximo.