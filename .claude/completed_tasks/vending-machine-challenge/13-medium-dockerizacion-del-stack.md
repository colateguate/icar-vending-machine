# Containerizar el backend con docker-compose

## Contexto
El enunciado aprecia explícitamente Dockerfile/docker-compose para evaluación fácil. Objetivo: `make up` y el evaluador tiene la API y el CLI funcionando sin instalar PHP.

**Alcance recortado a propósito**: la mitad de frontend dependía del ticket 17, que no existía, así que se movió al ticket 13b tal y como anticipaban las notas. SQLite en volumen — sin servicio de base de datos que levantar.

## Criterios de aceptación
- [ ] `backend/Dockerfile` multi-stage: composer install (sin dev) → runtime PHP-FPM o FrankenPHP
- [ ] `docker-compose.yml` en la raíz: servicio de backend, healthcheck contra /api/health, volumen nombrado para `backend/var/data`
- [ ] Entrypoint del backend corre migraciones + `app:machine:provision` idempotente
- [ ] `make up` levanta el backend; API y CLI usables de punta a punta contra el contenedor
- [ ] `.dockerignore` para vendor/node_modules/var
- [ ] Verificación activa: `docker compose up` real + curl al health + compra completa vía API, evidencia pegada

## Capa
infra

## Archivos probablemente afectados
- `backend/Dockerfile`, `docker-compose.yml`, `.dockerignore` (a crear)
- `Makefile` — target `up` real
- `backend/docker/entrypoint.sh` (a crear)

## Enfoque sugerido
1. Backend primero (la API sola ya sirve al evaluador).
2. APP_ENV=prod en la imagen final, APP_DEBUG=0 — el security-reviewer lo comprueba.

(No prescriptivo — el implementador puede divergir si encuentra mejor camino.)

## ADR asociado
No — decisión operativa; el porqué de SQLite ya vive en ADR-0008.

## Depende de
11

## Prioridad sugerida
media — imprescindible para la entrega, no para el desarrollo.

## Notas y referencias
- Se adelantó al 17 dejando solo el servicio de backend; el de frontend es el ticket 13b.

## Origen
Desglose de backlog — sesión PM de 2026-08-18.
