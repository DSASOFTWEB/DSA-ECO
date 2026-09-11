# Domínio normal (sem porta) com EasyPanel

Objetivo: entrar em **`http://softplanerp.com.br`** (e depois HTTPS).

EasyPanel continua dono de 80/443. O app fica em `9080` só como backend;
o Traefik faz o reverse e o usuário **não** digita `:9080`.

## Na VPS

```bash
cd /var/www/dsa-eco
git pull

# App escutando (backend)
docker compose -f docker-compose.prod.yml --env-file .env.production up -d
curl -I http://127.0.0.1:9080/up

# Gateway do host (ajuste no YAML se for diferente)
ip -4 addr show docker0 | awk '/inet /{print $2}' | cut -d/ -f1

mkdir -p /etc/easypanel/traefik/config
cp /var/www/dsa-eco/deploy/traefik-dsa-eco.custom.yaml /etc/easypanel/traefik/config/custom.yaml
# se o gateway não for 172.17.0.1, edite a linha url: no custom.yaml

# Reinicie o Traefik pelo EasyPanel (Settings → Traefik → Restart)
# Alternativa swarm:
docker service ls | grep -i traefik
docker service update --force easypanel-traefik   # use o nome exato do service ls
```

```bash
sed -i 's|^APP_URL=.*|APP_URL=http://softplanerp.com.br|' .env.production
sed -i 's|^SESSION_SECURE_COOKIE=.*|SESSION_SECURE_COOKIE=false|' .env.production
docker compose -f docker-compose.prod.yml --env-file .env.production up -d --force-recreate app
```

DNS: registro **A** de `softplanerp.com.br` (e `www`) → IP da VPS.

Teste:

```bash
curl -I -H 'Host: softplanerp.com.br' http://127.0.0.1/up
curl -I http://softplanerp.com.br/up
```

## HTTPS (depois)

No EasyPanel, ative HTTPS no domínio ou acrescente router `https` + `tls.certResolver`
no custom (nome do resolver: inspecione env do container Traefik).
Aí mude `APP_URL=https://softplanerp.com.br` e `SESSION_SECURE_COOKIE=true`.
