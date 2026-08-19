# 0010 — Allow command handlers to return the physical outcome of an action

- **Status**: accepted
- **Date**: 2026-08-19

## Context and problem statement

Command-Query Separation says a method either changes something or answers something, never both. Two of this machine's commands break that rule: buying returns the item and the change, and pressing return-coin gives the coins back. Either the rule bends here or the design has to work around it.

## Decision drivers

- The brief defines the machine's *responses* as the physical output: `1, GET-WATER -> WATER, 0.25, 0.10`.
- Coins that fall into the tray are no longer machine state; a later query would correctly report them as gone.
- Whatever is chosen has to stay consistent with the single-aggregate rule from ADR-0005.

## Considered options

1. Handlers return `void` unless the result physically left the machine; the two that do return it.
2. Strict CQS: the client generates a purchase id, the handler returns nothing, and a follow-up query reads the result back.
3. Model the output tray as machine state, so the result is readable by the normal state query.

## Decision outcome

**Chosen: option 1**, with the rule stated precisely: *a command answers only when its effect cannot be recovered by any later question*. Three of the five use cases return nothing; the two that do return something the machine no longer has.

Option 2 is the purest and was rejected on a stronger ground than taste. Reading the result back later means the result must have been written down, and writing a purchase record in the same transaction as the machine change means two aggregates in one transaction — breaking the exact rule used to justify a single aggregate in ADR-0005. Consistency of principle beat consistency with a textbook. It would also have bought idempotency for free, which is a real loss, recorded below and left as documented future work.

Option 3 was rejected because a shared tray is a shared mutable resource: two clients hitting the API concurrently could collect each other's change, and the aggregate would grow to hold something no business rule needs.

### Consequences (positive)

- The API can answer a purchase in one round trip, with the item and the change the customer is owed.
- The rule is testable rather than stylistic: the tests assert that after a purchase the machine no longer holds the change, which is what makes the return value necessary.

### Consequences (negative)

- It is a deviation from CQS, and a reviewer who applies the rule strictly will flag it. The defence is the criterion, not the exception.
- Idempotency is left unsolved: a retried purchase vends twice. An `Idempotency-Key` header with a store of processed keys is the intended answer, deliberately not built.
- The command bus has to be able to carry a return value, which is why it uses Messenger's `HandleTrait` and insists on exactly one handler per command.
