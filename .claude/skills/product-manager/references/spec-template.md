# Plantilla de spec (F5 — solo épicas: ≥3 tickets o multi-repo)

Guardar donde el proyecto guarde specs (`docs/superpowers/specs/` → `docs/specs/` → `docs/design/`; si ninguna existe, crear `docs/specs/`), como `YYYY-MM-DD-<slug>-design.md`. El spec es el documento-índice de la épica: los tickets enlazan a él, no lo repiten.

```markdown
# <Título de la épica> — Diseño

> **Estado:** aprobado en sesión PM de <YYYY-MM-DD>. Tickets en `.claude/tasks/<epic-slug>/`.

## Resumen
<3-5 frases: qué se construye y por qué ahora.>

## Problema / objetivo
<Qué duele hoy o qué oportunidad se persigue. Criterios de éxito medibles si los hay.>

## Alcance
- <Incluido 1>
## No-alcance (explícito)
- <Excluido 1 — y por qué / cuándo se revisitaría>

## Decisiones de la entrevista
| # | Pregunta | Decisión | Tipo |
|---|---|---|---|
| 1 | <pregunta> | <respuesta elegida> | usuario \| delegada |

## Suposiciones (huecos MENORES defaulteados)
- <Suposición 1 — validada en la aprobación de la definición>

## Diseño a nivel sistema
<Repos/apps tocados y su papel. Entidades/tablas nuevas o modificadas y quién posee su esquema.
Costuras implicadas (colas, syncs, permisos, emails) con dirección y disparador. Riesgos de
seguridad/privacidad señalados en la definición.>

## Desglose de tickets
| NN | Título | Repo | Prioridad | Depende de |
|---|---|---|---|---|
| 01 | <título> | <repo> | high | — |

## Riesgos
- <Riesgo → mitigación o decisión de aceptarlo>

## Referencias
- <Docs, código (`fichero:línea`), specs previas, hallazgos de investigación relevantes>
```

Tras escribirlo, pásale una revisión rápida: sin TBD/huecos, sin contradicciones entre secciones, sin requisito interpretable de dos maneras (si lo hay, fija una). Arregla inline y sigue.
