# Contrato: service gestiona monedas, supportedCoins en el estado, errores nuevos

## Contexto

Con dominio (01) y persistencia (02) listos, la API tiene que exponer la gestión. Decisiones K3/K6 del spec: `acceptedCoins` congela su significado (solo habilitadas — los botones del panel no cambian, `frontend/src/components/CoinButtons.jsx:15-34`); nace `supportedCoins` con las 6 y su flag `enabled` para el formulario; cargar en till una deshabilitada es 422. Hoy el estado publica el enum crudo (`GetMachineStateHandler.php:31`), el request de service no conoce flags (`ServiceMachineRequest.php:87`, `changeReserveIn`) y el enum del contrato OpenAPI lista 4 valores (`docs/openapi.yaml:607`).

## Criterios de aceptación

- [ ] `PUT /api/machine/service` acepta la gestión de monedas — forma exacta del payload decidida aquí en Fase 1 (propuesta del spec: la fila del till gana `enabled`; recordar `additionalProperties: false` — el formulario actual borra campos extra a propósito, `ServiceDrawer.jsx:102-104`).
- [ ] Cargar count>0 de una deshabilitada → 422 con code nuevo en `ErrorCatalog` (`ErrorCatalog.php:43`); insertar una moneda deshabilitada por `POST /coins` → 422 con su propio code (distinto de `unsupported_coin`, que sigue siendo "el hardware no la lee").
- [ ] `GET /api/machine` publica `supportedCoins` (las 6 con `enabled` y `dispensableAsChange`) y `acceptedCoins` pasa a ser solo las habilitadas (`GetMachineStateHandler.php:31`, `MachineStateView.php:38`, `MachineStateResponse.php:45-57`).
- [ ] `docs/openapi.yaml` actualizado: enum `Denomination` (`:607`) con los 6 valores, schema del estado con `supportedCoins`, errores nuevos documentados — los dos tests bidireccionales (catálogo↔contrato) en verde.
- [ ] Los 3 ejemplos del enunciado pasan **sin modificar** (aceptación HTTP + CLI, output literal) — el default de fábrica los protege; este ticket lo demuestra.
- [ ] Aceptación nueva: habilitar 0.50 por service → aparece en `acceptedCoins` → una compra con 0.50 de sobrante la devuelve como cambio; deshabilitarla → insertarla da el 422 nuevo.
- [ ] El CLI `app:machine:run` con `"0.50"` sobre la máquina de fábrica (0.50 deshabilitada) responde con el error nuevo, no con `unsupported_coin`.

## Capa

application, delivery

## Repo

icar-vending-machine

## Archivos probablemente afectados

- `backend/src/VendingMachine/Delivery/Http/Request/ServiceMachineRequest.php:51-98` — leer el flag, validar duplicados/deshabilitadas con ruta de campo
- `backend/src/VendingMachine/Application/Command/ServiceMachine/*` y `ProvisionMachine/*` — el comando transporta el set (primitivas)
- `backend/src/VendingMachine/Application/Query/GetMachineState/{GetMachineStateHandler.php:31,MachineStateView.php}` — habilitadas vs soportadas
- `backend/src/VendingMachine/Delivery/Http/Response/MachineStateResponse.php:29-57` — `supportedCoins` en el wire
- `backend/src/VendingMachine/Delivery/Http/Error/ErrorCatalog.php:43-45` — filas nuevas
- `backend/src/VendingMachine/Delivery/Cli/ProvisionMachineCommand.php:53` — la reserva de fábrica no cambia (las 4), pero la firma del comando sí
- `docs/openapi.yaml:607` + ejemplos (`:125-192`)
- `backend/tests/Acceptance/` + `backend/tests/Application/`

## Enfoque sugerido

1. Fase 1 de implement-feature: diseñar la forma del payload (¿flag en fila del till, o lista `enabledCoins` aparte?) y los nombres de los codes — es la decisión de contrato de este ticket.
2. Rojo: aceptación de los criterios (incluidos los 3 ejemplos intactos como no-regresión explícita).
3. Cadena request→command→handler→respuesta; el borde valida forma, los VOs el resto.

(No prescriptivo.)

## ADR asociado

No — la decisión de modelo quedó en el ADR-0018 (ticket 01); esto la expone aplicando ADR-0012 (catálogo de errores) y ADR-0015 (OpenAPI testeado).

## Depende de

02

## Prioridad sugerida

alta — desbloquea los dos tickets de panel.

## Notas y referencias

- Spec: [../../../docs/specs/2026-08-21-configurable-coins-and-catalogue-design.md](../../../docs/specs/2026-08-21-configurable-coins-and-catalogue-design.md) (K6 y riesgo `additionalProperties`).
- Regla de status del repo: 422 = input inválido en sí; el "insertar deshabilitada" es 422 (mandaste una moneda que esta máquina tiene apagada), no 409 — si el implementador discrepa, que lo argumente contra `ErrorCatalog.php:29-40` y lo traiga a revisión.
- Hallazgo de la review de seguridad del backlog: este endpoint sin auth + monedas conmutables = **DoS con un curl** (deshabilitarlas todas). Aceptado dentro del trade-off documentado (lo registra el ADR-0018, ticket 01). Existe una guardia barata opcional — rechazar con 422 un payload que deje cero denominaciones habilitadas — que NO está decidida: si el usuario la quiere, se añade aquí como criterio; no la implementes por iniciativa propia.

## Origen

Sesión PM de 2026-08-21 — spec `docs/specs/2026-08-21-configurable-coins-and-catalogue-design.md`
