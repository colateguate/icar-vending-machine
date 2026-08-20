# Implementar la compra con dispensado de cambio (compute-then-commit)

## Contexto
Cierra el dominio: `purchase()` une inventario, escrow, reserva y estrategia de cambio bajo la invariante central — un producto sale del stock si y solo si su precio está cubierto Y el cambio exacto es componible con las monedas físicamente dentro de la máquina. El caso "no puedo dar cambio" es la mejor edge case del reto: venta rechazada, monedas retenidas en escrow, y la lamparita `requiresExactChange()` convierte el error en feature.

## Criterios de aceptación
- [ ] `VendingMachine::purchase(ProductSelector, ChangeStrategy): DispensedGoods` — la estrategia entra por PARÁMETRO (double dispatch), no por constructor
- [ ] Compute-then-commit: si algo falla (stock, fondos, cambio) NINGÚN campo ha mutado — test que asserta igualdad total de estado tras compra fallida
- [ ] Las monedas insertadas se unen al pool de cambio antes de seleccionar: `changeReserve.merge(insertedCoins)`. **Pasar el pool SIN filtrar** — la estrategia ya filtra `dispensableOnly()` internamente porque el puerto lo promete (ADR-0006), y filtrar también aquí sería redundante y dejaría la invariante en dos sitios. Añade un test que pase una reserva con monedas de 1.00 y compruebe que el cambio no las incluye, para que la garantía quede verificada desde el agregado y no solo desde la estrategia
- [ ] `InsufficientFunds` con el importe faltante; `CannotDispenseChange` deja el escrow intacto
- [ ] Evento `ProductDispensed`; `requiresExactChange(): bool` expuesto
- [ ] Tests unitarios de los ejemplos 1 y 3 del enunciado: 1+0.25+0.25→GET-SODA→SODA sin cambio; 1→GET-WATER→WATER + [0.25, 0.10] y reserva decrementada exactamente en esas monedas
- [ ] Mutation testing: MSI >= 85 en Domain al cerrar

## Capa
domain

## Archivos probablemente afectados
- `backend/src/VendingMachine/Domain/Machine/VendingMachine.php` — añadir purchase()
- `backend/src/VendingMachine/Domain/Dispensing/DispensedGoods.php` (a crear — VO: Product + CoinCollection de cambio)
- `backend/src/VendingMachine/Domain/Exception/InsufficientFunds.php` (a crear)
- `backend/src/VendingMachine/Domain/Event/ProductDispensed.php` (a crear)
- `backend/tests/Unit/VendingMachine/Domain/Machine/VendingMachinePurchaseTest.php`, `VendingMachineExactChangeTest.php` (a crear)

## Enfoque sugerido
1. Rojo con el ejemplo 1 (cambio exacto, camino feliz).
2. Rojo con el ejemplo 3 (cambio a devolver).
3. Rojo por cada edge: fondos insuficientes → sin stock → sin cambio componible (assert estado intacto) → selector desconocido.
4. En tests del agregado que no prueban el algoritmo: `FixedChangeStrategy` de `tests/Support/Doubles/` (único doble legítimo a nivel unitario).

(No prescriptivo — el implementador puede divergir si encuentra mejor camino.)

## ADR asociado
Sí — crear `docs/adr/0007-reject-purchase-when-change-unavailable.md` (alternativa rechazada: auto-refund — descarta la intención del usuario y duplica el camino de reembolso).

## Depende de
06, 07

## Prioridad sugerida
alta — completa el modelo de negocio; los 3 ejemplos del enunciado quedan verdes a nivel unitario.

## Notas y referencias
- `1, 0.25, 0.25, GET-SODA -> SODA` · `1, GET-WATER -> WATER, 0.25, 0.10` (enunciado, ejemplos 1 y 3).
- Con este ticket, el git log demuestra: modelo de negocio completo SIN una línea de Symfony.

## Origen
Desglose de backlog — sesión PM de 2026-08-18.
