#!/usr/bin/env bash
# Deploy / atualização na VPS — rode a partir de /var/www/dsa-eco:
#   bash deploy/deploy.sh
#
# Ou do seu PC (com Host vps-dsa-eco no ~/.ssh/config):
#   ssh vps-dsa-eco "bash /var/www/dsa-eco/deploy/deploy.sh"
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/dsa-eco}"
COMPOSE_FILE="docker-compose.prod.yml"
ENV_FILE=".env.production"
BRANCH="${DEPLOY_BRANCH:-main}"

cd "$APP_DIR"

if [[ ! -f "$ENV_FILE" ]]; then
  echo "[deploy] ERRO: falta $APP_DIR/$ENV_FILE"
  echo "         cp .env.production.example .env.production  e edite as senhas/APP_KEY/APP_URL"
  exit 1
fi

if [[ ! -f "$COMPOSE_FILE" ]]; then
  echo "[deploy] ERRO: falta $COMPOSE_FILE neste diretório"
  exit 1
fi

echo "[deploy] git fetch/pull ($BRANCH)..."
git fetch origin
git checkout "$BRANCH"
git pull --ff-only origin "$BRANCH"

echo "[deploy] docker compose build + up..."
# NUNCA use "down -v" aqui — o volume db_data guarda o MySQL de produção.
docker compose -f "$COMPOSE_FILE" --env-file "$ENV_FILE" up -d --build --remove-orphans

echo "[deploy] status:"
docker compose -f "$COMPOSE_FILE" --env-file "$ENV_FILE" ps

echo "[deploy] OK — app em http://127.0.0.1:9080 (Nginx do host deve fazer proxy + SSL)"
echo "[deploy] Banco: volume Docker 'db_data' (persistente). Não use down -v."
