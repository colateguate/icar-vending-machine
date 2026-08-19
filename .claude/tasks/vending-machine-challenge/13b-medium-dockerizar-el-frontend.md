# Añadir el servicio de frontend al stack de docker-compose

## Contexto
El ticket 13 contenerizó el backend, que era la mitad que podía hacerse: el panel React no existía todavía (depende del 17) y `docker-compose.yml` quedó con un único servicio. Falta la otra mitad para que `make up` deje al evaluador con la API **y** el panel funcionando sin instalar Node. Sin esto, quien evalúe puede usar la API y el CLI, pero para ver la pantalla tiene que instalarse Node y arrancar Vite a mano — que es exactamente la fricción que el enunciado agradece que se elimine.

## Criterios de aceptación
- [ ] `frontend/Dockerfile` multi-stage: `npm ci` + `npm run build` (Vite) → estáticos servidos por nginx o equivalente
- [ ] `VITE_API_URL` como build-arg, porque Vite congela las variables en tiempo de build y no de arranque
- [ ] Servicio `frontend` en `docker-compose.yml`, con `depends_on` del backend condicionado a su healthcheck (`condition: service_healthy`, que el backend ya expone)
- [ ] `frontend/.dockerignore` con `node_modules/` y `dist/`
- [ ] CORS: el origen del contenedor de frontend entra en `CORS_ALLOW_ORIGIN` del backend, o se sirve todo tras el mismo origen — decidir y documentar cuál
- [ ] `make up` levanta los dos servicios y el panel carga el estado de la máquina
- [ ] Verificación activa: `docker compose up` real, compra completa desde el navegador contra el stack contenerizado, consola sin errores, evidencia pegada

## Capa
infra, frontend

## Archivos probablemente afectados
- `frontend/Dockerfile`, `frontend/.dockerignore` (a crear)
- `docker-compose.yml` — servicio nuevo junto al de backend
- `frontend/vite.config.js` (creado en el ticket 15) — comprobar que `base` y el proxy de desarrollo no estorban al build de producción
- `backend/.env` — `CORS_ALLOW_ORIGIN` si el panel se sirve desde otro origen
- `Makefile` — `make up` ya existe; comprobar que sigue valiendo para dos servicios

## Enfoque sugerido
1. Dockerfile del frontend con dos etapas: `node:22-alpine` para construir, `nginx:alpine` para servir.
2. Decidir el origen: si nginx hace de proxy inverso hacia el backend, el panel y la API comparten origen y el CORS deja de existir como problema. Si no, ampliar `CORS_ALLOW_ORIGIN`.
3. Añadir el servicio al compose y comprobar el arranque en frío desde cero (`docker compose down -v` antes).

(No prescriptivo — el implementador puede divergir si encuentra mejor camino.)

## ADR asociado
No — decisión operativa. Si se opta por el proxy inverso en vez de CORS, merece dos líneas en `docs/assumptions.md`, no un ADR.

## Depende de
13, 17

## Prioridad sugerida
media — imprescindible para la entrega completa, irrelevante para el desarrollo del backend.

## Notas y referencias
- El backend ya expone `GET /api/health` y su servicio de compose declara el healthcheck; `depends_on: condition: service_healthy` funciona sin tocar nada del backend.
- `nelmio_cors.yaml` está acotado a `^/api/` y lee `CORS_ALLOW_ORIGIN` como regex; ampliarlo es una variable de entorno, no código.
- El ticket 15 reserva `frontend/e2e/` para Playwright: si algún día hay E2E, este contenedor es contra el que correrían.

## Origen
Desglose del ticket 13 — su parte de frontend dependía del ticket 17, que no existía cuando se hizo la del backend.
