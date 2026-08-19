# Architecture

## The shape

Hexagonal architecture — ports and adapters. The business model sits in the middle and knows nothing about the outside; everything that touches the world is an adapter around it.

```
                        DRIVING (primary) adapters
                    the world calls the application

            ┌──────────────────┐   ┌──────────────────┐
            │  Delivery/Http/  │   │  Delivery/Cli/   │
            │  6 controllers   │   │  2 commands      │
            └────────┬─────────┘   └────────┬─────────┘
                     │                      │
                     └──────────┬───────────┘
                                │  commands / queries on a bus
                    ┌───────────▼────────────┐
                    │      Application       │   use cases: load, delegate,
                    │  handlers + read model │   save, announce
                    ├────────────────────────┤
                    │        Domain          │   the rules, and the ports
                    │  VendingMachine        │   they need declared here
                    │  Money, CoinCollection │
                    │  ChangeStrategy (port) │
                    │  VendingMachineRepo…   │
                    └───────────┬────────────┘
                                │  ports, implemented outwards
                     ┌──────────┴───────────┐
                     │                      │
            ┌────────▼─────────┐   ┌────────▼─────────┐
            │ Infrastructure/  │   │ Infrastructure/  │
            │ Doctrine repo    │   │ InMemory repo    │
            └──────────────────┘   └──────────────────┘

                        DRIVEN (secondary) adapters
                    the application calls the world
```

Two driving adapters and two driven ones. That pair **is** the hexagon, and it is the claim the repository can demonstrate rather than assert:

- Two doors in. The HTTP controllers and `app:machine:run` build the *same* command objects and put them on the *same* bus. The CLI reimplements nothing — no price, no rule, no arithmetic (ADR-0001).
- Two ways out. The port `VendingMachineRepository` is declared in `Domain/`; Doctrine and the in-memory double implement it, both pass the same contract test, and swapping them is one line of `config/services.yaml` (ADR-0009).

## The layers, and what may depend on what

| Layer | May depend on |
|---|---|
| `Shared/Domain` | nothing |
| `VendingMachine/Domain` | `Shared/Domain` |
| `VendingMachine/Application` | `Domain`, `Shared/Domain` |
| `VendingMachine/Delivery` | `Application`, `Domain`, `Shared/Domain`, `Symfony\*`, `Psr\*` |
| `VendingMachine/Infrastructure` | everything above + `Doctrine\*` |
| `Shared/Infrastructure` | `Shared/Domain`, `Symfony\*`, `Doctrine\*`, `Psr\*` |

This is not a convention that erodes: `deptrac.php` is that table in executable form and a violation fails CI. `grep -r "Symfony\|Doctrine" backend/src/VendingMachine/Domain` returns nothing, and it returns nothing because something checks.

Four mechanisms keep the framework out, and each closes a different door:

1. **Namespace containment** — enforced by Deptrac.
2. **No attributes in Domain or Application** — routes live in `config/routes/api.yaml`, handlers are tagged by `_instanceof` on marker interfaces, the ORM mapping is XML in `config/doctrine/`. A `#[Route]` or an `#[ORM\Entity]` is an import, and an import is a dependency.
3. **The container does not know the domain** — `services.yaml` excludes `Domain/` from autoregistration. An aggregate is not a service; it is built by a use case or rebuilt from storage.
4. **Ports are declared by the consumer** — `VendingMachineRepository` and `ChangeStrategy` are interfaces in `Domain/`, implemented outwards. The dependency points inwards, which is the whole trick.

## A purchase, end to end

`POST /api/machine/purchases {"selector":"WATER"}`, with 1.00 already inserted.

```
 1  PurchaseProductController          reads the body into a typed request DTO.
                                       A malformed payload is a 422 here, before
                                       a command exists.
 2  PurchaseProductCommand             carries a primitive: the selector string.
 3  CommandBus  →  Messenger           doctrine_transaction opens a transaction
                                       around the whole handler.
 4  PurchaseProductHandler             loads the machine, hands it the change
                                       policy, saves, publishes. Decides nothing.
 5  VendingMachine::purchase()         ★ the only place the three moving parts
                                       agree at once. Resolves the product,
                                       checks stock, checks the money, composes
                                       the change from escrow + reserve — and
                                       only then commits all three fields at
                                       once: inventory, reserve and escrow.
 6  DoctrineVendingMachineRepository   flush. The version column is checked here:
                                       a stale write is refused, not overwritten.
 7  EventBus                           ProductDispensed, after the write succeeded.
 8  MachineStateResponder              asks the query bus for the new state and
                                       renders both it and what fell in the tray.
 ▼
    200  {"dispensed": {...}, "machine": {...}}
```

And when step 5 refuses:

```
 5  VendingMachine::purchase()         throws CannotDispenseChange — nothing
                                       written, because every check runs before
                                       the first assignment (compute-then-commit)
 ↑  the exception rises through the handler and the controller, both of which
    catch nothing on purpose
 6  DomainExceptionSubscriber          the one place a failure becomes a response
 7  ErrorCatalog                       a table, not a match: 409, exact_change_required
 ▼
    409  application/problem+json  {"code":"exact_change_required","changeDue":"0.35"}
```

The same refusal reaches the command line as a sentence and an exit code, through `RunMachineScriptCommand`, without the domain knowing either protocol exists.

## Why one aggregate

`VendingMachine` is the only aggregate root, and inventory, coins and escrow live inside it. Not because the model is small — because a purchase enforces one invariant that spans all three at the same instant: *a product leaves the slot if and only if its price is covered and the exact change can be composed from coins physically inside the machine*.

Split them and that invariant becomes eventually consistent, which needs a compensating action for a can that has already dropped. There is no such action. So they share one transactional boundary (ADR-0005).

It does not grow without bound, because it scales by instance rather than by size: one aggregate per physical machine, with `MachineId` as the natural partition key. Fleet-level concerns belong to a different context, fed by the events this one records.

## Where to look

| You want to understand | Read |
|---|---|
| the business rules | `backend/src/VendingMachine/Domain/Machine/VendingMachine.php` |
| a use case | `backend/src/VendingMachine/Application/Command/PurchaseProduct/PurchaseProductHandler.php` |
| the HTTP contract | `backend/src/VendingMachine/Delivery/Http/Error/ErrorCatalog.php` and the `Response/` folder |
| how the hexagon is wired | `backend/config/services.yaml` |
| why any of it is like this | [`adr/`](adr/) |
