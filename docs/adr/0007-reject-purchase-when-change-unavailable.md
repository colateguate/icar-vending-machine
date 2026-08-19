# 0007 — Reject purchases that cannot be given exact change and keep the coins in escrow

- **Status**: accepted
- **Date**: 2026-08-19

## Context and problem statement

The brief says a customer who overpays "gets the item and change back". It never says what happens when the machine holds the item and the money but cannot compose the change from the coins inside it. That gap is not an edge case in the corner of the domain — it is the situation where the machine's three moving parts disagree, and every plausible answer commits us to something.

## Decision drivers

- A can that has dropped cannot be un-dropped: whatever we choose must never leave a sale half-done.
- The customer's coins are the customer's until a sale completes.
- The refusal has to be legible to whoever is on the other end, whether that is a person or an HTTP client.
- The brief's own examples must keep working unchanged.

## Considered options

1. Refuse the sale, leave the coins in escrow, and report how much change could not be paid.
2. Refuse the sale and automatically refund the escrow.
3. Dispense the item and short-change the customer, keeping the difference.
4. Dispense the item and round the change down to what the machine can pay.

## Decision outcome

**Chosen: option 1.** `CannotDispenseChange` is a domain error carrying the amount owed; the escrow is untouched, so `RETURN-COIN` remains the single path by which money leaves the machine.

Options 3 and 4 are theft with extra steps, however small the amount. Option 2 is the interesting one and it is still wrong: auto-refunding discards intent the customer already expressed. Someone who put 1.00 in for a 0.65 item may well want to pick the 1.00 item instead, and spitting their coins out forces them to start over. It also creates a second refund path with its own event, duplicating `ReturnCoins` for no gain.

Two mechanisms make the choice safe rather than merely stated:

- **Compute-then-commit.** `purchase()` resolves the product, checks the stock, checks the money and composes the change *before* it writes a single field. The refusal escapes with the aggregate untouched, which is asserted directly: a data-provider test drives all four refusals and compares inventory, reserve and escrow against snapshots taken beforehand, and also asserts that no event was recorded. A sale that did not happen must not be announced.
- **The change pool is the escrow plus the reserve.** The coins the customer just inserted are physically inside the machine, so they can pay their own change — a machine with an empty till can still hand back 0.05 out of the nickel just fed to it. The pool is handed to the change policy *unfiltered*, because the port already promises never to return a 1.00 coin (ADR-0006); filtering again at the call site would put one rule in two places.

The refusal is also announced before the fact. `requiresExactChange()` reports whether the till holds any coin it is allowed to hand back, which the API exposes as `exactChangeOnly` so a client can warn before a big coin goes in. It is deliberately narrow: predicting whether some *future* overpayment could be covered would need a change policy and a definition of the worst case the brief does not provide. The lamp is a warning; the refusal on the purchase remains the authoritative answer.

### Consequences (positive)

- No sale is ever half-completed, and the invariant is asserted rather than asserted-about: the till ends up richer by exactly the price, and a refused purchase leaves the machine byte for byte as it was.
- The customer keeps both their money and their options.
- One error, one meaning: 409 with `exact_change_required` and the amount at issue, distinct from "not enough money" (`insufficient_funds`, with the shortfall).
- An operational weakness becomes a product feature — the EXACT CHANGE ONLY lamp — instead of a surprise at the end of the flow.

### Consequences (negative)

- A customer can reach a dead end: money in, nothing buyable, and the only way out is pressing RETURN-COIN. The machine tells them why, but it does not solve it for them.
- Change selection now runs before anything is committed, so a purchase pays the cost of the dynamic-programming pass even when it is about to be refused. Irrelevant at this scale, and worth naming rather than pretending the ordering is free.
- `requiresExactChange()` under-warns by design: a till holding a single nickel reports that change is available, and an overpayment of 0.35 will still be refused. Closing that gap needs the richer definition this ADR declines to invent.
