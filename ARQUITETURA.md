# Arquitetura

Este documento explica as decisões estruturais do projeto: por que o multi-tenant foi
resolvido dessa forma, como as camadas se dividem, os padrões de segurança aplicados e,
principalmente, **o que está completo e o que é esqueleto/placeholder** em cada módulo —
a estratégia escolhida para esta primeira entrega foi montar o "esqueleto de todos os
módulos" (breadth-first) em vez de aprofundar um único módulo até o fim.

## 1. Padrão de camadas: Controller → Service → Repository → Model

Regra seguida em todo o projeto: **Controllers nunca contêm regra de negócio.** Um
Controller só faz três coisas: valida a entrada (via Form Request), chama um Service, e
devolve a resposta (view ou JSON). Toda regra de negócio — cálculo de mensalidade,
validação de limite de dependentes, baixa de estoque, fechamento de caixa, etc. — vive em
`app/Services/`.

```
HTTP Request
   → Form Request (app/Http/Requests/...)   valida entrada e autorização (Policy)
   → Controller (app/Http/Controllers/...)  orquestra, não decide nada de negócio
   → Service (app/Services/...)             regra de negócio, DB::transaction, exceções
   → Repository (app/Repositories/...)      acesso a dados quando há consultas reutilizáveis
   → Model (app/Models/...)                 Eloquent, relacionamentos, casts, tenant scope
```

**Por que Repository além de Service, se o Eloquent já é um repositório?** Porque nem
toda entidade precisa de um: só os cinco módulos com consultas complexas e reutilizáveis
em múltiplos lugares (Cliente, Contrato, Mensalidade, Produto, Venda) têm
`Contracts/*RepositoryInterface.php` + `Eloquent/*Repository.php`. Os demais Services
usam o Eloquent diretamente pelos Models — evita a "repository por decreto" que só
adiciona indireção sem ganho. O binding é automático via
`RepositoryServiceProvider::$bindings` (recurso nativo do Laravel), sem precisar
registrar cada par manualmente.

**Exceções de negócio:** `App\Exceptions\NegocioException` (regra violada — ex.: "caixa
já está fechado") e `App\Exceptions\IntegrationException` (falha de integração externa —
ex.: Evolution API fora do ar). Os Controllers não capturam essas exceções diretamente;
o handler global do Laravel (`bootstrap/app.php`) as converte em respostas HTTP
apropriadas (422 para negócio, 502/erro logado para integração).

## 2. Banco de dados: por que MySQL, e o que mudou na migração do Postgres

O projeto começou no PostgreSQL e foi migrado para **MySQL 8.0.16+ / MariaDB
10.2.1+** a pedido explícito, para alinhar com a infraestrutura de hospedagem já
disponível. Do ponto de vista de regra de negócio, a migração é segura — mas duas
diferenças de dialeto SQL mereceram decisão explícita, documentadas aqui para quem
for revisar o schema:

1. **CHECK constraints continuam existindo e sendo aplicadas.** As 5 regras
   (`contratos.dia_vencimento BETWEEN 1 AND 31`, `tipo IN ('entrada','saida')` em
   `acessos`/`caixa_movimentacoes`, `venda_itens.quantidade > 0`,
   `comissoes` com origem obrigatória, `carteirinhas` com titular exclusivo) usam
   sintaxe `ALTER TABLE ... ADD CONSTRAINT ... CHECK (...)` idêntica em Postgres e
   MySQL — não precisaram ser reescritas. O único cuidado é de versão: o MySQL só
   passou a **aplicar de fato** CHECK constraints a partir da 8.0.16 (antes disso a
   sintaxe é aceita e a constraint é **silenciosamente ignorada**, o que romperia a
   regra sem avisar ninguém). Por isso a versão mínima é requisito, não sugestão —
   ver README.md.
2. **O índice único parcial de `produtos.sku` não tem equivalente no MySQL.** No
   Postgres, `CREATE UNIQUE INDEX ... WHERE sku IS NOT NULL AND deleted_at IS NULL`
   garantia unicidade de SKU por empresa apenas entre produtos ativos (não
   soft-deleted). MySQL não suporta índice parcial. A migration
   (`2024_01_12_000001_create_produtos_vendas_tables.php`) agora cria um índice único
   "cheio" em `(empresa_id, sku)`. Duas consequências, ambas aceitas conscientemente:
   - SKUs `NULL` continuam permitidos em qualquer quantidade — MySQL, assim como o
     Postgres, trata cada `NULL` como distinto dentro de um índice único, então isso
     não mudou.
   - Um SKU usado por um produto **soft-deleted** fica reservado até o produto ser
     restaurado, ter o SKU limpo, ou ser excluído em definitivo (`forceDelete`) — no
     Postgres esse SKU ficava livre imediatamente após o soft delete. Para que isso
     apareça como erro de formulário (e não como `QueryException` de integridade),
     `StoreProdutoRequest`/`UpdateProdutoRequest` replicam a mesma regra via
     `Rule::unique('produtos', 'sku')->where('empresa_id', ...)`.

Fora esses dois pontos, o restante do schema (chaves estrangeiras, `softDeletes()`,
`nullableMorphs()`, colunas `DECIMAL(15,2)`, índices simples) é Blueprint padrão do
Laravel e roda igual nos dois bancos. Buscas textuais (`ClienteRepository`,
`ProdutoRepository`, `ProdutoController`) trocaram `ilike` (exclusivo do Postgres) por
`like` — no MySQL a collation `utf8mb4_unicode_ci` (configurada em
`config/database.php`/`.env.example`) já torna `LIKE` case-insensitive por padrão,
então não houve perda de comportamento.

**Validação:** como o Composer segue bloqueado neste ambiente (ver README.md), as
migrations foram novamente traduzidas para SQL puro e executadas contra uma instância
real de MariaDB 10.11 — incluindo teste funcional das 5 CHECK constraints (inserção
inválida rejeitada, válida aceita) e do novo índice único de `produtos` (múltiplos
`NULL` aceitos, SKU duplicado rejeitado com `ERROR 1062`).

## 3. Multi-tenancy (SaaS multiempresa)

Modelo escolhido: **banco único compartilhado**, isolado por coluna `empresa_id` — não
banco-por-tenant nem schema-por-tenant. Essa é a abordagem padrão para SaaS B2B de porte
médio: mais barata de operar (um único banco para migrar, monitorar e fazer backup) e
suficiente para o volume esperado de um sistema de gestão de parques aquáticos. O
trade-off consciente é que o isolamento depende de disciplina de código (mitigado pelas
camadas abaixo) em vez de uma fronteira física de banco.

Hierarquia: `empresas` (o tenant/cliente do SaaS) → `unidades` (as filiais/parques
físicos daquela empresa). Clientes, contratos, produtos etc. pertencem a uma `empresa_id`
e, quando fizer sentido operacionalmente (produtos, caixas, carteirinhas), também a uma
`unidade_id`.

Isolamento em duas camadas:

1. **`TenantScope`** (`app/Models/Scopes/TenantScope.php`) — um Global Scope Eloquent
   aplicado via a trait `BelongsToTenant` (`app/Models/Concerns/BelongsToTenant.php`) em
   todo model que tem `empresa_id`. Toda query feita através do Eloquent é filtrada
   automaticamente pela empresa do usuário autenticado, e o `empresa_id` é preenchido
   sozinho na criação. Isso cobre o caso comum (99% do código) sem que cada Controller
   precise lembrar de filtrar por tenant.
2. **Validação explícita nos pontos onde o Scope não alcança** — Form Requests e Jobs
   rodam fora do contexto HTTP autenticado (ou recebem IDs arbitrários do formulário), e
   uma regra `exists:tabela,id` simples do Laravel **não respeita Global Scopes**. Por
   isso, nos formulários que aceitam referência cruzada a outras entidades (ex.:
   `StoreContratoRequest` recebendo `cliente_id`, `plano_id`, `unidade_id`,
   `vendedor_id`), a validação usa `Rule::exists('tabela', 'id')->where('empresa_id', ...)`
   explicitamente, e o `ContratoService::contratar()` faz uma segunda checagem em
   memória confirmando que cliente, plano e unidade pertencem à mesma empresa antes de
   gravar — defesa em profundidade contra um usuário malicioso enviando o ID de outra
   empresa no payload (IDOR).

**Superadmin:** o gate `Gate::before` em `AuthServiceProvider` dá bypass total de
autorização para o papel `super_admin` — é o papel operacional da equipe do SaaS, não de
um cliente-tenant, então intencionalmente ele ignora o `TenantScope` quando necessário
(suporte, auditoria entre empresas).

## 4. Autenticação e autorização

- **Sessão (painel admin)** via guard padrão do Laravel — login por e-mail/senha
  (`app/Http/Controllers/Auth/LoginController.php`).
- **Token (API/app mobile)** via **Laravel Sanctum** — `App\Http\Controllers\Api\AuthController`
  emite tokens pessoais; as rotas de API (`routes/api.php`) exigem `auth:sanctum`.
- **Papéis e permissões**: Spatie Laravel Permission (`roles`, `permissions`,
  `RolesAndPermissionsSeeder`). Papéis pensados: `super_admin`, `admin` (dono da
  empresa), `gerente` (unidade), `operador` (recepção/catraca), `vendedor`.
- **Policies nativas do Laravel** (`app/Policies/`) para cada model relevante — 12 ao
  todo — checadas via `$this->authorize(...)` nos Controllers/Form Requests, e registradas
  em `AuthServiceProvider::$policies`.
- **CSRF**: proteção padrão do Laravel em todos os formulários Blade (`@csrf`); as rotas
  de webhook (Mercado Pago, Evolution API) são isentas via configuração no
  `bootstrap/app.php` porque são chamadas server-to-server autenticadas por assinatura,
  não por sessão de navegador.
- **XSS**: Blade escapa por padrão (`{{ }}`); nenhuma view usa `{!! !!}` com dado vindo
  do usuário sem sanitização.
- **SQL Injection**: 100% do acesso a dados via Eloquent/Query Builder com bindings
  parametrizados; os poucos `DB::statement()` usados nas migrations (CHECK constraints)
  não interpolam dado de usuário — são DDL fixo.

## 5. Dependências e política de segurança do Composer

O Composer 2.9+ bloqueia por padrão a instalação de qualquer versão de pacote com
advisory de segurança conhecido e sem correção disponível dentro do range pedido —
isso pegou este projeto: `composer install` passou a falhar porque **o Laravel 11
saiu do período de suporte de segurança em 12/03/2026**, e duas vulnerabilidades
divulgadas depois disso (CRLF injection na regra de validação `email`, e um path
confusion em URLs assinadas) só foram corrigidas no Laravel 12.60+/13.10+ — nunca na
branch 11.x. Como `laravel/framework` está travado em `^11.31` (ver seção 14 sobre um
possível upgrade futuro), toda a faixa instalável ficou bloqueada de uma vez.

Em vez de desligar a proteção inteira (`config.policy.advisories.block: false`, que
também pararia de barrar advisories FUTUROS com correção disponível), o
`composer.json` ignora — com justificativa registrada por ID — só os 3 advisories que
realmente não têm correção na 11.x:

- **PKSA-mdq4-51ck-6kdq** / **PKSA-3r5d-mb8f-1qw9** (CVE-2026-48019, mesmo problema
  reportado sob dois IDs do Packagist): verificado no código — a regra `email` é usada
  em `StoreClienteRequest`/`UpdateClienteRequest`/`StoreUserRequest`, mas o projeto
  não tem nenhum `Mail::`/`MailMessage`/canal `mail` de Notification (a única
  Notification do projeto, `CobrancaMensalidadeNotification`, só usa
  `WhatsappChannel`) — o e-mail validado nunca vira cabeçalho de e-mail de saída, então
  a exposição prática é nula hoje.
- **PKSA-m5cs-t1y6-qpcs** (signed URL path confusion): verificado — o projeto não usa
  `URL::temporarySignedRoute`/`signed` middleware em lugar nenhum.

Os outros 3 advisories que o Composer também reportou (`PKSA-8qx3-n5y5-vvnd` — file
validation bypass, `PKSA-q46n-4fdk-zjr4`/`PKSA-qzrn-rnz3-85w1` — XSS refletido em
página de erro com debug ligado) **têm correção dentro da própria 11.x** (a partir de
11.44.1 e 11.36.0 respectivamente) — o Composer resolve normalmente para uma versão
acima desse patch sem precisar de nenhuma exceção.

Isso é uma correção pontual, não uma solução definitiva: o problema de fundo é que o
projeto está numa versão do Laravel fora do período de suporte de segurança. Ver
seção 14 (Próximos passos) sobre avaliar o upgrade para Laravel 12.x.

## 6. Multi-unidade e app mobile

- **Multi-unidade**: já é o desenho padrão (`unidades` como filial de `empresas`).
  Caixa, produto/estoque e carteirinha/acesso são todos escopados por `unidade_id`, então
  duas unidades da mesma empresa têm caixas e estoques independentes por padrão.
- **App mobile**: a API (`routes/api.php`, `app/Http/Controllers/Api/`) já expõe
  autenticação por token (Sanctum), consulta de cliente e validação de carteirinha/acesso
  via `App\Http\Resources\*Resource`, prontas para consumo por um app Flutter/React
  Native futuro sem depender do painel web.

## 7. Integrações externas

Ambas as integrações seguem o mesmo padrão em `app/Services/Integrations/`:

- **`Concerns/RealizaRequisicoesComRetry.php`**: trait reutilizável com timeout, retry
  com backoff exponencial e log estruturado de toda chamada HTTP de saída (Guzzle). Isso
  evita duplicar lógica de resiliência em cada Service de integração.
- **`EvolutionApiService.php`** (WhatsApp): usado pelo canal de notificação customizado
  `App\Notifications\Channels\WhatsappChannel.php`, disparado de forma assíncrona pelo
  `App\Jobs\EnviarCobrancaWhatsappJob.php` (fila) — nunca síncrono numa request HTTP, para
  não travar a UI do operador esperando resposta de uma API externa.
- **`MercadoPagoService.php`** (Pix/pagamento): gera cobrança Pix para uma mensalidade;
  o webhook de confirmação de pagamento chega em
  `App\Http\Controllers\Webhooks\MercadoPagoWebhookController`, que **valida a
  assinatura HMAC-SHA256** (`x-signature`, manifesto `id:{data.id};request-id:{...};ts:{...};`)
  antes de aceitar o payload, grava o payload bruto em `webhooks_mercadopago` (auditoria/
  idempotência) e delega o processamento a `App\Jobs\ProcessarWebhookMercadoPagoJob`
  (fila) — o endpoint apenas confirma recebimento rápido (200) e devolve o processamento
  pesado para background, como o Mercado Pago espera de um webhook.

## 8. Financeiro

- Todo campo monetário é `DECIMAL(15,2)` no banco (nunca `FLOAT`/`DOUBLE`, que têm erro
  de arredondamento binário) e `decimal:2` no cast do Eloquent.
- Toda operação que grava mais de uma tabela financeira (abrir/fechar caixa, marcar
  mensalidade como paga, registrar venda com baixa de estoque e comissão) roda dentro de
  `DB::transaction()` — falha parcial nunca deixa o sistema em estado inconsistente
  (ex.: venda registrada sem baixar estoque).
- **Concorrência**: baixa de estoque (`EstoqueService`) usa `lockForUpdate()` (lock
  pessimista) dentro da transação e **revalida a quantidade disponível depois de travar
  o registro** — a checagem "tem estoque?" feita antes de entrar na transação é só uma
  falha rápida para o caso comum; sem a segunda checagem sob lock, duas vendas
  simultâneas do último item em estoque poderiam passar as duas e deixar
  `estoque_atual` negativo.
- Fechamento de caixa calcula o saldo esperado pelo sistema (abertura + entradas −
  saídas) e registra a diferença contra o valor informado pelo operador (sobra/falta).

## 9. Performance

- Eager loading (`with(...)`) usado nas listagens que exibem dados de relacionamento
  (ex.: lista de contratos carregando cliente/plano) para evitar N+1.
- Índices em toda coluna usada em `WHERE`/`JOIN` recorrente: `empresa_id`, `unidade_id`,
  chaves estrangeiras, `status`, `data_vencimento`. Colunas opcionalmente únicas e
  nulas (`carteirinhas.codigo`, `contratos.numero_contrato`) usam índice único comum —
  tanto MySQL quanto o Postgres anterior tratam cada `NULL` como distinto, então
  múltiplos registros sem valor continuam permitidos sem precisar de índice parcial.
- Busca textual (nome de cliente, produto) usa `LIKE` — no Postgres a mesma busca
  precisava de `ILIKE` para ser case-insensitive; no MySQL isso já é automático com a
  collation `utf8mb4_unicode_ci` configurada em `config/database.php`/`.env.example`,
  então `LIKE` comum já ignora maiúsculas/minúsculas sem precisar de `LOWER()` nos dois
  lados (o que impediria o uso de índice).

## 10. Jobs, filas e scheduler

- **Jobs** (`app/Jobs/`): `EnviarCobrancaWhatsappJob` e `ProcessarWebhookMercadoPagoJob`
  implementam `ShouldQueue` com `$tries` e `$backoff` configurados — qualquer chamada de
  rede lenta ou potencialmente instável roda em background, nunca bloqueando uma request
  do usuário.
- **Scheduler** (`routes/console.php`), todos com `withoutOverlapping()->onOneServer()`
  para segurança em ambiente com mais de um worker:
  - `GerarMensalidadesCommand` — gera a mensalidade do próximo ciclo para contratos ativos.
  - `AplicarAtrasosMensalidadesCommand` — marca mensalidades vencidas como atrasadas.
  - `EnviarCobrancasCommand` — dispara `EnviarCobrancaWhatsappJob` para inadimplentes.
  - `ProcessarInadimplenciaCommand` — aplica a regra de bloqueio de acesso por atraso.
  - `ExpirarCarteirinhasCommand` — expira carteirinhas vencidas.

## 11. Auditoria

`spatie/laravel-activitylog` via a trait `LogsActivity` nos models sensíveis
(Cliente, Contrato, Mensalidade, Pagamento, Caixa, Usuário, etc.), configurando
`getActivitylogOptions()` para logar apenas os campos relevantes (`logOnlyDirty()`) e
evitar ruído. A tela `auditoria.index` (Policy `AuditoriaPolicy`, gate
`auditoria.visualizar`) lista os eventos para o admin da empresa.

## 12. O que está completo x o que é esqueleto (por módulo)

A estratégia acordada para esta entrega foi **esqueleto de todos os módulos**: toda a
espinha dorsal (migration, model, Policy, Service com a regra de negócio central,
rotas, e ao menos as telas essenciais) existe e está coerente ponta a ponta para cada
módulo listado no escopo original. Isso é diferente de ter cada módulo 100% completo em
funcionalidades acessórias — o quadro abaixo é explícito sobre a diferença.

| Módulo | Back-end (Model/Service/Policy) | Rotas/Controller | Views | Status |
|---|---|---|---|---|
| Clientes + dependentes | Completo | CRUD completo | CRUD completo | **Completo** |
| Planos | Completo | CRUD (sem `show`, não necessário) | CRUD | **Completo** |
| Contratos | Completo (contratar/editar/cancelar, caução, primeiro vencimento flexível, prorrogação e limite de dependentes) | CRUD do fluxo principal + cancelar/reativar/prorrogar | index/create/show/edit/prorrogar | **Completo** no fluxo principal; pagamentos liquidados são preservados nas edições |
| Mensalidades | Completo (caução separada, geração recorrente, marcar como paga, idempotência) | index/show + baixa manual + gerar Pix | index/show | **Completo** no fluxo principal |
| Cobrança WhatsApp | Completo (Service, Job, Notification, canal customizado) | acionado via scheduler/Job, sem tela dedicada | — | **Esqueleto funcional**: dispara de verdade dado `EVOLUTION_API_URL`/`EVOLUTION_API_KEY` válidos; não há tela de histórico de envios (fica registrado só via log/activity log) |
| Pagamentos Mercado Pago/Pix | Completo (geração de cobrança, webhook com validação de assinatura, Job de processamento) | endpoint de webhook + botão "gerar Pix" na mensalidade | reaproveita a tela de mensalidade | **Esqueleto funcional**: depende de `MERCADOPAGO_ACCESS_TOKEN` válido para chamadas reais; sandbox de testes do Mercado Pago não foi exercitada neste ambiente |
| Carteirinha QR Code | Completo (emissão automática ao contratar, para titular e dependente, geração de imagem QR) | index/show + qrcode + bloquear/desbloquear | index/show | **Completo** |
| Controle de acesso (catraca) | Completo (`AcessoService::validarEEntrar`, cobre carteirinha inválida/bloqueada/inadimplente/dependente) | endpoint de API para o leitor + tela de histórico | index | **Completo** no fluxo principal; não há integração com hardware de catraca específico (a API está pronta para qualquer leitor que faça um POST) |
| Inadimplência | Completo (`InadimplenciaService`, bloqueio automático de acesso via scheduler) | acionado via scheduler | reaproveita telas de mensalidade/carteirinha | **Completo** na regra; sem uma tela dedicada de "painel de inadimplência" separada |
| Financeiro/Caixa | Completo (abrir/movimentar/fechar, cálculo de diferença) | CRUD parcial (index/show) + ações | index/show | **Completo** |
| Produtos/Estoque | Completo (entrada/saída/ajuste, lock pessimista, baixa por venda) | CRUD completo + ajuste de estoque | CRUD completo | **Completo** |
| Vendas | Completo (baixa de estoque, geração de comissão) | index/create/store/show + cancelar | index/create/show | **Completo** no fluxo principal |
| Vendedores/Comissões | Completo (`ComissaoService`, cálculo e marcação como pago) | index + pagar | index | **Completo** no fluxo principal; sem tela de configuração de percentual de comissão por vendedor (hoje é um valor único em `config/parque.php`) |
| Dashboard | Parcial | 1 rota | 1 view | **Esqueleto**: mostra indicadores básicos (contratos ativos, inadimplência, caixa do dia); ainda não tem gráficos nem filtro de período |
| Relatórios PDF/Excel | Completo o fluxo financeiro | 3 rotas | 1 view + template PDF | **Esqueleto parcial**: relatório financeiro (PDF e Excel) implementado ponta a ponta; relatórios de outros módulos (ex.: vendas por vendedor, acessos por período) ainda não têm exportação própria — reaproveitam as telas de listagem |
| Usuários/Permissões | Completo (Policies, Spatie roles) | CRUD completo | CRUD completo | **Completo** |
| Unidades | Completo | index/create/store/edit/update (sem exclusão, por segurança — unidade com histórico não deve ser removida) | CRUD | **Completo** |
| Auditoria | Completo (Activitylog) | 1 rota | 1 view | **Completo** para visualização; sem filtro avançado (por usuário/período) na tela ainda |
| API mobile | Parcial | Auth, Cliente, Acesso | — (API, sem view) | **Esqueleto**: autenticação e os dois endpoints mais críticos (consulta de cliente, validação de acesso) prontos; os demais módulos ainda não têm endpoint de API — hoje são web-only |
| Integrações fiscais (NF-e/NFC-e / NFS-e Nacional) | Emissão hospedagem | Emitente na empresa; NFC-e consumos + NFS-e diárias/serviços; persistência de chave/recibo/protocolo + XML no banco e em `storage/app/private/fiscal/...`; tabela `cidades` (API IBGE) e NCM (Siscomex) | Ajustes de schema SEFAZ/RTC e DANFSe PDF | Homologar com certificado real |

## 13. Testes

Dois testes de feature (`tests/Feature/`) cobrem os dois fluxos mais críticos do
sistema — financeiro (contratação → mensalidade → pagamento → cancelamento) e controle
de acesso (catraca) — como uma base que ainda precisa crescer. **Ainda não há testes**
para: estoque/concorrência, caixa, vendas/comissão, webhook do Mercado Pago (incluindo
validação de assinatura), isolamento entre tenants e Policies. Antes de ir para produção,
esses são os próximos testes a escrever, nessa ordem de prioridade (maior risco
financeiro/segurança primeiro).

## 14. Módulo Food: mesas e comandas

O módulo em `app/Services/Food` porta as regras operacionais do ZeusFOOD para a
arquitetura multiempresa do SaaS. `pontos_atendimento` unifica mesa e comanda sem
misturar seus mapas; cada ponto pode ter um `atendimento` aberto ou pré-fechado.
Itens preservam preço praticado, desconto, observação e cancelamento auditável.

A transferência para um ponto livre move integralmente o atendimento. Para um
ponto ocupado, os itens ativos são unidos à conta de destino e a origem é encerrada
como transferida. O fechamento é transacional: valida caixa e pagamentos, cria a
`Venda`, baixa estoque, registra `Pagamento`/`CaixaMovimentacao`, calcula comissão e
só então libera o ponto. A pré-conta pode ser impressa em HTML ou ESC/POS sem fechar
ou movimentar estoque/caixa.

Arquivos de entrada: `FoodController`, `AtendimentoService`, models
`PontoAtendimento`/`Atendimento`/`AtendimentoItem`, views `resources/views/food` e
rotas nomeadas `food.*` em `routes/web.php`.

Cadastro em faixa de mesas/comandas fica em **Minha Empresa** (`/empresa`), não no
mapa. O mapa (`food/mapa`) é só operação. A tela de lançamento (`food/atendimento`)
usa layout touch (categorias + grade + painel do pedido), referência ZeusFOOD.

## 15. Próximos passos sugeridos

1. Validar o schema real: `docker compose up -d --build` (ver README.md, seção "Subir
   com Docker") faz isso sozinho — build, `migrate`, seed e `php artisan test` ficam
   disponíveis via `docker compose exec app ...` sem precisar instalar nada na
   máquina. Sem Docker, o caminho equivalente é `composer install` +
   `php artisan migrate --seed` manuais.
2. Ampliar a cobertura de testes conforme a seção 13.
3. Tela de configuração de comissão por vendedor (hoje fixa em `config/parque.php`).
4. Dashboard com gráficos e filtro de período.
5. Exportação (PDF/Excel) para os demais relatórios além do financeiro.
6. Endpoints de API adicionais para o app mobile (hoje só auth/cliente/acesso).
7. Integração fiscal (NF-e/NFC-e para venda de produtos) como novo Service dedicado.
8. Testar de ponta a ponta a integração real com Evolution API e o ambiente de sandbox
   do Mercado Pago antes de qualquer cobrança em produção.
9. **Avaliar o upgrade para Laravel 12.x.** O Laravel 11 saiu do suporte de segurança
   em 12/03/2026 (ver seção 5) — as 3 exceções abertas no `composer.json` cobrem o que
   é conhecido *hoje*, mas qualquer advisory novo sem correção na 11.x vai voltar a
   travar o `composer install`/`update`. Upgrade de major version do Laravel é trabalho
   real (breaking changes a testar em todos os Services/Controllers), não algo pra
   fazer de afogadilho — mas é a única forma de sair definitivamente dessa situação.
