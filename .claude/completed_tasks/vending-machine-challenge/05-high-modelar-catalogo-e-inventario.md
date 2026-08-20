# Modelar Product, ProductSelector, Quantity e Inventory con TDD

## Contexto
Segunda pieza del dominio: el catálogo. Cada ítem tiene selector, precio y contador (requisito literal del enunciado). `Inventory` es un VO interno del agregado que mapea selector→producto; no es un agregado propio — la invariante de compra cruza inventario y monedas a la vez, así que viven dentro del mismo límite transaccional (decisión del blueprint, se defiende en el ADR-0005 del ticket 06).

## Criterios de aceptación
- [ ] `ProductSelector` VO (WATER | JUICE | SODA como valores iniciales, formato validado, extensible sin tocar el VO consumidor)
- [ ] `Quantity` VO con invariante >= 0; decremento por debajo de cero imposible
- [ ] `Product` entidad local al agregado: selector, name (string simple, sin VO — saber parar), price (Money), count (Quantity)
- [ ] `Inventory` VO: find por selector (lanza `UnknownProductSelector`), decrement stock (lanza `ProductOutOfStock` a cero), listado para la query de estado
- [ ] Tests unitarios rojos primero en `backend/tests/Unit/VendingMachine/Domain/Catalog/`
- [ ] Deptrac y PHPStan max verdes

## Capa
domain

## Archivos probablemente afectados
- `backend/src/VendingMachine/Domain/Catalog/Product.php`, `ProductSelector.php`, `Quantity.php`, `Inventory.php` (a crear)
- `backend/src/VendingMachine/Domain/Exception/UnknownProductSelector.php`, `ProductOutOfStock.php` (a crear)
- `backend/tests/Unit/VendingMachine/Domain/Catalog/*Test.php` (a crear)

## Enfoque sugerido
1. TDD: ProductSelector → Quantity → Product → Inventory.
2. Inventory inmutable devolviendo copias modificadas (consistente con los VOs monetarios).

(No prescriptivo — el implementador puede divergir si encuentra mejor camino.)

## ADR asociado
No — aplica el modelo decidido en `CLAUDE.md` § Domain model (el ADR del agregado único llega con el ticket 06).

## Depende de
04

## Prioridad sugerida
alta — el agregado (06) lo necesita.

## Notas y referencias
- NO crear `ProductName` VO: string basta y "saber dónde parar" es parte de lo evaluado (blueprint, riesgo 12).

## Origen
Desglose de backlog — sesión PM de 2026-08-18.
