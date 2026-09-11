# Mapa do projeto

## Stack e pontos de entrada

- PHP 8.2+, Laravel 11, Eloquent, Sanctum, Spatie Permission e Activitylog.
- Blade + Tailwind CSS + Alpine.js; Vite compila `resources/css/app.css` e `resources/js/app.js`.
- MySQL 8.0.16+; fila `database`; Docker Compose executa app, queue, scheduler, db e phpMyAdmin.
- `routes/web.php`: painel, autenticação, checkout/cadastro público e rotas administrativas.
- `routes/api.php`: webhooks públicos e API `/api/v1` autenticada com Sanctum.
- `routes/console.php`: agenda diária de mensalidades, cobrança, inadimplência, financeiro e carteirinhas.
- `bootstrap/app.php`: middleware e tratamento global de exceções.

## Camadas

- `app/Http/Controllers/Admin`: painel web autenticado.
- `app/Http/Controllers/Api`: API para app móvel e terminal de acesso.
- `app/Http/Controllers/Auth`: sessão e redefinição de senha.
- `app/Http/Controllers/Webhooks`: entradas de Mercado Pago e Evolution API.
- `app/Http/Requests`: validação e autorização; regras de relacionamentos devem incluir o tenant.
- `app/Http/Resources`: serialização da API.
- `app/Services`: regras de negócio e transações.
- `app/Repositories/Contracts` e `Eloquent`: consultas reutilizáveis de Cliente, Contrato, Mensalidade, Produto e Venda; bindings em `RepositoryServiceProvider`.
- `app/Models`: relacionamentos, casts, scopes e estados persistidos.
- `app/Policies`: permissões Spatie combinadas com propriedade do tenant.
- `app/Jobs` e `app/Notifications`: processamento assíncrono e WhatsApp.
- `app/Services/Integrations`: Mercado Pago, Evolution API e retry/backoff HTTP.
- `app/Services/Relatorios`: exportações PDF/Excel e vouchers.
- `app/Services/Impressao`: geração de cupom ESC/POS.

## Domínios e arquivos centrais

| Domínio | Arquivos centrais |
|---|---|
| Empresa e unidade | `Empresa`, `Unidade`, `EmpresaService`, controllers e policies correspondentes |
| Usuários e acesso | `User`, `RolesAndPermissionsSeeder`, `AuthServiceProvider`, `UserPolicy` |
| Clientes e dependentes | `Cliente`, `Dependente`, `ClienteService`, `ClienteRepository`, policies e Requests |
| Planos e contratos | `Plano`, `Contrato`, `ContratoService`, `ContratoRepository`, `MensalidadeService` |
| Mensalidades e cobrança | `Mensalidade`, `Pagamento`, `Cobranca`, serviços, notification, job e comandos `mensalidades:*` |
| Carteirinha e acesso | `Carteirinha`, `Acesso`, `CarteirinhaService`, `AcessoService`, QR Code e validação de voucher |
| PDV, venda e estoque | `Venda`, `VendaItem`, `Produto`, `MovimentacaoEstoque`, `Caixa`, respectivos Services e Repositories |
| Financeiro gerencial | `ContaPagar`, `ContaReceber`, `FinanceiroGestaoService`, relatórios e comando de atrasos |
| Hospedagem | `Quarto`, `Hospedagem`, `HospedagemConsumo`, `HospedagemService` e Requests |
| Integrações | `MercadoPagoService`, `EvolutionApiService`, controllers de webhook e Jobs |
| Auditoria | `AuditoriaService`, `AuditoriaPolicy`, Activitylog e tela administrativa |
| Relatórios | `RelatorioService`, exports em `Services/Relatorios` e views `resources/views/**/pdf` |

## Persistência e interface

- `database/migrations`: fonte do schema, constraints, índices e chaves estrangeiras.
- `database/seeders/RolesAndPermissionsSeeder.php`: catálogo de roles e permissões.
- `database/seeders/DemoDataSeeder.php`: ambiente demonstrativo; nunca trate credenciais de demo como produção.
- `resources/views/layouts`: layouts administrativo, público, PDV e validador.
- `resources/views/components`: biblioteca visual Blade reutilizável.
- `config/parque.php`: régua de cobrança, QR Code, bloqueio por atraso e impressão ESC/POS.
- `config/mercadopago.php` e `config/evolution.php`: configuração das integrações.
- `Dockerfile`, `docker-compose.yml`, `docker/entrypoint.sh`: build, inicialização, migrations e processos auxiliares.
- `tests/Feature`: cobertura dos fluxos integrados; `tests/TestCase.php` contém a base comum.

## Ao adicionar estrutura

Mantenha namespaces e pastas existentes. Atualize `ARQUITETURA.md` quando criar módulo, camada, integração, rota pública, processo assíncrono ou decisão arquitetural relevante. Atualize `README.md` quando mudar instalação, operação, configuração ou comandos para o usuário.
