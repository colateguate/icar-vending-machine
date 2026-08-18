# Crear README inicial, Makefile y plantilla ADR

## Contexto
El repo tiene scaffolding de carpetas pero ningún documento de entrada. El enunciado exige explícitamente un README con instrucciones y requisitos, y valora el git log desde el principio: el README debe existir desde el primer ticket y crecer con el proyecto, no aparecer la última noche. El Makefile será la chuleta del evaluador (`make up`, `make test`, `make qa`).

## Criterios de aceptación
- [ ] `README.md` en la raíz, en inglés, con secciones: qué es, Requirements, Quick start, Architecture (resumen + enlace a docs), Testing, Project status (honesto: work in progress)
- [ ] Ninguna mención al nombre de la empresa (`grep -ri` limpio)
- [ ] `Makefile` con targets `up`, `test`, `test-unit`, `qa`, `test-mutation` — como stubs honestos que expliquen qué ticket los activa si la herramienta aún no existe
- [ ] `docs/adr/0000-template.md` con formato MADR: Context, Decision drivers, Considered options, Decision outcome, Consequences (positive/negative)

## Capa
docs | infra

## Archivos probablemente afectados
- `README.md` (a crear)
- `Makefile` (a crear)
- `docs/adr/0000-template.md` (a crear)

## Enfoque sugerido
1. README esqueleto con placeholders honestos que el ticket 14 completa.
2. Makefile con guard: si `backend/vendor` no existe, mensaje "pending ticket 02/03" y exit 1.
3. Plantilla MADR corta (<40 líneas) — cada ADR real debe caber en ella.

(No prescriptivo — el implementador puede divergir si encuentra mejor camino.)

## ADR asociado
No — aplica decisiones ya tomadas.

## Depende de
—

## Prioridad sugerida
alta — el README es requisito explícito del enunciado y bloquea la historia del git log.

## Notas y referencias
- Regla dura: `CHALLENGE-DESCRIPTION.md` está gitignorado y no debe committearse jamás (ver `.gitignore:1-4`).

## Origen
Desglose de backlog — sesión PM de 2026-08-18, blueprint en plan aprobado.
