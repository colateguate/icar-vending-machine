# 0006 — Express change selection as a pluggable domain policy and default to the optimal strategy

- **Status**: accepted
- **Date**: 2026-08-19

## Context and problem statement

When a customer overpays, the machine must hand back the exact amount from the coins it physically holds. The obvious implementation — take the largest coin that fits, repeat — is wrong here in a way that costs money, and the wrongness is invisible in every example the brief gives.

## Decision drivers

- Change must add up exactly; there is no rounding policy to fall back on.
- The reserve is finite, and its contents change with every sale.
- A refused sale is lost revenue, so refusing when the coins were there is a defect, not a preference.
- The 1.00 coin is accepted but must never be dispensed (ADR-0004).

## Considered options

1. A `ChangeStrategy` port with a bounded-coin dynamic-programming implementation wired as the default, keeping a greedy implementation as a tested counterexample.
2. Greedy only, as a method on `CoinCollection`.
3. Dynamic programming only, no interface.

## Decision outcome

**Chosen: option 1.** Greedy is provably optimal only for an *unconstrained canonical* coin system, and neither precondition holds:

- **The reserve is finite.** Owing 0.30 with one quarter and three dimes, greedy commits to the quarter, is left owing 0.05 it does not hold, and refuses a sale it had the coins to serve. Verified side by side: greedy refuses, the DP returns three dimes.
- **Canonicality is a property of the data, not the code.** `{5, 10, 25}` happens to be canonical, so greedy agrees with the optimal answer on every example in the brief — including example 3 (1.00 for a 0.65 item → 0.25 + 0.10). Add a 20-cent coin and 40 becomes 25+10+5 instead of 20+20. A future product decision would silently break an algorithm whose correctness nobody wrote down.

Those two facts are what make the interface a correctness requirement rather than speculative flexibility, which is the usual and fair objection to a strategy pattern behind a single caller. The greedy implementation is kept, wired to nothing, and covered by a test named `test_greedy_refuses_a_sale_the_optimal_strategy_serves`: deleting it would delete the argument.

A second, independent reason: the aggregate takes the strategy as a method parameter (ADR-0005's double dispatch), so a unit test of `purchase()` can pass a fixed stand-in and assert what the aggregate does with change rather than re-testing the algorithm through it. Without the interface, every aggregate test would be coupled to the real dynamic-programming table.

Both implementations live in `Domain/Dispensing/`, not in `Infrastructure/`. They adapt nothing external — their only collaborators are `Money`, `CoinCollection` and `CoinDenomination` — and computing which coins to hand back is business policy. They are domain services in Evans' sense: logic that belongs to no single entity or value object. The test for that classification: a stand-in strategy used to isolate an aggregate test would obviously not belong in an infrastructure folder.

Two further decisions follow:

- **The strategies filter dispensable denominations themselves.** The port promises never to return a coin the machine cannot hand back. If that depended on the aggregate remembering to filter its pool first, it would be a convention at a call site, not a guarantee of the port.
- **The port's promises are enforced by a shared contract test**, with completeness deliberately left out of it: a strategy is allowed to refuse a sale it could have served, so "never refuses when a combination exists" is tested only against the implementation that guarantees it — against an exhaustive-search oracle over the same reserve.

### Consequences (positive)

- No sale is ever lost to the change algorithm when the coins are present, proven against an independent brute-force oracle over randomised reserves.
- Minimising coin count falls out of the same table, which spends large coins first and preserves the small ones future change depends on.
- Adding a denomination cannot silently invalidate the algorithm.

### Consequences (negative)

- The DP is more code than a greedy loop and needs a comment to be readable; a reviewer meeting it cold will spend a minute on the table before it clicks.
- Its cost is O(denominations × amount × count), fine for a machine holding tens of coins and paying under a couple of units, but it is not the algorithm to reach for if a reserve ever held thousands of coins. Binary-splitting the counts would fix that, and is deliberately not done for a problem we do not have.
- Two implementations of one interface means one of them is dead weight at runtime, and its only justification is the argument it preserves. That is a real maintenance cost, accepted knowingly.
- **Mutation score is 95%, not 100%, and it stays that way on purpose.** Around fourteen mutants of the dynamic-programming table survive: shifting the base score, pre-emptying a row that PHP would create anyway, starting a loop counter one below zero. None is observable through the port, because the table's absolute values are an internal ordering device and only the *selection* leaves the method — verified by running the mutated variants over ~14,000 randomised reserves (every total exact, the reserve never over-drawn) and ~4,000 minimality comparisons against an exhaustive oracle. They could be silenced in `infection.json5` the way one equivalent `CastInt` pair already is, and they are not: that suppression is scoped to a single mutator on a single method with a two-mutant proof, whereas muting six mutators across the algorithm's core would also hide the next *real* defect introduced there. A safety net on the most intricate code in the project is worth more than a round number.
