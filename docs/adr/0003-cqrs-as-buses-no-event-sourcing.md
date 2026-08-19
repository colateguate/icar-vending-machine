# 0003 — Apply CQRS as command/query bus separation, without event sourcing or a read database

- **Status**: accepted
- **Date**: 2026-08-19

## Context and problem statement

The machine has four things a caller can ask it to do and one thing a caller can ask about it. That is a small surface, and "CQRS" covers everything from separating two method names to running two databases kept in sync by a projection. Choosing where on that scale to sit is the decision; taking the whole package because the acronym is fashionable would be the mistake.

## Decision drivers

- Cross-cutting concerns — transactions today, logging and metrics later — should be declared once, not repeated in every use case.
- The delivery mechanism must not be able to reach into the model and change it.
- Whatever is built has to be defensible against "you have four commands, why is there a bus at all?".

## Considered options

1. Two in-process buses (command and query) over Symfony Messenger, one write model, no projections.
2. Use-case services injected directly into controllers, no bus.
3. Full CQRS with event sourcing: events as the source of truth, projections feeding a separate read store.

## Decision outcome

**Chosen: option 1.**

The honest justification for the bus is not "commands and queries are different", which two well-named services would also give. It is that **the bus is the one place a rule about every use case can be stated once**. `doctrine_transaction` on the command bus declares "one command, one transaction, one aggregate" for all of them, present and future, and the same slot later takes logging, metrics or idempotency without a single handler changing. Without that, the bus is ceremony, and option 2 is the better answer — which is why this ADR would have to be revisited if the middleware were ever removed.

The read side earns its separation differently: `GetMachineStateQuery` returns a `MachineStateView`, not the aggregate. Handing the aggregate to a controller would put `purchase()` within reach of the delivery layer, and the entire point of the command side is that changes go through it.

Option 3 is rejected on cost. Event sourcing brings event versioning, projections, snapshots and rebuild tooling, and buys correctness properties this domain does not need: there is one aggregate, its state is small, and nobody has asked to replay history. The domain events here are notifications, not the source of truth — a distinction worth being explicit about, because "we have domain events" is often mistaken for "we do event sourcing".

Two consequences of the shape chosen:

- **Commands carry primitives, not value objects.** They must survive serialisation so a bus can go asynchronous later without the message changing, and the handler is where a primitive becomes a domain type — a translation that doubles as validation, since the value object's constructor is what rejects what cannot exist. There is deliberately no validation middleware: making an invalid value unrepresentable is the domain's job.
- **Two commands answer back.** `PurchaseProduct` returns what was dispensed and `ReturnCoins` returns the coins refunded, because both physically left the machine and no later query could recover them. This is a knowing deviation from CQS, recorded in ADR-0010.

### Consequences (positive)

- A rule that must hold for every use case is written once, in configuration, instead of being remembered in each handler.
- Handlers are reached through marker interfaces tagged by the container, so the application layer names no framework at all.
- The delivery layer cannot mutate the model even by accident: the query side hands back a view.

### Consequences (negative)

- Dispatching through a bus makes stack traces longer and the path from controller to handler less obvious to someone reading the code for the first time.
- Every use case costs two files (a message and a handler) where a service method would have cost none.
- The type safety of the buses rests on PHPStan generics rather than the language. It is checked at analysis time, and a caller who ignores the analyser gets `mixed`.
- Until Doctrine lands the transaction middleware is not there, which means the central justification is configured but not yet true. That is tracked, not glossed over.
