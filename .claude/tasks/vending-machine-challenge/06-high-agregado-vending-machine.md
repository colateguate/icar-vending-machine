# Introducir el agregado VendingMachine con insert/return/service y eventos

## Contexto
El corazón del dominio: agregado único que custodia inventario, reserva de cambio y escrow bajo una sola frontera transaccional. Este ticket cubre las operaciones que no dispensan producto: insertar moneda, devolver lo insertado (ejemplo 2 del enunciado) y SERVICE (valores absolutos, reembolsando escrow primero). La compra llega en el ticket 08.

## Criterios de aceptación
- [ ] `Shared/Domain/AggregateRoot.php` con recordThat/releaseEvents
- [ ] `VendingMachine` con `provision()`, `insert(Coin)`, `returnInsertedCoins(): CoinCollection`, `service(Inventory, CoinCollection)` (valores ABSOLUTOS; reembolsa escrow antes), `insertedAmount()`, accessors de lectura
- [ ] `MachineId` VO; puerto `VendingMachineRepository` (interface) en `Domain/Machine/`
- [ ] Eventos `CoinInserted`, `CoinsRefunded`, `MachineServiced` registrados en el agregado
- [ ] `InMemoryVendingMachineRepository` con deep-clone en save y find (los tests no comparten identidad de objeto)
- [ ] Test unitario del ejemplo 2 del enunciado: insertar 0.10, 0.10 → return → devuelve exactamente [0.10, 0.10] y escrow vacío
- [ ] Sin setters públicos; estado solo muta por comportamiento de negocio

## Capa
domain | infrastructure (solo el adaptador InMemory)

## Archivos probablemente afectados
- `backend/src/Shared/Domain/AggregateRoot.php` (a crear)
- `backend/src/VendingMachine/Domain/Machine/VendingMachine.php`, `MachineId.php`, `VendingMachineRepository.php` (a crear)
- `backend/src/VendingMachine/Domain/Event/CoinInserted.php`, `CoinsRefunded.php`, `MachineServiced.php` (a crear)
- `backend/src/VendingMachine/Infrastructure/Persistence/InMemory/InMemoryVendingMachineRepository.php` (a crear)
- `backend/tests/Unit/VendingMachine/Domain/Machine/*Test.php`, `backend/tests/Support/Builder/VendingMachineBuilder.php` (a crear)

## Enfoque sugerido
1. AggregateRoot mínimo primero (test del record/release).
2. TDD del agregado: insert → returnInsertedCoins (ejemplo 2) → service.
3. Builder de test desde el principio: `aMachine()->withProduct(...)->build()`.

(No prescriptivo — el implementador puede divergir si encuentra mejor camino.)

## ADR asociado
Sí — crear `docs/adr/0005-single-aggregate-root.md` (la defensa nº1 de la entrevista: frontera de consistencia transaccional; no se puede compensar un refresco ya dispensado; escala por instancia, no por tamaño).

## Depende de
05

## Prioridad sugerida
alta — fichero patrón canónico del repo (los reviewers lo citan).

## Notas y referencias
- Semántica SERVICE = set absoluto + reembolso de escrow: requisito interpretado, documentarlo en el ADR-0005 o en docs/assumptions.
- Los eventos aún no tienen bus (llega en 09): quedan registrados en el agregado y testeados vía releaseEvents.

## Origen
Desglose de backlog — sesión PM de 2026-08-18.
