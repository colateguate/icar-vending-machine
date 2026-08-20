# 0011 — Detect concurrent writes with a version column and answer 409

- **Status**: accepted
- **Date**: 2026-08-19

## Context and problem statement

Two people press the button at the same instant and there is one can left. Both requests read a machine with stock of one, both aggregates agree the sale is valid, and both write. Without something in the way, the second write overwrites the first: one can is dispensed twice, the till is short, and nothing anywhere reports a problem.

The aggregate's own guarantees do not help here. `purchase()` is careful to compute everything before it commits, so no *single* request can leave a half-done sale — but that reasoning is about one unit of work, and this failure happens between two.

## Decision drivers

- The invariant that justifies having a single aggregate (ADR-0005) is worth nothing if two writers can both satisfy it against the same starting state.
- A lost update must be reported, not swallowed. Silence here is the worst outcome available.
- Real contention on one physical machine is effectively zero: a person stands in front of it and presses one button.
- The runtime is SQLite (ADR-0008), which serialises writers at the database level anyway.

## Considered options

1. Optimistic locking: a version column, checked on every write; the loser is told.
2. Pessimistic locking: `SELECT ... FOR UPDATE` when reading the machine.
3. Optimistic locking plus an automatic retry of the losing request.
4. Nothing, on the grounds that the contention is theoretical.

## Decision outcome

**Chosen: option 1.** `<version/>` in the XML mapping, an integer column, and Doctrine adding `WHERE version = ?` to every update. The adapter turns the resulting `OptimisticLockException` into `ConcurrentMachineModification`, which the error catalogue answers as **409**.

Option 4 is the one to reject out loud. The odds are low and the failure is silent and monetary, which is exactly the combination that deserves a cheap detector rather than an argument about probability.

Option 2 costs a lock held for the length of a request to prevent a collision that will not happen, and on SQLite it buys nothing the engine is not doing already. Option 3 is a real improvement for a busy system and is out of scope here: retrying means deciding what to replay and what to tell the customer while it happens, which is a design, not a flag.

### Why the version lives on the aggregate

Doctrine can only guard a field it maps, and the mapped class is the domain aggregate (ADR-0008). So `VendingMachine` carries `private int $version` and a `version()` accessor that the model itself never calls.

This is the one place where persistence is visible in the domain, and it is worth being precise about the trade. The alternative was a parallel persistence model — a second aggregate, mapped, translated to and from this one — which buys purity with a copy that can drift. Weighed against a single integer that no business rule mentions, the copy is the worse deal. Vaughn Vernon's *Implementing DDD* puts a concurrency version on aggregates for the same reason, so this is a documented pattern rather than an improvisation; that does not make it free, and calling it free would be the dishonest part.

### Why 409 and not 500 or a retry

Nothing the caller sent was wrong, so it is not a 4xx about their input; nothing is broken, so it is not a 500. Their read is simply out of date, which is what 409 Conflict means. The response says so, and the client can ask again and decide — which for a vending machine UI means redrawing the stock and letting the customer choose.

`ConcurrentMachineModification` lives in the **domain**, not beside the adapter that detects it. Two reasons: the HTTP edge has to name it to answer 409 and the dependency rule forbids the edge from knowing Doctrine exists; and conceptually it belongs to the same family as `MachineNotFound` — a failure the model anticipates about its own persistence, named so it can be answered honestly. How it is detected is deliberately not part of it: today a version column, tomorrow a timestamp or a row lock, and the caller's answer would not change.

### Consequences (positive)

- A lost update is impossible rather than unlikely, and the second writer is told what happened.
- `ConcurrentPurchaseTest` proves it with two EntityManagers over one database: both read stock of one, both purchase, one save wins, the other raises, and the shelf ends at zero rather than minus one.
- The cost is one integer column and no locks held anywhere.
- The mechanism is invisible to every use case. No handler mentions concurrency; the bus opens the transaction (ticket 11) and the adapter checks the version.

### Consequences (negative)

- **A version column in the domain model.** The single honest blemish of this design, argued above, and the first thing to point at when asked what this codebase gave up for pragmatism.
- **The loser loses their work.** They read, they decided, and they are told to start again. With real contention that becomes a bad experience and the answer would be option 3 — which is deliberately not built.
- **SQLite serialises writers anyway**, so on the chosen runtime this guard is belt and braces: the race it prevents is hard to even provoke without two connections and a file database, which is exactly what the test has to set up. It stops being redundant the moment the DSN points at Postgres, and that is the point of writing it now rather than then.
- Optimistic locking only guards what a single row's version covers. It is the right tool because this aggregate is one row (ADR-0008); it would not, on its own, protect an invariant spanning several.
