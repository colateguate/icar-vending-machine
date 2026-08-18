# Containerizar el stack (backend + frontend) con docker-compose

## Contexto
El enunciado aprecia explícitamente Dockerfile/docker-compose para evaluación fácil. Objetivo: `make up` y el evaluador tiene la API y el panel funcionando sin instalar PHP ni Node. SQLite en volumen — sin servicio de base de datos que levantar.

## Criterios de aceptación
- [ ] `backend/Dockerfile` multi-stage: composer install (sin dev) → runtime PHP-FPM o FrankenPHP
- [ ] `frontend/Dockerfile`: build Vite → estáticos servidos (nginx o similar)
- [ ] `docker-compose.yml` en la raíz: 2 servicios, healthcheck del backend contra /api/health, volumen nombrado para `backend/var/data`
- [ ] Entrypoint del backend corre migraciones + `app:machine:provision` idempotente
- [ ] `make up` levanta todo; panel accesible y compra funcional de punta a punta
- [ ] `.dockerignore` para vendor/node_modules/var
- [ ] Verificación activa: `docker compose up` real + curl al health + compra vía panel, evidencia pegada

## Capa
infra

## Archivos probablemente afectados
- `backend/Dockerfile`, `frontend/Dockerfile`, `docker-compose.yml`, `.dockerignore` (a crear)
- `Makefile` — target `up` real
- `backend/docker/entrypoint.sh` (a crear)

## Enfoque sugerido
1. Backend primero (la API sola ya sirve al evaluador).
2. APP_ENV=prod en la imagen final, APP_DEBUG=0 — el security-reviewer lo comprueba.
3. Frontend con VITE_API_URL parametrizada en build-arg.

(No prescriptivo — el implementador puede divergir si encuentra mejor camino.)

## ADR asociado
No — decisión operativa; el porqué de SQLite ya vive en ADR-0008.

## Depende de
11, 17

## Prioridad sugerida
media — imprescindible para la entrega, no para el desarrollo.

## Notas y referencias
- Puede adelantarse a 17 dejando solo el servicio backend y añadiendo frontend después.

## Origen
Desglose de backlog — sesión PM de 2026-08-18.
