# Parque Aquático SaaS

Sistema web multi-tenant (multiempresa) para gestão de parques aquáticos: clientes e
dependentes, planos e contratos, mensalidades e cobrança recorrente, carteirinhas com
QR Code, controle de acesso na catraca, inadimplência, cobrança automática via
WhatsApp (Evolution API), pagamentos via Mercado Pago (Pix/Webhook), caixa/financeiro,
produtos/estoque, vendas com comissão de vendedores, dashboard, relatórios em PDF/Excel,
usuários com permissões (Policies) e trilha de auditoria.

> Leia também o **[ARQUITETURA.md](ARQUITETURA.md)** para decisões de design, o
> desenho do multi-tenant, os padrões de Service/Repository e o que está completo
> versus placeholder em cada módulo.

## Stack

| Camada        | Tecnologia |
|---------------|------------|
| Backend       | PHP 8.2+, Laravel 11 |
| Banco de dados| MySQL 8.0.16+ (ou MariaDB 10.2.1+) — InnoDB + utf8mb4 |
| Autenticação  | Laravel Sanctum (sessão para o painel web, token para API/app mobile) |
| Autorização   | Spatie Laravel Permission (roles/permissions) + Policies nativas do Laravel |
| Auditoria     | Spatie Laravel Activitylog |
| PDF           | barryvdh/laravel-dompdf |
| Excel         | maatwebsite/excel (PhpSpreadsheet) |
| QR Code       | endroid/qr-code |
| HTTP client   | Guzzle (com retry/backoff e logging próprios) |
| Fila          | Laravel Queue (`database` por padrão) |
| Frontend      | Blade + Tailwind CSS, build via Vite |
| Containers    | Docker + Docker Compose (`app`, `queue`, `scheduler`, `db`, `phpmyadmin`) |

## Requisitos

**Com Docker** (ver seção abaixo), o único requisito é Docker + Docker Compose — ele
cuida de PHP, Composer, Node e MySQL dentro dos containers. Os requisitos abaixo são
só para quem vai instalar tudo diretamente na própria máquina.

- PHP >= 8.2 com extensões `pdo_mysql`, `mbstring`, `bcmath`, `gd`, `zip`
- Composer 2.x
- **MySQL >= 8.0.16** ou **MariaDB >= 10.2.1** — versão mínima não é sugestão, é
  requisito: as migrations usam `CHECK` constraints (ex.: `dia_vencimento BETWEEN 1
  AND 28`), e o MySQL só passou a *aplicar* CHECK de verdade a partir da 8.0.16 —
  antes disso ele aceita a sintaxe e **ignora silenciosamente** a constraint, o que
  deixaria a regra de negócio sem proteção nenhuma no banco.
- Node.js >= 18 e npm
- (Produção) um worker de fila (`queue:work`) e o Scheduler do Laravel via cron

## ⚠️ Sobre este pacote

Este projeto foi desenvolvido em um ambiente sem acesso ao Packagist (registro de
pacotes do Composer), então **o `vendor/` não está incluído** e os comandos abaixo
(`composer install`, `php artisan migrate`, `php artisan test` etc.) **ainda não
foram executados**. Todo o código foi escrito à mão seguindo a estrutura oficial do
Laravel 11 e passou por:

- **Lint de sintaxe** (`php -l`) em todos os 165 arquivos PHP — sem erros.
- **Validação do schema**: todas as 12 migrations foram traduzidas manualmente para
  SQL puro e executadas com sucesso em uma instância real de MariaDB 10.11 (tabelas,
  chaves estrangeiras, índices). As 5 `CHECK` constraints e o novo índice único de
  `produtos` foram testadas funcionalmente de verdade: inserção inválida rejeitada
  (`dia_vencimento=29`), inserção válida aceita (`dia_vencimento=28`), múltiplos
  produtos com `sku IS NULL` permitidos, e SKU duplicado na mesma empresa rejeitado
  com `ERROR 1062 Duplicate entry`.
- **Build do frontend**: `npm install && npm run build` rodou com sucesso (Vite +
  Tailwind, 58 módulos), validando a configuração de assets e a sintaxe geral das
  views Blade.

O que falta antes do primeiro `php artisan serve`: rodar `composer install` (baixa o
Laravel e os pacotes listados em `composer.json`) e seguir os passos de instalação
abaixo. Isso é esperado — nenhum framework PHP roda sem suas dependências do Composer.

## Subir com Docker (recomendado — um único comando)

Se você tem Docker Desktop (ou Docker Engine + Compose no Linux) instalado, **não
precisa instalar PHP, Composer, Node nem MySQL na sua máquina** — tudo roda em
containers. Na pasta do projeto:

```bash
docker compose up -d --build
```

Isso sobe 5 containers e faz tudo sozinho, na ordem certa:

| Container | O que faz |
|---|---|
| `parque_db` | MySQL 8.0, aguarda ficar saudável (`healthcheck`) antes do resto subir |
| `parque_app` | Compila o Tailwind/Vite, instala as dependências PHP, **roda `migrate` e o seed de demonstração automaticamente na primeira subida**, sobe o Apache |
| `parque_queue` | Worker de fila (`queue:work`) — processa WhatsApp e webhook do Mercado Pago |
| `parque_scheduler` | Substitui o cron: chama `schedule:run` a cada minuto |
| `parque_phpmyadmin` | Interface web pra ver o banco em `http://localhost:8080` (opcional, pode remover do `docker-compose.yml`) |

O primeiro `--build` demora alguns minutos (baixa as imagens base, compila os assets,
roda `composer install`). Depois disso, `docker compose up -d` sozinho (sem
`--build`) sobe tudo em segundos.

- **Painel:** http://localhost:8000 — login `admin@parqueaquatico.com.br` /
  `trocar@123` (o seed de demonstração já populou empresa, unidade e dados de
  exemplo, iguais ao login padrão descrito mais abaixo).
- **Ver logs em tempo real:** `docker compose logs -f app`
- **Rodar um comando artisan dentro do container:** `docker compose exec app php artisan tinker`
- **Rodar os testes:** o build padrão instala as dependências de dev (necessário —
  o seeder de demonstração usa `fakerphp/faker`), então
  `docker compose exec app php artisan test` funciona direto — só crie o banco de
  teste antes: `docker compose exec db mysql -uroot -pparque_root -e "CREATE DATABASE parque_aquatico_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"`.
- **Parar tudo:** `docker compose down` (mantém os dados — banco e uploads ficam em
  volumes nomeados). **Apagar os dados também:** `docker compose down -v`.

**Sobre o `.env.docker`:** ele já vem com um `APP_KEY` real e as credenciais do MySQL
já configuradas — é isso que permite o `up -d` funcionar sem passo manual nenhum.
Isso é intencional e seguro para uso local/homologação, mas **não é uma chave secreta
de produção**: se algum dia você expuser isso na internet de verdade, gere sua
própria chave (`docker compose exec app php artisan key:generate --show`) e troque
as senhas do MySQL no `docker-compose.yml`/`.env.docker` antes.

Para configurar Evolution API/Mercado Pago no Docker, edite as variáveis
correspondentes direto no `.env.docker` (não no `.env.example`) e rode
`docker compose up -d --build` de novo.

### Se algo falhar na primeira subida

- `docker compose ps` mostra o status de cada container — se `parque_db` não chegar a
  "healthy", os outros ficam esperando (isso é esperado, eles têm `depends_on`
  configurado assim).
- `docker compose logs app` mostra exatamente onde o entrypoint travou (aguardando
  MySQL, rodando migration, etc.).
- Se a porta 8000, 3306 ou 8080 já estiver em uso na sua máquina, troque o lado
  esquerdo do mapeamento em `docker-compose.yml` (ex.: `"8001:80"`).

## Instalação manual (sem Docker)

```bash
# 1. Dependências PHP
composer install

# 2. Dependências JS e build dos assets
npm install
npm run build          # ou `npm run dev` durante o desenvolvimento

# 3. Ambiente
cp .env.example .env
php artisan key:generate

# 4. Configure o .env (mínimo obrigatório)
#    DB_CONNECTION=mysql
#    DB_HOST=127.0.0.1
#    DB_PORT=3306
#    DB_DATABASE=parque_aquatico
#    DB_USERNAME=root
#    DB_PASSWORD=sua_senha
#    Veja também as seções EVOLUTION_* e MERCADOPAGO_* mais abaixo.

# 5. Crie o banco (utf8mb4 é obrigatório) e rode as migrations + seeders
mysql -u root -p -e "CREATE DATABASE parque_aquatico CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
php artisan migrate --seed

# 6. Suba o servidor de desenvolvimento
php artisan serve
```

### Login padrão (seed de demonstração)

Após `php artisan migrate --seed`, o `DemoDataSeeder` cria uma empresa, uma unidade e
um usuário administrador:

- **E-mail:** `admin@parqueaquatico.com.br`
- **Senha:** `trocar@123`

> Troque essa senha antes de qualquer uso real — ela existe apenas para permitir o
> primeiro acesso ao painel em ambiente de desenvolvimento/homologação.

### Integrações externas

Configure no `.env` (valores de exemplo documentados em `.env.example`):

```
# Evolution API (envio de cobrança/notificação por WhatsApp)
EVOLUTION_API_URL=
EVOLUTION_API_KEY=
EVOLUTION_INSTANCE=

# Mercado Pago (Pix, cobrança e webhook de pagamento)
MERCADOPAGO_ACCESS_TOKEN=
MERCADOPAGO_WEBHOOK_SECRET=
```

Sem essas chaves, os módulos de cobrança por WhatsApp e pagamento via Mercado Pago
continuam funcionando estruturalmente (rotas, jobs, filas, telas), mas as chamadas
HTTP reais falharão — o que é esperado em ambiente sem credenciais válidas. O
`Services/Integrations/Concerns/RealizaRequisicoesComRetry.php` já trata timeout,
retry com backoff e log de erro para essas falhas.

### Fila e agendamento (produção)

```bash
# Worker de fila (processa envio de WhatsApp e webhooks do Mercado Pago)
php artisan queue:work --tries=3

# Scheduler (gera mensalidades, aplica atraso, dispara cobranças, processa
# inadimplência e expira carteirinhas — tudo definido em routes/console.php)
# Adicione ao crontab do servidor:
* * * * * cd /caminho/do/projeto && php artisan schedule:run >> /dev/null 2>&1
```

## Testes

Os testes usam **MySQL/MariaDB**, não SQLite — as migrations criam `CHECK`
constraints via `ALTER TABLE ... ADD CONSTRAINT` logo após o `Schema::create`, e o
SQLite não suporta `ALTER TABLE` para adicionar constraint numa tabela que já existe.
Antes de rodar os testes, crie o banco de teste:

```bash
mysql -u root -p -e "CREATE DATABASE parque_aquatico_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
php artisan test
```

As credenciais de teste ficam em `.env.testing`.

Cobertura atual (`tests/Feature/`):

- `ContratoMensalidadeTest.php` — contratação gera mensalidade e carteirinha,
  respeita limite de dependentes do plano, marcar mensalidade como paga é
  idempotente, cancelamento de contrato cancela apenas mensalidades futuras.
- `AcessoCarteirinhaTest.php` — liberação de acesso na catraca por carteirinha
  válida, negação por carteirinha inexistente/bloqueada/inadimplência, e acesso via
  carteirinha de dependente usando o contrato do titular.

## Estrutura de pastas (resumo)

```
app/
  Console/Commands/     Comandos do scheduler (geração de mensalidades, cobrança, etc.)
  Exceptions/           NegocioException (regra de negócio) e IntegrationException
  Http/
    Controllers/Admin/  Painel administrativo (web, sessão)
    Controllers/Api/    API para app mobile (Sanctum token)
    Controllers/Webhooks/  Recebimento de webhooks (Mercado Pago, Evolution API)
    Requests/           Form Requests com validação (inclui isolamento por tenant)
    Resources/          API Resources (serialização JSON)
  Jobs/                 Filas: envio de WhatsApp, processamento de webhook
  Models/               Eloquent, com TenantScope global aplicado via BelongsToTenant
  Notifications/        Canal customizado de notificação por WhatsApp
  Policies/             Autorização por model (Laravel Policies)
  Providers/            AuthServiceProvider, RepositoryServiceProvider, etc.
  Repositories/         Contracts + implementações Eloquent (padrão Repository)
  Services/             Regras de negócio (nunca em Controllers) e integrações externas
database/
  migrations/           Schema MySQL (chaves, índices, CHECK constraints)
  seeders/               Roles/permissions + dados de demonstração
  factories/             Model factories para testes
resources/views/        Blade + Tailwind (painel admin)
routes/                 web.php, api.php, console.php (scheduler)
tests/Feature/          Testes de integração dos fluxos críticos
docker/                 apache-vhost.conf, entrypoint.sh / entrypoint.prod.sh
deploy/                 nginx-dsa-eco.conf, deploy.sh (VPS)
Dockerfile              Build multi-stage: assets (Node) + app (PHP/Apache) — local
Dockerfile.prod         Imagem de produção (composer --no-dev, sem seed)
docker-compose.yml      app + queue + scheduler + db + phpmyadmin (local)
docker-compose.prod.yml Produção: app em 127.0.0.1:9080, sem phpMyAdmin
.env.docker             Env pronto para os containers locais
.env.production.example Modelo de env de produção (não commitar .env.production)
docs/DEPLOY-VPS.md      Guia espelhado da seção Deploy na VPS
```

## Deploy na VPS (produção)

Publicação com **Docker Compose de produção** atrás do **Nginx do host** (reverse proxy + SSL). Isola este projeto dos outros em `/var/www/` (ex.: `farmaciatrabalhadorpm.com.br`).

| Camada | Onde | Função |
|--------|------|--------|
| Nginx + Certbot | host | HTTPS → `127.0.0.1:9080` |
| `parque_prod_app` | Docker | Laravel + Apache (só localhost) |
| `parque_prod_queue` | Docker | `queue:work` |
| `parque_prod_scheduler` | Docker | `schedule:run` a cada minuto |
| `parque_prod_db` | Docker | MySQL 8 (sem porta pública) |

- Pasta no servidor: `/var/www/dsa-eco`
- Repo: `git@github.com:DSASOFTWEB/DSA-ECO.git`
- Arquivos: `Dockerfile.prod`, `docker-compose.prod.yml`, `deploy/`, `.env.production.example`
- Guia detalhado: [docs/DEPLOY-VPS.md](docs/DEPLOY-VPS.md)

**Premissas:** sem phpMyAdmin em produção; entrypoint de prod **não** roda seed de demonstração (só `migrate`).

### 1. Chave SSH do PC → VPS

No PC (já gerada para este projeto):

- Privada: `~/.ssh/id_ed25519_dsa_eco`
- Pública: `~/.ssh/id_ed25519_dsa_eco.pub`
- Alias: `vps-dsa-eco` em `~/.ssh/config`

Chave pública (cole na VPS):

```
ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIAB3ujnwlhyBJrvKa14DUDQi2RSQEHIdZkae7RfFAbw9 dsa-eco-vps-deploy@idtecnologia
```

Na VPS, como root:

```bash
mkdir -p /root/.ssh && chmod 700 /root/.ssh
echo 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIAB3ujnwlhyBJrvKa14DUDQi2RSQEHIdZkae7RfFAbw9 dsa-eco-vps-deploy@idtecnologia' >> /root/.ssh/authorized_keys
chmod 600 /root/.ssh/authorized_keys
```

No PC, `~/.ssh/config` deve ter algo assim (troque `HostName` pelo **IP** se o hostname não resolver):

```
Host vps-dsa-eco
  HostName v33654orikic
  User root
  IdentityFile ~/.ssh/id_ed25519_dsa_eco
  IdentitiesOnly yes
```

Teste: `ssh vps-dsa-eco`

### 2. Deploy key da VPS → GitHub (somente leitura)

Chave **diferente** da do PC — gerada na VPS:

```bash
ssh-keygen -t ed25519 -f /root/.ssh/id_ed25519_dsa_eco_deploy -N "" -C "vps-dsa-eco-github-deploy"
cat /root/.ssh/id_ed25519_dsa_eco_deploy.pub
```

GitHub → repo **DSA-ECO** → **Settings → Deploy keys → Add deploy key** (sem write).  
Na VPS, aponte o SSH do GitHub para essa chave:

```bash
cat >> /root/.ssh/config <<'EOF'
Host github.com
  HostName github.com
  User git
  IdentityFile /root/.ssh/id_ed25519_dsa_eco_deploy
  IdentitiesOnly yes
EOF
chmod 600 /root/.ssh/config
ssh -T git@github.com
```

### 3. Primeiro deploy

Pré-requisitos: `docker`, `docker compose`, `nginx` na VPS.

```bash
mkdir -p /var/www/dsa-eco
git clone git@github.com:DSASOFTWEB/DSA-ECO.git /var/www/dsa-eco
cd /var/www/dsa-eco

cp .env.production.example .env.production
nano .env.production   # APP_KEY, APP_URL, DB_PASSWORD, DB_ROOT_PASSWORD

docker compose -f docker-compose.prod.yml --env-file .env.production up -d --build
docker compose -f docker-compose.prod.yml ps
curl -I http://127.0.0.1:9080/login
```

Crie o admin (prod **não** faz seed). Exemplo via tinker ou, só se consciente, `db:seed` uma vez.

### 4. Nginx + SSL

```bash
cp /var/www/dsa-eco/deploy/nginx-dsa-eco.conf /etc/nginx/sites-available/dsa-eco
nano /etc/nginx/sites-available/dsa-eco   # troque SEU_DOMINIO.com.br
ln -sf /etc/nginx/sites-available/dsa-eco /etc/nginx/sites-enabled/dsa-eco
nginx -t && systemctl reload nginx
certbot --nginx -d seu-dominio.com.br -d www.seu-dominio.com.br
```

### 5. Atualizações seguintes

```powershell
git push origin main
ssh vps-dsa-eco "bash /var/www/dsa-eco/deploy/deploy.sh"
```

### Segurança

- Não commitar `.env.production` (já no `.gitignore`).
- MySQL e porta `9080` só em localhost; firewall: 22/80/443.
- `APP_DEBUG=false` e `SESSION_SECURE_COOKIE=true` com HTTPS.

## Usuários de demonstração (somente Docker local / seed)

Admin do parque: `admin@parqueaquatico.com.br` / `trocar@123`  
Super (SaaS): `super@parqueaquatico.com.br` / `trocar@123`

Veja o [ARQUITETURA.md](ARQUITETURA.md) para o detalhamento de cada decisão.
