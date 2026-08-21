# Ampliar el enum a 6 monedas y añadir el set habilitado al agregado

## Contexto

Las máquinas reales aceptan también 0.50 y 2.00, y el operador puede habilitar/deshabilitar denominaciones. Hoy `CoinDenomination` tiene 4 casos fijos (`backend/src/VendingMachine/Domain/Money/CoinDenomination.php:21-24`) y el agregado no distingue "el hardware sabe leerla" de "esta máquina la tiene activa". Decisión de la sesión PM (spec, K1-K4): el enum crece a 6 (capacidad hardware) y nace un **set habilitado por máquina** como estado del agregado; deshabilitada = fuera del todo (ni ranura, ni till, ni cambio); de fábrica se habilitan las 4 del enunciado, así que los 3 ejemplos literales siguen verdes.

## Criterios de aceptación

- [ ] `CoinDenomination` tiene `FIFTY_CENTS = 50` y `TWO_UNITS = 200`; `isDispensableAsChange()` (`CoinDenomination.php:40-45`) responde `true` para 0.50 y `false` para 2.00 (el match sigue siendo exhaustivo).
- [ ] `VendingMachine` lleva el set habilitado como campo (junto a `changeReserve`, `VendingMachine.php:64`); `provision()` y `service()` (`:192`) lo reciben; `insert()` lanza un error de dominio nuevo y nombrado cuando la moneda está deshabilitada (distinto de `UnsupportedCoin`: "el hardware no la lee" ≠ "está apagada").
- [ ] El pool de cambio excluye las deshabilitadas: una moneda deshabilitada que quedara dentro jamás sale como cambio (semántica K3), y `requiresExactChange()` (`:176`) se calcula sobre dispensables∩habilitadas.
- [ ] `service()` con reserva que carga una denominación deshabilitada (count>0) lanza error de dominio nombrado — el borde lo convertirá en 422 (ticket 03).
- [ ] Los tests unitarios del contraejemplo greedy (0.30 con {0.25, 0.10×3}) siguen pasando; el set {5,10,25,50,100} lo mantiene válido.
- [ ] Los mutantes suprimidos de `backend/infection.json5:64-71` revisados: su comentario dice que la supresión EXPIRA si entra una denominación no canónica — decidir si {5,10,25,50} sigue siendo canónico y documentarlo en el propio json5.
- [ ] `make test-mutation` con MSI 100 % sobre el nuevo código.
- [ ] ADR nuevo escrito en este mismo commit.

## Capa

domain

## Repo

icar-vending-machine

## Archivos probablemente afectados

- `backend/src/VendingMachine/Domain/Money/CoinDenomination.php:21-45` — 2 casos nuevos + match
- `backend/src/VendingMachine/Domain/Machine/VendingMachine.php:64,131,176,192` — campo nuevo, firma de `provision()`/`service()`, filtro del pool en `purchase()`, `requiresExactChange()`
- `backend/src/VendingMachine/Domain/Money/CoinCollection.php:108` — la costura del filtro (dispensable + habilitada; el cómo exacto lo decide el implementador)
- `backend/src/VendingMachine/Domain/Exception/` — 2 excepciones nuevas (insertar deshabilitada; cargar deshabilitada en till) implementando `VendingMachineError`
- `backend/src/VendingMachine/Domain/Dispensing/{Optimal,Greedy}ChangeStrategy.php:37,31` — si el filtro cambia de sitio
- `backend/tests/Unit/...` — tests nuevos por regla
- `backend/infection.json5:64-71` — revisión de supresiones
- `docs/adr/0018-<slug>.md` (a crear) — la decisión enum-6 + enabled set, con alternativas (data-driven, solo-añadir) y consecuencia negativa real

## Enfoque sugerido

1. Rojo: tests unitarios de cada regla nueva (insertar deshabilitada, cargar till deshabilitada, cambio nunca en deshabilitada, `requiresExactChange` con habilitadas sin dispensables, default de `provision`).
2. Enum + match exhaustivo (PHPStan obligará a contestar por cada caso nuevo — es la demo del README §"A new coin" vivida).
3. Campo + firmas + reglas en el agregado, compute-then-commit intacto.
4. Revisar infection.json5 y escribir el ADR.

(No prescriptivo — el implementador puede divergir si encuentra mejor camino.)

## ADR asociado

Sí — crear `docs/adr/0018` sobre "hardware capability enum + per-machine enabled set". Es LA decisión de la épica.

## Depende de

—

## Prioridad sugerida

alta — todo lo demás depende de este modelo.

## Notas y referencias

- Spec: [../../../docs/specs/2026-08-21-configurable-coins-and-catalogue-design.md](../../../docs/specs/2026-08-21-configurable-coins-and-catalogue-design.md) (decisiones K1-K4, riesgos).
- Patrón canónico: `VendingMachine.php` y su compute-then-commit; no mutar nada antes de que todos los checks pasen.
- OJO: este ticket NO toca persistencia ni HTTP — el agregado en memoria con su InMemory repo basta para todos los tests de este nivel.

## Origen

Sesión PM de 2026-08-21 — spec `docs/specs/2026-08-21-configurable-coins-and-catalogue-design.md`
