---
name: fix-bug
description: Úsala cuando el usuario reporte un bug ("X falla", "X devuelve mal el cambio", "el test Y rompe"), pida un refactor ("limpia X", "extrae Y"), un cambio trivial (typo, rename, 1-línea), o pida implementar un ticket de bug de `.claude/tasks/`. Lee el ticket, reproduce, escribe el test que habría capturado el bug, aplica el fix mínimo y verifica la corrección antes de cerrar. Para FUNCIONALIDAD NUEVA usa `implement-feature` en su lugar.
---

# Flujo de corrección de bugs

Violar la letra de las fases es violar el espíritu. No hay "fast path".

## Fase 0 — Clasificar

| Clasificación | Cuándo | Fases |
|---|---|---|
| `bug` | Comportamiento incorrecto observable | 1–6 |
| `refactor` | Mismo comportamiento, mejor forma | 2 (los tests existentes son la red), 3, 5 |
| `trivial` | Typo, rename, 1-línea sin lógica | 3, 5 local |

Si viene de ticket: **léelo entero** (`.claude/tasks/…`). Sus criterios de aceptación definen "corregido". Si el ticket contradice lo que ves en el código, pregunta antes de tocar nada. Si vas a tocar >3 archivos productivos, propón partir con `create-ticket`.

**Antes de tocar un solo fichero, abre la rama.** `git checkout release/backend && git pull && git checkout -b fix/<slug>` (ver `CLAUDE.md` § Branching model). Nunca commitees sobre `main` ni sobre la release.

## Fase 1 — Reproducir (solo bugs)

Localiza la capa donde vive la causa raíz — no donde se manifiesta el síntoma:

- **Dominio**: reprodúcelo con un test unitario directo sobre el agregado/VO (el más barato y el más probable en este repo: cálculo de cambio, invariantes, escrow).
- **Aplicación**: handler con repo InMemory.
- **Delivery/contrato**: `curl` contra el stack levantado, pegando petición y respuesta.
- **Frontend**: reproducir en navegador y anotar los pasos.

Si no puedes reproducir con la información dada, **pregunta al usuario por pasos antes de adivinar**. Escribe en una línea la causa raíz identificada; si solo tienes hipótesis, dilo y verifícala con la reproducción antes de seguir.

## Fase 2 — El test que faltaba (rojo)

Escribe **el test que habría capturado este bug**, al nivel donde vive la causa raíz. Si ya hay tests en la zona, identifica qué assertion falta y añádela; si no los hay, créalos ahora — no después del fix. **Ejecuta y pega el output confirmando que falla por la razón esperada** (el mensaje de fallo debe describir el bug, no un error de setup).

### Tabla de reclasificación de seguridad

Si el síntoma casa con una fila, el bug es de seguridad aunque parezca funcional: el test de Fase 2 debe incluir el payload hostil, y la Fase 6 debe grepear el mismo patrón en el resto del código.

| Síntoma | OWASP | Implicación |
|---|---|---|
| Importe/cambio calculado mal solo con ciertos valores | A04 Insecure Design | ¿Float en aritmética monetaria? Critical por regla del repo |
| Input raro rompe el endpoint (500 en vez de 4xx) | A03 Injection / A04 | Falta validación en el borde; ¿el VO permite lo inválido? |
| Respuesta de error muestra rutas, trazas o SQL | A05 Misconfiguration | El catálogo problem+json debe suprimir detalle en prod |
| Estado inconsistente tras petición doble/repetida | A04 | Falla el compute-then-commit o el optimistic locking |
| Algo accesible que no debería (p.ej. SERVICE sin control) | A01 Broken Access Control | Revisar la superficie completa, no solo el endpoint reportado |

## Fase 3 — Fix mínimo

Cambia **solo** lo necesario para que el test de Fase 2 pase. NO refactorices código adyacente, no renombres, no "aproveches para". Cada línea del diff debe ser atribuible al bug. Anti-patrones que veas por el camino → Fase 6, como sugerencia de ticket.

## Fase 4 — Verificar la corrección

Ambas cosas, con evidencia pegada:

1. El test de Fase 2 ahora **pasa** (output).
2. La reproducción original de Fase 1 ya **no** reproduce (mismo método: si fue curl, curl de nuevo; si fue navegador, navegador de nuevo).

Un fix cuyo test pasa pero cuya reproducción original no se ha re-ejecutado **no está verificado**.

## Fase 5 — Regresión

`make qa` completo (o los binarios desde `backend/`): la suite entera, no solo el archivo tocado. Counts exactos. Si el fix tocó dominio, `make test-mutation` — un bug que llegó a existir es evidencia de un hueco en los tests de esa zona.

## Fase 6 — Impacto

- **Bug**: `grep` de la misma causa raíz en el resto del código (¿otro sitio hace la misma aritmética? ¿otro endpoint omite la misma validación?). Si la tabla de seguridad aplicó, grep del patrón hostil en toda la superficie.
- **Refactor**: `grep` de todos los consumidores de lo movido/renombrado.
- **Trivial**: omitible.

Hallazgos laterales → sugiere `/create-ticket` con `Origen: Detectado durante fix-bug de "<bug>"`. NO los arregles aquí.

## Cierre

**Revisa `documentation/`** antes de cerrar: si el bug nacía de algo que esos apuntes explican mal, o si ya no describen el código, corrígelo — una doc que contradice al código es peor que no tenerla. Y si la causa raíz fue una trampa que costó descubrir, cuéntala: son las que más valen. Mueve el ticket a `.claude/completed_tasks/` si venía de ticket. Commit atómico Conventional (`fix(domain): ...`) en la rama de Fase 0. **Antes de push: `review-before-push`**; con PASS, `git push -u origin <rama>` y el usuario abre el PR hacia `release/backend`. Informe numerado: (1) clasificación y causa raíz en una línea, (2) test añadido y a qué nivel, (3) diff mínimo (archivos:líneas), (4) evidencia de Fase 4 (ambas), (5) regresión con counts, (6) hallazgos laterales y lo que quedó **sin** hacer, explícitamente.

## Red flags — STOP

| Pensamiento | Realidad | Acción |
|---|---|---|
| "El fix es obvio, no necesita test rojo" | Si es tan obvio, el test toma 2 minutos. Sin él, el bug vuelve. | Fase 2. |
| "Ya que estoy aquí, limpio esta función" | Diff contaminado: el reviewer no distingue fix de ruido. | Ticket aparte. |
| "El test pasa, cierro" | El test puede pasar por razón equivocada. | Fase 4.2: re-ejecuta la reproducción original. |
| "Es solo un bug de redondeo" | En este dominio, redondeo = dinero = posible Critical. | Tabla de seguridad de Fase 2. |
| "Reporto en positivo lo que sí hice" | Omitir lo no hecho es información que el usuario necesita antes de mergear. | En Cierre, lista explícita de lo NO hecho. |
