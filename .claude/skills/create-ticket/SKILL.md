---
name: create-ticket
description: Convierte una descripción breve o vaga (un bug, una idea de feature, un anti-patrón visto al revisar) en un ticket estructurado con contexto, criterios de aceptación, capa afectada, archivos y enfoque sugerido, y lo guarda en `.claude/tasks/<prioridad>-<slug>.md`. Úsala cuando el usuario diga "crea un ticket para X", "guarda esto como tarea", "apúntalo para luego", cuando otra skill (típicamente fix-bug o review-before-push) detecte un hallazgo lateral que merezca tarea propia, o en modo épica para desglosar el backlog completo del reto en tickets ordenados. NO implementes nada con esta skill — para implementar usa `implement-feature` o `fix-bug`.
---

# Crear ticket estructurado

Convierte una entrada informal en un ticket accionable en `.claude/tasks/`. Local al repo: no se sube a Trello ni a ningún sistema externo.

## Fase 1 — Entender la entrada

Identifica: ¿es bug, feature, refactor, deuda, docs o infra? ¿Qué capa toca (ver `CLAUDE.md` § Backend architecture)? ¿Hay prioridad implícita en cómo lo cuenta el usuario? Si la entrada es ambigua en algo que cambia el ticket materialmente (¿es comportamiento esperado o bug? ¿dominio o delivery?), pregunta antes de investigar.

## Fase 2 — Investigar el código (obligatoria)

Antes de escribir el ticket, localiza los archivos reales implicados con `Grep`/`Glob`/`Read`. Cita `archivo:línea` reales, no inventados. Si el código aún no existe (proyecto en construcción), cita la ruta *planificada* según el árbol de `CLAUDE.md` y márcala como `(a crear)`.

**"Apunta esto", "rápido", "solo como nota" NO son excusas para saltar esta fase.** Un ticket sin investigación es un TODO sin valor. Excepción única: si el usuario dice explícitamente "no investigues, guarda lo que digo y ya" — en ese caso marca las secciones afectadas como **"No investigado por petición explícita"** y guarda igualmente.

## Fase 3 — Construir el ticket

Plantilla (todas las secciones, en este orden):

```markdown
# <Título en imperativo, máx 80 chars>

## Contexto
<2-4 frases: qué pasa hoy, por qué importa, qué impacto tiene si no se aborda>

## Criterios de aceptación
- [ ] <Condición testeable 1, específica>
- [ ] <Condición testeable 2>

## Capa
<domain | application | delivery | infrastructure | frontend | infra | docs — puede ser más de una>

## Archivos probablemente afectados
- `backend/src/VendingMachine/Domain/...` — <qué se hace ahí> <(a crear) si no existe>

## Enfoque sugerido
1. <Paso concreto>
2. <Paso concreto>

(No prescriptivo — el implementador puede divergir si encuentra mejor camino.)

## ADR asociado
<¿Esta tarea toma o cambia una decisión arquitectónica? "Sí — crear docs/adr/NNNN-<slug>.md sobre <decisión>" | "No — aplica decisiones ya tomadas">

## Depende de
<SOLO en modo épica — lista de NN previos ("01, 02") o "—">

## Prioridad sugerida
<alta | media | baja> — <una línea de justificación>

## Notas y referencias
- <Patrón canónico a imitar, ADR relevante, riesgo conocido>

## Origen
<Manual | Detectado durante fix-bug de "<bug>" | Detectado durante review-before-push | Desglose de backlog | Otro>
```

Reglas: **nada inventado** · específico, no genérico · cita siempre `archivo:línea` (o ruta planificada) · una tarea, un ticket · **no implementes**.

## Fase 4 — Guardar

- Nombre: `.claude/tasks/<prioridad>-<slug>.md` con `alta→high`, `media→medium`, `baja→low`; slug en kebab-case, máx 50 chars, sin acentos. Colisión → sufijo `-2`.
- **Modo épica** (desglose de varios tickets ordenados): `.claude/tasks/<epic-slug>/NN-<prioridad>-<slug>.md` donde `NN` es orden de ejecución (dependencias primero). Ticket insertado a posteriori → sufijo de letra (`09b`).
- Ciclo de vida por sistema de ficheros: pendiente = `.claude/tasks/`; al terminar, `implement-feature`/`fix-bug` lo **mueven** a `.claude/completed_tasks/` (conservando la subcarpeta de épica).
- Reporta con una sola línea clicable: `Guardado en [.claude/tasks/medium-foo.md](.claude/tasks/medium-foo.md)`.

## Red flags — STOP

| Pensamiento | Realidad |
|---|---|
| "Es una nota rápida, no hace falta plantilla" | Un ticket a medias es el que nadie retoma. Plantilla completa siempre. |
| "Ya sé qué archivos toca, no hace falta grep" | Casi siempre hay un consumidor más. Verifica. |
| "De paso lo implemento, es pequeño" | Esta skill NO implementa. Enruta a `implement-feature` o `fix-bug`. |
| "La sección ADR no aplica a este proyecto tan pequeño" | El guion de la entrevista se construye ticket a ticket. Contesta sí o no, razonado. |
