#!/usr/bin/env bash
#
# atualize.sh — atualização dia a dia do Parque Aquático SaaS (DSA-ECO) na VPS
#
# Uso (na pasta do clone, tipicamente /var/www/dsa-eco):
#   chmod +x atualize.sh
#   ./atualize.sh
#
# Do PC (com Host vps-dsa-eco no ~/.ssh/config):
#   ssh vps-dsa-eco "bash /var/www/dsa-eco/atualize.sh"
#
# Flags opcionais (env):
#   DISCARD_LOCAL=1   — descarta alterações locais de código (git checkout -- .)
#                       NÃO apaga .env.production
#   SKIP_BUILD=1      — só restart/recreate, sem rebuild da imagem
#   SKIP_MIGRATE=1    — não força migrate via exec (o entrypoint.prod já migra no boot)
#   SKIP_CACHE=1      — não limpa/recompõe caches artisan
#   SKIP_HEALTH=1     — não testa /up
#   DEPLOY_BRANCH=main
#   APP_PORT=9080     — porta publicada do app
#
# Boas práticas:
#   - só pull --ff-only (sem merge sujo)
#   - preserva .env.production e volumes (MySQL + storage)
#   - NUNCA usa "docker compose down -v"
#   - rebuild + up -d
#   - limpa caches e recompõe config/route/view em produção
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_DIR="${REPO_DIR:-${SCRIPT_DIR}}"
COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.prod.yml}"
ENV_FILE="${ENV_FILE:-.env.production}"
APP_SERVICE="${APP_SERVICE:-app}"
APP_PORT="${APP_PORT:-9080}"
BRANCH="${DEPLOY_BRANCH:-main}"
DISCARD_LOCAL="${DISCARD_LOCAL:-0}"
SKIP_BUILD="${SKIP_BUILD:-0}"
SKIP_MIGRATE="${SKIP_MIGRATE:-0}"
SKIP_CACHE="${SKIP_CACHE:-0}"
SKIP_HEALTH="${SKIP_HEALTH:-0}"

log()  { printf '\033[1;36m==>\033[0m %s\n' "$*"; }
ok()   { printf '\033[1;32mOK>\033[0m %s\n' "$*"; }
warn() { printf '\033[1;33m!!>\033[0m %s\n' "$*"; }
die()  { printf '\033[1;31mERRO:\033[0m %s\n' "$*" >&2; exit 1; }

dc() {
  docker compose -f "${COMPOSE_FILE}" --env-file "${ENV_FILE}" "$@"
}

artisan() {
  dc exec -T "${APP_SERVICE}" php artisan "$@"
}

[[ -f "${REPO_DIR}/artisan" ]] || die "REPO_DIR inválido (sem artisan): ${REPO_DIR}"
[[ -f "${REPO_DIR}/${COMPOSE_FILE}" ]] || die "Falta ${COMPOSE_FILE} em ${REPO_DIR}"
command -v docker >/dev/null 2>&1 || die "Docker não encontrado."
command -v git >/dev/null 2>&1 || die "Git não encontrado."

cd "${REPO_DIR}"

log "Pasta: ${REPO_DIR}"
[[ -f "${ENV_FILE}" ]] || die "Arquivo ${ENV_FILE} não encontrado. Abortando para não subir produção sem config."

# ── 1) Git ─────────────────────────────────────────────────────
if [[ "${DISCARD_LOCAL}" == "1" ]]; then
  warn "DISCARD_LOCAL=1 — descartando alterações locais de código (preserva ${ENV_FILE})"
  # Nunca use git clean -fdx (apagaria .env.production)
  git checkout -- . || true
  # Remove untracked que não sejam env / storage sensível
  while IFS= read -r line; do
    status="${line:0:2}"
    path="${line:3}"
    path="${path#"${path%%[![:space:]]*}"}"
    if [[ "${status}" == "??" \
      && "${path}" != ".env" \
      && "${path}" != ".env."* \
      && "${path}" != "${ENV_FILE}" \
      && "${path}" != "storage/"* ]]; then
      rm -rf -- "${path}" 2>/dev/null || true
    fi
  done < <(git status --porcelain 2>/dev/null || true)
fi

if ! git diff --quiet || ! git diff --cached --quiet; then
  warn "Há alterações locais. Se o pull falhar: DISCARD_LOCAL=1 ./atualize.sh"
  git status --short
fi

log "git fetch + pull --ff-only (${BRANCH})"
git fetch origin
git checkout "${BRANCH}"
git pull --ff-only origin "${BRANCH}"

# ── 2) Docker rebuild / up ─────────────────────────────────────
# NUNCA "down -v" — volume MySQL (parque_aquatico_prod_db) e storage são persistentes.
if [[ "${SKIP_BUILD}" == "1" ]]; then
  log "SKIP_BUILD=1 — recreate sem rebuild"
  dc up -d --force-recreate --remove-orphans
else
  log "docker compose -f ${COMPOSE_FILE} --env-file ${ENV_FILE} up -d --build"
  dc up -d --build --remove-orphans
fi

log "Aguardando container ${APP_SERVICE}..."
for i in $(seq 1 90); do
  if dc ps --status running --services 2>/dev/null | grep -qx "${APP_SERVICE}"; then
    break
  fi
  sleep 2
  if [[ "${i}" -eq 90 ]]; then
    die "Container ${APP_SERVICE} não subiu a tempo. Veja: docker compose -f ${COMPOSE_FILE} --env-file ${ENV_FILE} logs --tail=100 ${APP_SERVICE}"
  fi
done
# dá tempo do entrypoint.prod (migrate/caches no boot)
sleep 5
ok "Container em execução"

# ── 3) Pastas fiscais no volume de storage ─────────────────────
log "Garantindo pasta fiscal (XML NFS-e/NFC-e) no storage"
dc exec -T "${APP_SERVICE}" mkdir -p storage/app/private/fiscal || warn "Não foi possível criar storage/app/private/fiscal"

# ── 4) Artisan (pós-boot) ──────────────────────────────────────
if [[ "${SKIP_CACHE}" != "1" ]]; then
  log "Limpando caches artisan"
  artisan optimize:clear || warn "optimize:clear falhou (container ainda iniciando?)"
  artisan config:clear || true
  artisan route:clear || true
  artisan view:clear || true
  artisan cache:clear || true

  log "Recompondo caches de produção"
  artisan config:cache || warn "config:cache falhou"
  artisan route:cache || warn "route:cache falhou (rotas com closure?)"
  artisan view:cache || warn "view:cache falhou"
fi

if [[ "${SKIP_MIGRATE}" != "1" ]]; then
  log "Migrations (force)"
  artisan migrate --force || warn "migrate falhou — confira logs do app"
fi

# ── 5) Health check ────────────────────────────────────────────
if [[ "${SKIP_HEALTH}" != "1" ]]; then
  log "Health check http://127.0.0.1:${APP_PORT}/up"
  if command -v curl >/dev/null 2>&1; then
    if curl -fsS -o /dev/null -w "%{http_code}" "http://127.0.0.1:${APP_PORT}/up" | grep -Eq '^(200|204)$'; then
      ok "/up OK"
    else
      # Laravel health às vezes devolve 200 com body; tenta de novo sem filtrar código
      if curl -fsS "http://127.0.0.1:${APP_PORT}/up" >/dev/null 2>&1; then
        ok "/up OK"
      else
        warn "/up não respondeu — veja logs"
        dc logs --tail=80 "${APP_SERVICE}" || true
      fi
    fi
  else
    warn "curl não instalado — pulando health check"
  fi
fi

ok "Atualização concluída."
printf '\nComandos úteis:\n'
printf '  docker compose -f %s --env-file %s logs -f --tail=100 %s\n' "${COMPOSE_FILE}" "${ENV_FILE}" "${APP_SERVICE}"
printf '  docker compose -f %s --env-file %s ps\n' "${COMPOSE_FILE}" "${ENV_FILE}"
printf '  docker compose -f %s --env-file %s exec %s php artisan tinker\n' "${COMPOSE_FILE}" "${ENV_FILE}" "${APP_SERVICE}"
printf '\nNUNCA use: docker compose ... down -v  (apaga o MySQL de produção)\n\n'
