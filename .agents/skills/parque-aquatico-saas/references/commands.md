# Comandos e validação

## Ambiente Docker preferido

O Compose publica o painel em `http://localhost:9091`, o phpMyAdmin em `http://localhost:9092` e o MySQL somente em `127.0.0.1:3308`.

```powershell
docker compose up -d --build
docker compose ps
docker compose logs app
docker compose exec app php artisan about
```

Não execute `docker compose down -v` sem autorização explícita: `-v` remove os volumes persistentes do banco e uploads.

## Validação de código

Escolha o menor conjunto que cobre a mudança:

```powershell
docker compose exec app php artisan test
docker compose exec app php artisan test --filter ContratoMensalidadeTest
docker compose exec app php artisan route:list
docker compose exec app php artisan config:show parque
docker compose exec app ./vendor/bin/pint --test
npm run build
```

Os testes exigem MySQL/MariaDB e usam `.env.testing`; não habilite SQLite no `phpunit.xml`. Se o banco de teste ainda não existir, crie `parque_aquatico_test` antes, conforme `README.md`.

## Checklist por tipo de mudança

- PHP isolado: lint/suite direcionada e Pint.
- Service ou regra de domínio: teste Feature do caminho feliz, rollback/falha e idempotência quando aplicável.
- Tenant ou autorização: teste com duas empresas e usuário sem permissão.
- Migration: migrate em MySQL suportado, teste de constraints/índices e rollback quando seguro.
- Blade/Tailwind/JS: `npm run build` e inspeção do fluxo afetado.
- Rota/API: `route:list`, autenticação, Policy, validação e formato da resposta.
- Job/webhook/scheduler: repetição segura, tenant explícito, falha externa e execução em fila.
- Docker/configuração: `docker compose config` e health/status dos serviços.

Não rode seed de demonstração, migrations destrutivas, limpeza de banco, remoção de volumes ou chamadas reais a integrações de produção apenas para validar uma alteração.
