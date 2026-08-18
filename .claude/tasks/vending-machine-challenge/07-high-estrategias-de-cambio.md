# Implementar las estrategias de cambio (Greedy y Optimal) como servicio de dominio

## Contexto
El algoritmo de cambio es política de negocio sin I/O: vive en el dominio como servicio (necesita el importe Y el multiset disponible, y tiene más de una implementación válida). Es el diferenciador técnico más fuerte del reto: greedy con monedas LIMITADAS rechaza ventas servibles — pedir 0.30 con reserva {0.25×1, 0.10×3} hace que greedy tome el 0.25, deba 0.05 que no tiene, y pierda la venta que 0.10+0.10+0.10 servía. Eso convierte el interface de "abstracción especulativa" en "requisito de corrección".

## Criterios de aceptación
- [ ] Puerto `ChangeStrategy` en `Domain/Dispensing/`: `selectCoins(Money $amount, CoinCollection $available): CoinCollection` con `@throws CannotDispenseChange`
- [ ] `GreedyChangeStrategy`: denominaciones descendentes, min(needed, available)
- [ ] `OptimalChangeStrategy`: DP de monedas acotadas — nunca falla si existe solución
- [ ] `CannotDispenseChange` (domain error) transporta el importe debido
- [ ] Test nombrado `test_greedy_refuses_a_sale_the_optimal_strategy_serves` con el caso 0.30/{0.25×1,0.10×3}
- [ ] Test de propiedad/invariante con seed fija sobre ambas: total(result) == amount, result ⊆ available, sin denominaciones no dispensables
- [ ] Solo se seleccionan monedas `dispensableOnly()` (la de 1.00 jamás sale como cambio)

## Capa
domain

## Archivos probablemente afectados
- `backend/src/VendingMachine/Domain/Dispensing/ChangeStrategy.php`, `GreedyChangeStrategy.php`, `OptimalChangeStrategy.php` (a crear)
- `backend/src/VendingMachine/Domain/Exception/CannotDispenseChange.php` (a crear)
- `backend/tests/Unit/VendingMachine/Domain/Dispensing/*Test.php` (a crear)

## Enfoque sugerido
1. TDD de Greedy primero (simple, establece el contrato del puerto).
2. Optimal después, arrancando por los contraejemplos donde Greedy falla.
3. Bucle randomizado con seed fija a mano — sin dependencia extra de property-testing.

(No prescriptivo — el implementador puede divergir si encuentra mejor camino.)

## ADR asociado
Sí — crear `docs/adr/0006-pluggable-change-strategy-optimal-default.md` (con el contraejemplo de disponibilidad como justificación de corrección, no de flexibilidad).

## Depende de
04

## Prioridad sugerida
alta — la compra (08) lo necesita; puede hacerse en paralelo con 05-06.

## Notas y referencias
- Greedy NO es código muerto: se conserva testeado como contraejemplo (regla anti-falso-positivo en `.claude/agents/clean-code-reviewer.md`).
- Comentario en el DP explicando el porqué, no el paso a paso (regla del clean-code-reviewer).

## Origen
Desglose de backlog — sesión PM de 2026-08-18.
