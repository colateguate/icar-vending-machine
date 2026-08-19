# 0005 — Model the vending machine as a single aggregate root

- **Status**: accepted
- **Date**: 2026-08-19 (decision taken and implemented earlier — see Notes)

## Context and problem statement

The machine tracks three things that all move during a sale: what is on the shelves, what coins it can pay change with, and what a customer has put in and not yet spent. How those are grouped decides what can be true at once, what has to be locked together, and what a future fleet-management feature would cost.

## Decision drivers

- A purchase must be all-or-nothing: there is no compensating action for a can that has already dropped.
- The design has to survive the question "does that aggregate grow without bound?".
- Fleet-level concerns (telemetry, restocking routes, cash reconciliation) will eventually exist and must not be blocked by this choice.

## Considered options

1. One aggregate, `VendingMachine`, holding inventory, change reserve and escrow.
2. Three aggregates — `Inventory`, `CoinBank`, `Purchase` — coordinated by a process manager.
3. Two aggregates: `Inventory` and `CoinBank`, with the escrow held by the session.

## Decision outcome

**Chosen: one aggregate.** An aggregate is a *transactional consistency boundary*, and the rule that follows is one aggregate modified per transaction. A purchase enforces an invariant spanning all three at the same instant:

> a product leaves the shelf **if and only if** its price is covered **and** the exact change can be composed from coins physically inside the machine.

Split those into separate aggregates and that invariant becomes eventually consistent: stock would be decremented, the change attempt would fail afterwards, and something would have to un-vend the can. Nothing can un-vend a can. Options 2 and 3 therefore require a compensating action that cannot exist, which is not a trade-off but a defect.

The obvious objection — an aggregate holding everything grows unbounded — does not apply, because **it scales by instance rather than by size**. There is one aggregate per physical machine, `MachineId` is already the natural partition key, and a fleet of ten thousand machines is ten thousand small aggregates rather than one large one. What would legitimately split off later is fleet-level behaviour, and that becomes a separate bounded context consuming the events this one records — not a second aggregate inside it.

`Purchase` is deliberately not an aggregate either. It would be one if the business had a purchase lifecycle to manage — refunds, disputes, receipts — but today a purchase is an instantaneous transformation with no life of its own. It is modelled as a domain event (`ProductDispensed`) plus a returned value (`DispensedGoods`), which is also the seam through which a purchase read model could later be built without touching the domain.

Two implementation consequences follow:

- **The aggregate is the only mutable thing in the domain.** Everything inside it — inventory, products, coin collections — is an immutable value that gets replaced. There is no shared mutable state to reason about and no defensive copying at the boundaries.
- **Policies arrive as method parameters, not constructor dependencies.** `purchase()` takes the `ChangeStrategy` it should use. An aggregate has to be reconstructible from storage without wiring services into it, and passing the policy per call is what lets a test swap it. The term for it is double dispatch.

## Notes

This record was written during ticket 09, three tickets after the decision was implemented in ticket 06 — where the acceptance criteria called for it and it was missed. The reasoning had been captured in the aggregate's own docblock and in the commit message, so nothing was lost, but an ADR is meant to be readable without opening the code, and it was not there. Recorded plainly rather than quietly backdated.

### Consequences (positive)

- A sale is atomic by construction, and the tests can assert that a refused purchase leaves the machine byte for byte unchanged.
- One lock, one transaction, one clear owner of every invariant that spans stock and cash.
- Adding a machine to a fleet costs nothing structurally.

### Consequences (negative)

- Every operation, including inserting a single coin, loads and saves the whole machine. Irrelevant at this size; it would matter if the inventory ran to thousands of lines.
- Two clients acting on the same machine contend for the same aggregate, which is why optimistic locking is planned (ADR-0011) rather than optional.
- Fleet-wide questions such as "how much cash is out there right now" cannot be answered by querying this aggregate and will need a read model fed by its events.
