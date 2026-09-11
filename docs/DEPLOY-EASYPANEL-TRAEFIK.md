# EasyPanel + Traefik (esta VPS)

Nesta VPS a porta 80/443 pertence ao container `easypanel-traefik`, **não** ao Nginx do host.
Por isso `certbot --nginx` falha com `bind() to 0.0.0.0:80`.

O app DSA-ECO já escuta em `127.0.0.1:9080`. O Traefik precisa rotear
`softplanerp.com.br` → esse endereço e emitir o SSL.

## Passo 0 — descobrir rede e resolvers do Traefik

```bash
TRAEFIK=$(docker ps -q -f name=easypanel-traefik | head -1)
echo "Traefik id: $TRAEFIK"

docker inspect "$TRAEFIK" --format '{{range $k,$v := .NetworkSettings.Networks}}{{$k}}{{"\n"}}{{end}}'
docker inspect "$TRAEFIK" --format '{{range .Config.Env}}{{println .}}{{end}}' | grep -iE 'ENTRYPOINT|CERT|ACME|RESOLVER' || true
```

Anote o **nome da rede** (ex.: `easypanel`) e o **certResolver** (ex.: `letsencrypt`).

## Opção A (recomendada) — domínio no painel EasyPanel

1. Abra o EasyPanel no navegador.
2. Crie/abra um serviço App (ou “Compose”) apontando para este stack, **ou** use um serviço “proxy” se existir.
3. Em **Domains** adicione:
   - Host: `softplanerp.com.br` (+ `www` se quiser)
   - Target port: `80` (porta **dentro** do container `parque_prod_app`)
   - HTTPS: ligado (Let's Encrypt)
4. O EasyPanel aplica labels Traefik e o certificado sozinho.

Se o stack já está fora do EasyPanel (compose manual), use a **Opção B**.

## Opção B — rota Traefik custom → `127.0.0.1:9080`

O Traefik (em Docker) precisa alcançar o host. Em Linux costuma ser o gateway Docker:

```bash
# Gateway típico da bridge
ip -4 addr show docker0 | awk '/inet /{print $2}' | cut -d/ -f1
# muitas vezes: 172.17.0.1
```

Crie/edite o custom do EasyPanel (caminho comum):

```bash
mkdir -p /etc/easypanel/traefik/config
nano /etc/easypanel/traefik/config/custom.yaml
```

Conteúdo (ajuste `certResolver` se o Passo 0 mostrar outro nome):

```yaml
http:
  routers:
    dsa-eco-http:
      rule: "Host(`softplanerp.com.br`) || Host(`www.softplanerp.com.br`)"
      entryPoints:
        - http
      middlewares:
        - dsa-eco-https-redirect
      service: dsa-eco

    dsa-eco-https:
      rule: "Host(`softplanerp.com.br`) || Host(`www.softplanerp.com.br`)"
      entryPoints:
        - https
      tls:
        certResolver: letsencrypt
      service: dsa-eco

  middlewares:
    dsa-eco-https-redirect:
      redirectScheme:
        scheme: https
        permanent: true

  services:
    dsa-eco:
      loadBalancer:
        servers:
          - url: "http://172.17.0.1:9080"
```

Reinicie o Traefik pelo **EasyPanel → Settings → Traefik → Restart**, ou:

```bash
docker service update --force easypanel-traefik
# se não for swarm service name exato:
docker ps -f name=easypanel-traefik --format '{{.Names}}'
```

Teste:

```bash
curl -I http://softplanerp.com.br/up
curl -I https://softplanerp.com.br/up
```

## Opção C — labels no compose + rede do Traefik

Se preferir o próprio `docker-compose.prod.yml` se registrar no Traefik:

1. Descubra a rede: `docker inspect $TRAEFIK ...` (Passo 0).
2. Em `docker-compose.prod.yml`, o serviço `app` deve ter:
   - `networks: [parque_prod, easypanel]` (external)
   - labels `traefik.enable=true`, `Host(...)`, `entrypoints=https`, `tls.certresolver=...`, `loadbalancer.server.port=80`
3. `docker compose ... up -d` de novo.

Arquivo de referência no repo: `deploy/traefik-dsa-eco.labels.example.yml`.

## APP_URL

```bash
cd /var/www/dsa-eco
sed -i 's|^APP_URL=.*|APP_URL=https://softplanerp.com.br|' .env.production
docker compose -f docker-compose.prod.yml --env-file .env.production up -d --force-recreate app
```

## Não use nestes casos

- `certbot --nginx` no host (porta 80 do Docker/Traefik)
- Publicar o `parque_prod_app` em `0.0.0.0:80` (conflito com Traefik)
