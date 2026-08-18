---
name: implement-feature
description: Úsala cuando el usuario pida añadir funcionalidad nueva ("añade X", "implementa el endpoint Y", "crea el agregado Z"), o implementar un ticket de feature de `.claude/tasks/`. Flujo TDD estricto con verificación activa antes de entregar. Cubre dominio, aplicación, delivery, infraestructura y frontend. Para BUGS, refactors o cambios triviales usa `fix-bug` en su lugar. Antes de hacer push, el cierre exige `review-before-push`.
---

# Flujo TDD para funcionalidad nueva

Violar la letra de las fases es violar el espíritu. No hay "fast path".

## Fase 0 — Clasificar el alcance

| Clasificación | Cuándo | Fases |
|---|---|---|
| `domain-only` | Nueva regla de negocio, VO, invariante del agregado — sin tocar HTTP | 1–7 (F5 solo `make qa` + mutation) |
| `vertical-slice` | Caso de uso completo: dominio → comando/consulta → endpoint | 1–7 completas |
| `frontend-only` | Componente o interacción del panel React | 1–7 (tests con Vitest+RTL; F5 = navegador) |
| `lite` | Cambio pequeño en capa única ya cubierta por tests | 1 mínima, 2, 3, 5 local, 7 |

**Anuncia la clasificación al usuario al arrancar.** Si el ticket viene de `.claude/tasks/`, léelo entero primero; sus criterios de aceptación son el contrato mínimo.

**Antes de tocar un solo fichero, abre la rama.** `git checkout release/backend && git pull && git checkout -b feat/<slug>` (ver `CLAUDE.md` § Branching model). Commitear sobre `main` o sobre la release directamente es una violación del flujo, no un atajo.

**Sub-tarea split:** si vas a tocar >5 archivos productivos, propón partir en tickets encadenados con `create-ticket` antes de seguir.

## Fase 1 — Diseño de contrato

Antes de escribir un test, deja por escrito (en el chat, breve):

1. **Capa(s) afectada(s)** y en qué dirección fluyen las dependencias. Autoridad: `CLAUDE.md` § Backend architecture. Si necesitas un puerto nuevo, el interface lo declara el **consumidor** (Domain o Application), jamás Infrastructure.
2. **Mensaje**: ¿comando o consulta? Nombre (`<Verbo><Sustantivo>Command` + handler en `Application/Command/<UseCase>/`). Payload en **primitivas**. ¿Devuelve algo? Solo si el resultado físico no es recuperable por consulta posterior (regla de `CLAUDE.md`).
3. **Contrato HTTP** si hay endpoint: método, ruta bajo `/api`, forma del request/response (dinero como string decimal), y qué errores del `ErrorCatalog` puede producir (422/409/404).
4. **¿Merece ADR?** Si la feature toma una decisión con alternativas reales → el ADR se escribe en Fase 7, en el mismo commit. Si solo aplica decisiones ya tomadas, dilo explícitamente.
5. Decisiones abiertas → `AskUserQuestion` ANTES de Fase 2.

> **Consulta opt-in: `/clean-ddd-hexagonal`** — solo si la feature introduce un patrón táctico nuevo (nuevo agregado, evento con consumidor, saga). Para extender lo existente, el patrón canónico del repo basta: `VendingMachine.php` y `PurchaseProductHandler.php`.

## Fase 2 — Tests primero (rojo)

Escribe TODOS los tests del happy path **al nivel correcto** antes de tocar implementación:

- Regla de negocio → `tests/Unit/` sobre el agregado/VO directamente, sin kernel.
- Orquestación del caso de uso → `tests/Application/` con repo **InMemory** y spy del event bus.
- Adaptador nuevo → `tests/Integration/` (si es un repositorio: extiende el contract test abstracto para que corra contra ambos adaptadores).
- Contrato HTTP → `tests/Acceptance/Http/` a través del kernel real.
- Frontend → Vitest + Testing Library sobre el componente.

Un test de regla de negocio que arranca el kernel está en el nivel equivocado: bájalo.

**Confirma rojo en TODOS y pega el output.** Si algún test pasa sin implementación, el test está mal escrito — arréglalo antes de seguir.

## Fase 3 — Rebanada vertical mínima (verde)

Implementa el happy path en orden **dominio → aplicación → infraestructura → delivery → frontend**. Convenciones always-on: `declare(strict_types=1)` · VOs `final readonly` sin setters · sin atributos Symfony/ORM fuera de Delivery/Infrastructure · handler registrado por `_instanceof`, ruta en `config/routes/api.yaml`, mapping en XML · dinero solo en céntimos enteros.

## Fase 4 — Casos límite (TDD iterativo)

Para cada caso: **test rojo nuevo → implementación → verde** antes del siguiente. Lista canónica del dominio: moneda no soportada · selector desconocido · sin stock · fondos insuficientes · **no se puede dar cambio** · compra concurrente (si toca persistencia) · payload malformado (si hay endpoint).

## Fase 5 — Verificación activa (antes de declarar nada)

Declarar "funciona" sin output pegado es una violación de esta fase.

| Qué | Cómo | Evidencia exigida |
|---|---|---|
| Gates de calidad | `make qa` (o los binarios directos desde `backend/`) | Salida completa en verde: PHPUnit + PHPStan max + Deptrac + cs-fixer |
| Endpoint real | Stack levantado (`make up` o server local) + `curl` con el payload real | Petición y respuesta JSON pegadas, código HTTP incluido |
| Caso de error real | `curl` que provoque al menos un error del catálogo | El `problem+json` pegado con su status |
| Dominio tocado | `make test-mutation` | MSI reportado; matar los mutantes escapados que señalen asserts débiles |
| Frontend tocado | Abrir el panel en navegador y ejercitar la interacción | Descripción de lo observado + consola sin errores |

Si algo inesperado aparece aquí, vuelve a Fase 4 (caso límite nuevo) o a Fase 1 (el contrato estaba mal).

## Fase 6 — Regresión

Suite completa (`make test`), no solo lo tocado. Reporta counts exactos ("84/84 verde"). Si algo falla, **no** declares la feature terminada — arregla o reporta el fallo con su output.

## Fase 7 — Cierre

1. Escribe/actualiza el **ADR** decidido en Fase 1 (inglés, MADR, con alternativa rechazada y consecuencia negativa real).
2. Mueve el ticket a `.claude/completed_tasks/` (si venía de ticket).
3. Commit atómico Conventional en inglés (`feat(domain): ...`) **en la rama de Fase 0**. El git log es entregable evaluado.
4. **Antes de push: invoca `review-before-push`.** Con KO no se pushea. Con PASS, `git push -u origin <rama>` y **dile al usuario que abra el PR** hacia `release/backend` — tú no abres ni mergeas PRs.
5. Anti-patrones detectados por el camino: NO los arregles de paso — pregunta al usuario y sugiere `/create-ticket` con `Origen` rellenado.

Informe final numerado: (1) clasificación aplicada, (2) contrato implementado, (3) archivos con líneas, (4) tests por nivel con counts, (5) evidencia de verificación activa (comandos + outputs), (6) resultado de regresión, (7) ADR escrito o "no aplica" razonado, (8) hallazgos laterales.

## Red flags — STOP y reclasifica

| Pensamiento | Realidad | Acción |
|---|---|---|
| "El test lo escribo después, la implementación es obvia" | Tests-after contestan "¿qué hace?" en vez de "¿qué debe hacer?". | Borra la implementación. Fase 2. |
| "Pasa PHPUnit, ya está verificado" | Los tests no ejercitan el stack real ni el contrato JSON. | Fase 5 completa con curl y output pegado. |
| "El test de dominio lo hago por HTTP, así cubro dos cosas" | Nivel equivocado: lento, frágil, y no localiza el fallo. | Test unitario sobre el agregado + acceptance solo del contrato. |
| "PHPStan/Deptrac los paso al final del día" | Cada commit debe ser verde. Un rojo acumulado contamina el log evaluado. | `make qa` antes de cada commit. |
| "Esto necesita un VO nuevo... y ya que estoy, tres más" | Saber dónde parar es parte de lo evaluado. | Solo VOs con invariante real. Duda → pregunta. |
| "El ADR lo escribo al final del proyecto" | El final no llega y la entrevista es el entregable. | ADR en el mismo commit que la decisión. |
| "Es pequeño, hago push sin review" | El veredicto PASS/KO existe para todos los push. | `review-before-push` siempre. |
