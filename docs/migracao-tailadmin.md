# Migração visual para TailAdmin

## Escopo concluído

A camada de apresentação foi migrada para um design system inspirado no TailAdmin, mantendo o backend, as rotas e o modelo de dados existentes. A migração cobre o layout autenticado, login, dashboard e os módulos de clientes, contratos, planos, mensalidades, caixas, produtos, vendas, comissões, carteirinhas, acessos, relatórios, unidades, usuários e auditoria.

O relatório financeiro em PDF continua usando HTML e CSS próprios, compatíveis com o DomPDF.

## Stack e decisões

- Tailwind CSS 3 foi mantido para evitar uma troca simultânea do pipeline de CSS. Os componentes visuais do TailAdmin foram adaptados para essa versão.
- Alpine.js 3 controla a sidebar móvel, o overlay, os modais e o seletor de tema.
- O tema escuro usa a estratégia `class` e grava a preferência em `localStorage`.
- A cor principal usa a escala `brand`, baseada em azul, complementada pelas escalas neutras do Tailwind.
- Ícones são SVGs Blade locais, sem biblioteca externa.
- Formulários mantêm os nomes dos campos, ações, métodos HTTP, CSRF, valores antigos e mensagens de validação.

## Componentes compartilhados

Foram disponibilizados componentes Blade para alertas, badges, botões, cards, estado vazio, mensagens de campo, inputs, selects, textareas, tabelas, modais, cabeçalhos de página e ícones de navegação.

O partial global de mensagens aceita as chaves de sessão em português e inglês e mantém a proteção `isset($errors) && $errors->any()`.

## Validação e manutenção

Antes de publicar uma nova versão, execute:

```bash
npm install
npm run build
php artisan view:clear
php artisan view:cache
php artisan route:list
php artisan test
```

As telas devem ser verificadas em desktop e mobile nos temas claro e escuro. O fluxo do PDV merece atenção especial porque mantém JavaScript próprio para adicionar e reindexar itens.

## Pendências não funcionais

- A pasta fornecida não possui metadados Git; por decisão do responsável, não foram criados repositório, branch ou commits.
- Uma validação visual humana ainda é recomendada nos navegadores usados em produção.
