---
name: review-before-push
description: Úsala SIEMPRE antes de hacer push de una rama ("revisa antes de subir", "¿puedo pushear?", "lanza los revisores"), o cuando implement-feature/fix-bug lleguen a su cierre. Calcula el diff, lanza en paralelo los cuatro agentes revisores que correspondan a lo que el diff toca (roster de backend o de frontend) y sintetiza un único veredicto PASS/KO. NO la uses para revisar PRs ya abiertos en GitHub ni para reviews conceptuales sin diff.
---

# Revisión pre-push con veredicto agregado

Cuatro lentes independientes sobre el mismo diff, un solo veredicto. Con KO no se pushea.

## Fase 1 — Preparar el diff

**Base de comparación inflexible: la rama de release, NUNCA `main`.** Estás en una `feat/*`, `fix/*` o `chore/*` cortada de `release/backend` (ver `CLAUDE.md` § Branching model); lo que se revisa es lo que esa rama añade sobre su release.

1. `git fetch origin` y luego `git diff release/backend...HEAD` (con `--stat` primero para mostrar alcance). Si la release en curso es otra (`release/frontend`), úsala; si el usuario nombra otra base explícitamente, respétala.
2. Si aún hay trabajo sin commitear que entra en el push: añade `git --no-pager diff HEAD` + `git --no-pager diff --cached` + untracked relevantes vía `git status --short`.
3. Guardas de tamaño:
   - Diff vacío → dilo y para. No hay nada que revisar.
   - Diff trivial (<10 líneas en <2 archivos) → pregunta si de verdad quiere la revisión completa (overhead alto).
   - Diff enorme (>2000 líneas o >30 archivos) → propone restringir por subárbol (`backend/src/VendingMachine/Domain/` p.ej.) o partir el push.
4. Si el diff toca `backend/composer.json` o `backend/composer.lock`, ejecuta **tú** `composer audit` desde `backend/` y pega la salida en el prompt de `security-reviewer`. Los revisores no tienen shell (Fase 2); sin esa salida su check A06 no es verificable y no deben inventarlo.

## Fase 2 — Despachar los revisores en paralelo

En **una sola tool-call** con una invocación del tool Agent por revisor, lanza:

**Cuál de los dos rosters, según lo que toque el diff.** Siempre cuatro lentes y un solo veredicto; lo que cambia es qué par ocupa las plazas de arquitectura y tests, porque `architecture-reviewer` habla de Deptrac y agregados y `test-quality-reviewer` de PHPUnit y niveles: sobre un diff de React no dan falsos positivos, dan ruido, y un revisor que opina de lo que no entiende enseña a ignorar veredictos.

| Lente | Diff de `backend/` | Diff de `frontend/` |
|---|---|---|
| Seguridad | `security-reviewer` | `security-reviewer` |
| Arquitectura | `architecture-reviewer` | `frontend-architecture-reviewer` |
| Legibilidad | `clean-code-reviewer` | `clean-code-reviewer` |
| Tests | `test-quality-reviewer` | `frontend-test-quality-reviewer` |

**Diff que toca los dos** (p. ej. un cambio de contrato que mueve backend y cliente a la vez): despacha la unión, seis agentes en la misma tool-call, y pasa a cada uno el diff **completo** — el de arquitectura de backend necesita ver el cliente para juzgar si el contrato se filtró, y al revés. Un diff que solo toca `.claude/`, `docs/` o `Makefile` no lleva revisores de arquitectura ni de tests: di qué ejes omites y por qué.

**Todos son de solo lectura y no tienen shell**: su allowlist es `Read, Grep, Glob`. No es solo una instrucción en su prompt, es lo que pueden hacer. Un revisor que edita destruye trabajo sin commitear y contamina el diff que está juzgando — ya ocurrió una vez, con un revisor reescribiendo el código que se le había pedido revisar.

Red de seguridad: apunta la salida de `git status --porcelain` **antes** de lanzarlos y compárala al recibir los informes. Si difiere, el árbol se ha tocado: revierte antes de seguir y no incorpores nada al commit sin haberlo decidido tú.

A cada uno pásale exactamente lo mismo: el diff literal (si cabe en el prompt; si no, la lista de paths para que lo lean ellos) y la instrucción de aplicar su rúbrica contra `CLAUDE.md`.

**Nunca les pases tu valoración sobre si el cambio está bien.** Pasa el diff y el contrato. Si les das tu conclusión, te la devuelven confirmada.

## Fase 3 — Reconciliar y sintetizar

1. Deduplica hallazgos que dos ejes reporten sobre la misma línea (p.ej. float monetario: security lo marca Critical y clean-code lo marca como magic number — es UN hallazgo, eje Security, severidad la mayor).
2. Descarta hallazgos sobre líneas que el diff no toca (deuda preexistente): no bloquean, pero se listan como candidatos a ticket. **Sí** cuentan si el diff *añade* violaciones a esa zona (regresión).
3. Tabla única:

```
## Síntesis review-before-push

| Severidad | Eje | Archivo:Línea | Resumen |
|---|---|---|---|
| Critical | Security | backend/src/.../X.php:42 | Float en cálculo de cambio |
| High | Architecture | backend/src/.../Y.php:12 | Handler importa Doctrine |

### Veredicto: KO (1 Critical + 1 High) — no hacer push
```

o bien:

```
### Veredicto: PASS (0 Critical, 0 High)
Notas menores: <lista de Medium/Low, o "ninguna">
```

Reglas del veredicto: **KO** si ≥1 Critical o ≥1 High tras reconciliar · **PASS CON RESERVAS** si solo hay Medium (se puede pushear, los Medium van a tickets) · **PASS** si como mucho Low. El veredicto de cada agente individual informa, pero el agregado manda: un PASS individual no anula un Critical de otro eje.

## Fase 4 — Después del veredicto

- **KO** → lista las correcciones en orden de severidad. Las que son del cambio actual se arreglan ahora (vía `fix-bug` si tienen entidad); re-lanza SOLO los ejes que fallaron sobre el diff corregido, y el veredicto final vuelve a ser agregado.
- **Hallazgos laterales** (deuda preexistente, Medium/Low fuera de alcance) → **sugiere** `/create-ticket` con `Origen: Detectado durante review-before-push`, uno por hallazgo ortogonal. NO crees los tickets tú directamente.
- **PASS** → di explícitamente que el push queda autorizado y con qué comando.

## Trampas conocidas

- No relances los 4 agentes sobre un diff sin cambios esperando otro resultado — si dudas de un hallazgo, verifícalo tú leyendo el código citado.
- Un hallazgo sin `archivo:línea` verificable no cuenta para el veredicto: pide al agente la evidencia o descártalo como no accionable.
- El primer commit de scaffolding (carpetas, tooling, docs) produce diffs enormes sin código productivo: restringe los agentes a lo que tenga lógica (o dilo y salta la review con acuerdo del usuario).
