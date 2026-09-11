#!/usr/bin/env bash
# Gera/atualiza .env.production com APP_KEY sem tocar no banco Docker.
# Uso na VPS (ou no PC com bash):
#   cd /var/www/dsa-eco && bash deploy/gerar-env-production.sh
set -euo pipefail

APP_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$APP_DIR"

EXAMPLE=".env.production.example"
ENV_FILE=".env.production"

if [[ ! -f "$EXAMPLE" ]]; then
  echo "[env] ERRO: falta $EXAMPLE"
  exit 1
fi

if [[ ! -f "$ENV_FILE" ]]; then
  cp "$EXAMPLE" "$ENV_FILE"
  echo "[env] Criado $ENV_FILE a partir do example."
else
  echo "[env] $ENV_FILE já existe — só preenche APP_KEY se estiver vazio."
fi

# Gera APP_KEY no formato Laravel (base64:...) sem precisar do artisan/PHP do host.
if grep -qE '^APP_KEY=$|^APP_KEY=$' "$ENV_FILE" || grep -qE '^APP_KEY=\s*$' "$ENV_FILE"; then
  KEY="base64:$(openssl rand -base64 32 | tr -d '\n')"
  # portable sed: reescreve a linha APP_KEY=
  if sed --version >/dev/null 2>&1; then
    sed -i "s|^APP_KEY=.*|APP_KEY=${KEY}|" "$ENV_FILE"
  else
    sed -i '' "s|^APP_KEY=.*|APP_KEY=${KEY}|" "$ENV_FILE"
  fi
  echo "[env] APP_KEY gerada e gravada em $ENV_FILE"
  echo "[env]   $KEY"
else
  echo "[env] APP_KEY já preenchida — não alterada (evita invalidar sessões/cookies)."
fi

echo
echo "[env] IMPORTANTE — banco Docker:"
echo "  - Os dados ficam no volume nomeado 'db_data' (sobrevive a restart e rebuild)."
echo "  - NUNCA rode: docker compose ... down -v   ← isso APAGA o MySQL."
echo "  - Não troque DB_PASSWORD/DB_ROOT_PASSWORD depois da 1ª subida do volume"
echo "    (o MySQL só aplica essas vars na criação inicial do volume)."
echo
echo "[env] Próximos passos:"
echo "  1) Edite APP_URL, DB_PASSWORD e DB_ROOT_PASSWORD em $ENV_FILE (se ainda for a 1ª vez)"
echo "  2) docker compose -f docker-compose.prod.yml --env-file .env.production up -d --build"
