# Deploy na VPS — DSA-ECO / Parque Aquático SaaS

Publicação com **Docker Compose** na **porta 9080**. Nesta VPS o EasyPanel já usa 80/443 — não mexa nisso. Detalhes: [DEPLOY-EASYPANEL-TRAEFIK.md](DEPLOY-EASYPANEL-TRAEFIK.md).

## Arquitetura

| Camada | Onde | Função |
|--------|------|--------|
| EasyPanel Traefik | Docker | 80/443 dos outros apps (não tocar) |
| `parque_prod_app` | Docker | Laravel + Apache em **:9080** |
| `parque_prod_queue` | Docker | `queue:work` |
| `parque_prod_scheduler` | Docker | `schedule:run` a cada minuto |
| `parque_prod_db` | Docker | MySQL 8 (sem porta pública) |

Código em: `/var/www/dsa-eco`  
Repo: `git@github.com:DSASOFTWEB/DSA-ECO.git`

Acesso: `http://IP:9080` ou `http://dominio:9080` (`APP_URL` com a porta).


## 1. Chave do PC → VPS

No PC já existe a chave dedicada:

- Privada: `~/.ssh/id_ed25519_dsa_eco`
- Pública: `~/.ssh/id_ed25519_dsa_eco.pub`
- Alias SSH: `vps-dsa-eco` (em `~/.ssh/config`)

Na VPS, como root:

```bash
mkdir -p /root/.ssh
chmod 700 /root/.ssh
# Cole a linha pública do PC (id_ed25519_dsa_eco.pub):
echo 'COLE_AQUI_A_CHAVE_PUBLICA' >> /root/.ssh/authorized_keys
chmod 600 /root/.ssh/authorized_keys
```

Teste do PC:

```powershell
ssh vps-dsa-eco
```

Se o hostname `v33654orikic` não resolver, edite `HostName` em `~/.ssh/config` para o **IP público** da VPS.

## 2. Deploy key da VPS → GitHub (somente leitura)

Na VPS (chave **diferente** da do PC):

```bash
ssh-keygen -t ed25519 -f /root/.ssh/id_ed25519_dsa_eco_deploy -N "" -C "vps-dsa-eco-github-deploy"
cat /root/.ssh/id_ed25519_dsa_eco_deploy.pub
```

No GitHub: repo **DSA-ECO** → **Settings → Deploy keys → Add deploy key**  
- Title: `vps-dsa-eco`  
- Key: cole a pública acima  
- **Allow write access:** desmarcado (só `git pull`)

SSH config na VPS para usar essa chave no GitHub:

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

## 3. Pré-requisitos na VPS

```bash
# Docker Engine + Compose plugin
docker --version
docker compose version

# Nginx (já costuma existir se há sites em /var/www)
nginx -v
```

## 4. Primeiro deploy

Pré-requisitos: `docker` e `docker compose` na VPS.

```bash
mkdir -p /var/www/dsa-eco
git clone git@github.com:DSASOFTWEB/DSA-ECO.git /var/www/dsa-eco
cd /var/www/dsa-eco

# Cria .env.production e gera APP_KEY (não apaga banco — volume separado)
bash deploy/gerar-env-production.sh
nano .env.production   # APP_URL=http://dominio:9080 + senhas DB

docker compose -f docker-compose.prod.yml --env-file .env.production up -d --build
docker compose -f docker-compose.prod.yml ps
curl -I http://127.0.0.1:9080/login
```

### Persistência do banco (importante)

| Ação | O que acontece com o MySQL |
|------|----------------------------|
| `up -d --build` / restart / `deploy.sh` | Mantém dados (volume `parque_aquatico_prod_db`) |
| `docker compose ... down` | Para containers, **mantém** o volume |
| `docker compose ... down -v` | **APAGA o banco** — nunca use em produção |
| Trocar `DB_PASSWORD` depois da 1ª subida | Não recria o user; pode quebrar o login no DB |

O entrypoint de produção só roda `migrate` (schema). **Não** roda `db:seed`.

**Primeiro usuário admin:** crie manualmente (tinker) ou, só se consciente, `db:seed` uma vez:

```bash
# Opcional — NÃO use senha padrão em produção pública:
# docker compose -f docker-compose.prod.yml --env-file .env.production exec app php artisan db:seed --force
```

Preferível criar usuário real:

```bash
docker compose -f docker-compose.prod.yml --env-file .env.production exec app php artisan tinker
# User::create([...]); syncRoles(['admin']);
```

## 5. Acesso público (porta 9080)

EasyPanel permanece em 80/443. Abra a porta **9080** no firewall e use:

```text
http://SEU_IP:9080
http://seu-dominio.com.br:9080
```

`APP_URL` no `.env.production` deve incluir a porta (`http://...:9080`).

## 6. Atualizações seguintes

Do PC (depois de `git push origin main`):

```powershell
ssh vps-dsa-eco "bash /var/www/dsa-eco/deploy/deploy.sh"
```

Ou na VPS:

```bash
bash /var/www/dsa-eco/deploy/deploy.sh
```

## Segurança rápida

- Não publique MySQL nem phpMyAdmin em porta pública (o compose de prod já evita isso).
- Não commite `.env.production`.
- Firewall: 22, 80/443 (EasyPanel), **9080** (este app). Sem 3306 público.
- **Nunca** `docker compose ... down -v` em produção (apaga o volume do MySQL).

## Troubleshooting

| Sintoma | Checagem |
|---------|----------|
| 502 Bad Gateway | `docker compose ... ps` — app healthy? `curl -I http://127.0.0.1:9080/up` |
| `parque_prod_app` unhealthy | `docker logs parque_prod_app --tail 100` — APP_KEY vazia? migrate? |
| Migrate falha | logs: `docker compose -f docker-compose.prod.yml logs app` |
| Git pull denied | deploy key no GitHub + `IdentityFile` no `/root/.ssh/config` |
| SSH do PC falha | chave em `authorized_keys` + `HostName` com IP correto |
