# Actualizar los docs entregables: README, assumptions y testing-strategy

## Contexto

La épica cambia cosas que el entregable publica con números y afirmaciones concretas: el README dice "Takes **0.05, 0.10, 0.25 and 1.00**" y su §"A new coin" narra el camino de añadir una denominación como ejercicio hipotético — que los tickets 01-05 habrán convertido en historia real. `docs/assumptions.md` recoge la regla "la 1.00 nunca se devuelve" que ahora cubre también a la 2.00, y los counts de tests publicados quedarán viejos. Regla del repo: toda cifra publicada sale de un comando ejecutado, no de memoria.

## Criterios de aceptación

- [ ] README: §"What the machine does" describe el modelo real (6 soportadas, habilitadas por máquina, default de fábrica = las 4 del brief); §"A new coin" se reescribe sobre la experiencia real de los tickets 01/03 (qué señaló PHPStan, cuántos tests fijaban el set); la tabla de la API y el ejemplo de `GET /api/machine` muestran `supportedCoins`.
- [ ] `docs/assumptions.md`: la regla de dispensabilidad cubre 0.50 (sí) y 2.00 (no) con su porqué (K2).
- [ ] `docs/testing-strategy.md` y README: counts de tests re-medidos con `make test`/`make front-test` y pegados — nunca estimados.
- [ ] `docs/architecture.md`: si el trazado de la compra menciona el pool de cambio, refleja el filtro por habilitadas.
- [ ] Todos los enlaces del README siguen resolviendo; `make qa` verde.
- [ ] `documentation/` (apuntes de estudio): `flujos/04-comprar-producto.md`, `flujos/05-servicio.md` y las FAQs de monedas actualizadas en la misma sesión — una doc que contradice al código es peor que no tenerla.

## Capa

docs

## Repo

icar-vending-machine

## Archivos probablemente afectados

- `README.md` — §What the machine does, §API (ejemplo de estado), §How to extend it/§A new coin, counts de §Tests
- `docs/assumptions.md`
- `docs/testing-strategy.md`
- `docs/architecture.md`
- `documentation/flujos/*.md` y `documentation/faqs/*.md` (gitignorados — mismo commit no aplica, misma sesión sí)

## Enfoque sugerido

1. Releer cada afirmación del README sobre monedas/catálogo contra el código ya mergeado de la épica.
2. Re-medir todos los counts publicados.
3. Pasada de enlaces.

(No prescriptivo.)

## ADR asociado

No — documenta decisiones ya registradas en el ADR-0018 (ticket 01).

## Depende de

04, 05

## Prioridad sugerida

baja — es el cierre; sin él la épica no está entregada, pero nada técnico lo bloquea antes.

## Notas y referencias

- Spec: [../../../docs/specs/2026-08-21-configurable-coins-and-catalogue-design.md](../../../docs/specs/2026-08-21-configurable-coins-and-catalogue-design.md)
- Precedente de fallo a evitar: dos veces se publicó un count viejo (ADR-0017, ticket 22) — la regla "cifra publicada = comando pegado" existe por eso.

## Origen

Sesión PM de 2026-08-21 — spec `docs/specs/2026-08-21-configurable-coins-and-catalogue-design.md`
