# Montar el esqueleto mínimo de Symfony con endpoint de health

## Contexto
`backend/` solo tiene el árbol de carpetas. Hay que instalar Symfony **skeleton** (no webapp-pack: servimos solo JSON, sin Twig) y dejar el kernel arrancando con un único endpoint de salud. Es el primer contacto real con Symfony del proyecto: cuanto más pequeña la superficie, más defendible.

## Criterios de aceptación
- [ ] `backend/composer.json` con PSR-4 `App\` → `src/` y solo las dependencias mínimas (framework-bundle, runtime, dotenv, yaml)
- [ ] `backend/src/Kernel.php`, `backend/public/index.php`, `backend/bin/console` operativos
- [ ] `GET /api/health` responde `200 {"status":"ok"}` — verificado con curl real y output pegado
- [ ] Rutas declaradas en `backend/config/routes/api.yaml` (NO atributos `#[Route]`)
- [ ] `.env` con `APP_ENV=dev` y `DATABASE_URL` sqlite apuntando a `var/data/machine.db`; `.env.test` con sqlite `:memory:`
- [ ] `grep -r "Symfony" backend/src/VendingMachine/Domain backend/src/VendingMachine/Application` → 0 hits

## Capa
delivery | infra

## Archivos probablemente afectados
- `backend/composer.json` (a crear)
- `backend/src/Kernel.php` (a crear)
- `backend/public/index.php`, `backend/bin/console` (a crear)
- `backend/config/services.yaml`, `backend/config/routes.yaml`, `backend/config/routes/api.yaml`, `backend/config/packages/framework.yaml` (a crear)
- `backend/src/VendingMachine/Delivery/Http/Controller/HealthController.php` (a crear)

## Enfoque sugerido
1. `composer create-project symfony/skeleton` en dir temporal y fusionar con el árbol existente (create-project exige carpeta vacía), o componer `composer.json` a mano (~30 líneas) y `composer install`.
2. `services.yaml`: autowire/autoconfigure ON, **excluir** `src/*/Domain/` y `src/Shared/Domain/` del autoregistro.
3. Controlador invocable `HealthController::__invoke` como primer habitante de `Delivery/Http/`.

(No prescriptivo — el implementador puede divergir si encuentra mejor camino.)

## ADR asociado
Sí — crear `docs/adr/0001-hexagonal-architecture-symfony-at-the-edge.md` y `docs/adr/0002-single-bounded-context-layers-inside.md`.

## Depende de
01

## Prioridad sugerida
alta — bloquea todo el backend.

## Notas y referencias
- Symfony Flex generará `config/packages/*` al requerir paquetes: revisar cada receta antes de committear.
- Autoridad de capas: `CLAUDE.md` § "Backend architecture — the dependency rule".

## Origen
Desglose de backlog — sesión PM de 2026-08-18.
