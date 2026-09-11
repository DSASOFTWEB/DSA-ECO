# Deploy na VPS — DSA-ECO / Parque Aquático SaaS

Publicação com **Docker Compose de produção** atrás do **Nginx do host** (reverse proxy + SSL). Isola este projeto dos outros em `/var/www/` (ex.: `farmaciatrabalhadorpm.com.br`).

## Arquitetura

| Camada | Onde | Função |
|--------|------|--------|
| Nginx + Certbot | host | HTTPS, `proxy_pass` → `127.0.0.1:9080` |
| `parque_prod_app` | Docker | Laravel + Apache (porta só localhost) |
| `parque_prod_queue` | Docker | `queue:work` |
| `parque_prod_scheduler` | Docker | `schedule:run` a cada minuto |
| `parque_prod_db` | Docker | MySQL 8 (sem porta pública) |

Código em: `/var/www/dsa-eco`  
Repo: `git@github.com:DSASOFTWEB/DSA-ECO.git`

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

```bash
mkdir -p /var/www/dsa-eco
git clone git@github.com:DSASOFTWEB/DSA-ECO.git /var/www/dsa-eco
cd /var/www/dsa-eco

cp .env.production.example .env.production
nano .env.production
```

Preencha no mínimo:

- `APP_KEY` — gere com `php artisan key:generate --show` (ou num container one-off)
- `APP_URL` — `https://seu-dominio.com.br`
- `DB_PASSWORD` / `DB_ROOT_PASSWORD` — senhas fortes
- Integrações (Evolution / Mercado Pago) se for usar

Subir stack:

```bash
docker compose -f docker-compose.prod.yml --env-file .env.production up -d --build
docker compose -f docker-compose.prod.yml ps
curl -I http://127.0.0.1:9080/login
```

**Primeiro usuário admin:** o entrypoint de produção **não** roda seed. Crie o admin manualmente (tinker) ou rode uma vez, só se quiser dados demo:

```bash
# Opcional e consciente — NÃO use senha padrão em produção pública:
# docker compose -f docker-compose.prod.yml --env-file .env.production exec app php artisan db:seed --force
```

Preferível criar usuário real:

```bash
docker compose -f docker-compose.prod.yml --env-file .env.production exec app php artisan tinker
# User::create([...]); syncRoles(['admin']);
```

## 5. Nginx + SSL

```bash
cp /var/www/dsa-eco/deploy/nginx-dsa-eco.conf /etc/nginx/sites-available/dsa-eco
nano /etc/nginx/sites-available/dsa-eco   # troque SEU_DOMINIO.com.br
ln -sf /etc/nginx/sites-available/dsa-eco /etc/nginx/sites-enabled/dsa-eco
nginx -t && systemctl reload nginx

# DNS A do domínio → IP da VPS, depois:
certbot --nginx -d seu-dominio.com.br -d www.seu-dominio.com.br
```

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
- `APP_DEBUG=false` e `SESSION_SECURE_COOKIE=true` com HTTPS.
- Firewall: 22/80/443; nada de 3306/9080 para o mundo (9080 só localhost).

## Troubleshooting

| Sintoma | Checagem |
|---------|----------|
| 502 Bad Gateway | `docker compose ... ps` — app healthy? `curl -I http://127.0.0.1:9080/login` |
| Migrate falha | logs: `docker compose -f docker-compose.prod.yml logs app` |
| Git pull denied | deploy key no GitHub + `IdentityFile` no `/root/.ssh/config` |
| SSH do PC falha | chave em `authorized_keys` + `HostName` com IP correto |
