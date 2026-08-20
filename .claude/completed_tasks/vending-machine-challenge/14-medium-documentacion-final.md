# Completar README, documentación de arquitectura y consolidación de ADRs

## Contexto
Último ticket: convertir la documentación incremental en el paquete de entrega. El README es la puerta de entrada del evaluador; `docs/testing-strategy.md` responde por escrito a la pregunta literal del enunciado ("what and how to test at different levels"); assumptions.md recoge cada silencio del enunciado y qué se decidió. Los ADRs ya existen (se escribieron ticket a ticket) — aquí se indexan y revisan.

## Criterios de aceptación
- [ ] README completo: requirements, quick start (docker y manual), referencia de la API (endpoints + ejemplos curl), resumen de arquitectura con diagrama, cómo correr tests por nivel, assumptions destacadas, trade-offs, "how to extend" (añadir producto nuevo, añadir denominación nueva — el walkthrough de extensibilidad que el enunciado pregunta)
- [ ] `docs/architecture.md`: capas, flujo de una petición de compra de punta a punta, el hexágono con sus dos driving y dos driven adapters
- [ ] `docs/testing-strategy.md`: los 4 niveles, qué pregunta responde cada uno, por qué MSI y no cobertura de línea, el contract test compartido
- [ ] `docs/assumptions.md`: 1.00 nunca es cambio · SERVICE = set absoluto + reembolso de escrow · monedas insertadas se unen al pool · moneda única implícita · sin auth por alcance · idempotencia como trabajo futuro documentado
- [ ] Índice de ADRs en `docs/adr/` (o en el README); cada ADR con alternativas reales y consecuencia negativa
- [ ] `grep -ri` del nombre de la empresa → 0 resultados en todo el repo
- [ ] Revisión final: `review-before-push` con PASS antes del push definitivo

## Capa
docs

## Archivos probablemente afectados
- `README.md` — completar
- `docs/architecture.md`, `docs/testing-strategy.md`, `docs/assumptions.md` (a crear)
- `docs/adr/*.md` — revisión y índice

## Enfoque sugerido
1. assumptions.md primero (recolecta lo sembrado en tickets previos).
2. testing-strategy.md contra la suite real (citar counts reales).
3. README al final, cuando todo lo enlazable existe.

(No prescriptivo — el implementador puede divergir si encuentra mejor camino.)

## ADR asociado
No — consolida los existentes (0001-0014).

## Depende de
12, 13

## Prioridad sugerida
media — última milla, pero es lo primero que leerá el evaluador.

## Notas y referencias
- Un ADR sin inconvenientes listados se lee como marketing: revisar que TODOS tengan "Consequences (negative)" real.

## Origen
Desglose de backlog — sesión PM de 2026-08-18.
