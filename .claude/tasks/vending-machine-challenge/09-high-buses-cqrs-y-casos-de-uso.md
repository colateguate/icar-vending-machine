# Añadir los buses CQRS (Messenger) y los cinco casos de uso

## Contexto
Primera conexión del dominio con Symfony, y solo a través de puertos: contratos de bus en `Shared/Domain`, implementación Messenger en `Shared/Infrastructure`, y los casos de uso como pares Command/Handler y Query/Handler. La defensa del bus para 4 comandos es el middleware: `doctrine_transaction` declara "un comando = una transacción = un agregado" UNA vez, y el mismo slot acepta luego logging, métricas, idempotencia o transporte async sin tocar handlers.

## Criterios de aceptación
- [ ] Contratos en `Shared/Domain/Bus/`: Command/CommandBus/CommandHandler, Query/QueryBus/QueryHandler, DomainEvent/EventBus
- [ ] `Messenger{Command,Query,Event}Bus` en `Shared/Infrastructure/Bus/`
- [ ] `config/packages/messenger.yaml`: 3 buses — command.bus con middleware doctrine_transaction, query.bus prohíbe handler ausente, event.bus permite cero handlers
- [ ] Handlers registrados por `_instanceof` en `config/services/application.yaml` — CERO `#[AsMessageHandler]`
- [ ] 4 comandos + 1 query con handlers: InsertCoin (void), ReturnCoins (→ReturnedCoinsResult), PurchaseProduct (→PurchaseResult), ServiceMachine (void), GetMachineState (→MachineStateView)
- [ ] Comandos transportan PRIMITIVAS; el handler traduce a VOs
- [ ] Suite `backend/tests/Application/` con repo InMemory + `SpyEventBus`: cada handler carga→delega→guarda→publica
- [ ] Deptrac verde: Application sin imports de Symfony

## Capa
application | infrastructure (Shared)

## Archivos probablemente afectados
- `backend/src/Shared/Domain/Bus/Command/*.php`, `Query/*.php`, `Event/*.php` (a crear)
- `backend/src/Shared/Infrastructure/Bus/{Command,Query,Event}/Messenger*Bus.php` (a crear)
- `backend/src/VendingMachine/Application/Command/{InsertCoin,ReturnCoins,PurchaseProduct,ServiceMachine}/*.php` (a crear)
- `backend/src/VendingMachine/Application/Query/GetMachineState/*.php` (a crear)
- `backend/config/packages/messenger.yaml`, `backend/config/services/application.yaml` (a crear)
- `backend/tests/Application/VendingMachine/**/*Test.php`, `backend/tests/Support/Doubles/SpyEventBus.php` (a crear)

## Enfoque sugerido
1. Contratos de bus + tests de wiring mínimos.
2. TDD handler a handler contra InMemory, empezando por InsertCoin (el más simple).
3. `MachineLocator` en `Application/Shared/` para resolver la máquina singleton (lanza `MachineNotFound`).

(No prescriptivo — el implementador puede divergir si encuentra mejor camino.)

## ADR asociado
Sí — crear `docs/adr/0003-cqrs-as-buses-no-event-sourcing.md` y `docs/adr/0010-command-handlers-may-return-physical-outcome.md` (desviación CQS consciente: las monedas dispensadas han salido físicamente de la máquina — ninguna query posterior puede recuperarlas; alternativas A/B del blueprint como rechazadas).

## Depende de
08

## Prioridad sugerida
alta — el patrón canónico `PurchaseProductHandler.php` que citan los reviewers nace aquí.

## Notas y referencias
- Frase para la entrevista: "validation isn't middleware — making invalid values unrepresentable is the domain's job" (por eso no hay Symfony Validator en Application).

## Origen
Desglose de backlog — sesión PM de 2026-08-18.
