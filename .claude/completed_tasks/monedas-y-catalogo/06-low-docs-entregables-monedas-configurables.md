# Actualizar los docs entregables: README, assumptions y testing-strategy

## Contexto

La épica cambia cosas que el entregable publica con números y afirmaciones concretas: el README dice "Takes **0.05, 0.10, 0.25 and 1.00**" y su §"A new coin" narra el camino de añadir una denominación como ejercicio hipotético — que los tickets 01-05 habrán convertido en historia real. `docs/assumptions.md` recoge la regla "la 1.00 nunca se devuelve" que ahora cubre también a la 2.00, y los counts de tests publicados quedarán viejos. Regla del repo: toda cifra publicada sale de un comando ejecutado, no de memoria.

## Criterios de aceptación

- [x] README: §"What the machine does" describe el modelo real (6 soportadas, habilitadas por máquina, default de fábrica = las 4 del brief); §"A new coin" se reescribe sobre la experiencia real de los tickets 01/03 (qué señaló PHPStan, cuántos tests fijaban el set); la tabla de la API y el ejemplo de `GET /api/machine` muestran `supportedCoins`.
- [x] `docs/assumptions.md`: la regla de dispensabilidad cubre 0.50 (sí) y 2.00 (no) con su porqué (K2).
- [x] `docs/testing-strategy.md` y README: counts de tests re-medidos con `make test`/`make front-test` y pegados — nunca estimados.
- [x] `docs/architecture.md`: si el trazado de la compra menciona el pool de cambio, refleja el filtro por habilitadas.
- [x] Todos los enlaces del README siguen resolviendo; `make qa` verde.
- [x] `documentation/` (apuntes de estudio): `flujos/04-comprar-producto.md`, `flujos/05-servicio.md` y las FAQs de monedas actualizadas en la misma sesión — una doc que contradice al código es peor que no tenerla.

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

## Cierre (2026-08-23)

- Toda cifra publicada salió de un comando ejecutado en esta sesión: suites 318/45/53/133 = 549 tests y 3.700 aserciones (`make test`), panel 212 (`make front-test`), 7 specs de navegador, mutación 333 generados · 326 muertos · 6 timeout · 1 fatal · MSI 100% (`make test-mutation`), y 147 respuestas validadas contra el contrato (contador desechable en `assertResponseMatches`, revertido tras medir — eran 114).
- §"A new coin" no se reescribió de memoria: el experimento se **repitió hoy** — `case TWENTY_CENTS = 20;` añadido de verdad, PHPStan señalando el match (línea 60), y al contestarlo **57 tests** fijando el set: 5 unit (uno se llama `the_hardware_reads_exactly_six_denominations`), 4 application y 48 de aceptación, los 48 el contrato OpenAPI rechazando cada respuesta que publica una séptima moneda. Revertido después.
- El ejemplo de `GET /api/machine` del README es una respuesta real capturada de una máquina provisionada en local (la base de dev era pre-épica y daba 500 al hidratar el campo nuevo — migrada y reprovisionada, que es lo que `make up` hace solo). La demo de habilitar 0.50 + comprar con ella también se ejecutó y sus outputs son los pegados.
- assumptions: la regla pasa a "las monedas grandes entran y no salen" (1.00 y 2.00; la 0.50 sí se dispensa) + la varada nunca sale. architecture.md: el paso 5 de la traza estrecha el pool a aceptadas∩dispensables. testing-strategy: cuarto gate (ejemplos de éxito) y la re-comprobación de las supresiones de Infection con 0.50.
- Enlaces verificados por script (0 rotos); `make qa` verde entero.
- `documentation/`: corregidos dos "289" huérfanos y ampliada la FAQ de la moneda de 1.00 a la regla de seis; flujos 04/05 y la FAQ de monedas configurables ya estaban al día.
