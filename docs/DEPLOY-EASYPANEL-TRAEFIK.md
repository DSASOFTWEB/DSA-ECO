# Acesso na VPS com EasyPanel

Nesta VPS o EasyPanel (Traefik) já usa **80/443**. Não altere isso.

O DSA-ECO sobe na **porta 9080** e fica acessível direto:

```text
http://SEU_IP:9080
http://softplanerp.com.br:9080
```

## Na VPS

```bash
cd /var/www/dsa-eco
git pull

# APP_URL com a porta
sed -i 's|^APP_URL=.*|APP_URL=http://softplanerp.com.br:9080|' .env.production

docker compose -f docker-compose.prod.yml --env-file .env.production up -d --force-recreate app

curl -I http://127.0.0.1:9080/up
```

Libere **9080/tcp** no firewall da VPS (e no painel do provedor, se houver).

DNS do domínio: registro **A** → IP da VPS (acesso via `domínio:9080`).

Não use certbot/Nginx na 80 para este app enquanto o EasyPanel estiver ativo.
