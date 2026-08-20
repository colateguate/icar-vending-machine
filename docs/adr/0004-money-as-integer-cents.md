# 0004 — Represent monetary amounts as integer cents in a Money value object

- **Status**: accepted
- **Date**: 2026-08-19

## Context and problem statement

Every rule in this domain is arithmetic on money: totalling inserted coins, comparing against a price, composing change. The representation chosen here propagates into the aggregate, the persistence mapping and the JSON contract, and a defect in it is not a rendering glitch — it is missing money.

## Decision drivers

- Equality and accumulation must be exact: change selection compares sums for strict equality.
- The representation must survive a database round-trip and a JSON round-trip unchanged.
- The accepted coin set is closed (0.05, 0.10, 0.25, 1.00) and known at compile time.
- The brief accepts four coins but lists only three valid coin *responses*.

## Considered options

1. `Money` value object holding an `int` number of cents.
2. `float` amounts.
3. `bcmath` / a money library such as `brick/money`.

## Decision outcome

**Chosen: a `final readonly Money` holding integer cents, with private constructor and named constructors.** Floats are unusable here: under IEEE-754 `0.1 + 0.2 !== 0.3`, so both equality and accumulation are unsound, and rounding drift becomes missing coins. Arbitrary-precision arithmetic solves a problem we do not have — a single currency with two decimals fits in an `int`, which is exact, totally ordered, hashable and free to serialize.

Three decisions follow from it:

- **Money crosses the wire as a decimal *string*** (`"0.65"`), never a JSON number, so the JavaScript client does not inherit the trap we just avoided. `fromDecimalString()` / `toDecimalString()` are the boundary.
- **`CoinDenomination` is a backed enum**, not a wrapper object. The set is closed and finite, so `tryFrom()` is validation, `cases()` is exhaustiveness, and `match` is statically checked. A `Coin` class wrapping this enum was considered and rejected: it would carry no state and no behaviour of its own, and every method would delegate — ceremony, not modelling. Should a coin ever gain properties (physical validation, counterfeit detection), the enum becomes its denomination and the class earns its place then.
- **`isDispensableAsChange()` encodes the spec asymmetry**: the machine takes a 1.00 coin and never returns one (confirmed by example 3, where 1.00 for a 0.65 item comes back as 0.25 + 0.10). It is written as an exhaustive `match` so that adding a denomination forces an explicit answer rather than inheriting a default.

Failures split into two families on purpose. What the caller got wrong (`InvalidMoneyAmount`, `UnsupportedCoin`) implements the `VendingMachineError` marker interface and will be mapped to 4xx at the edge. A broken invariant (a negative amount, subtracting coins the collection does not hold) throws a plain SPL exception: it is a bug, and a 500 is the honest answer rather than a 4xx blaming the caller for our mistake.

### Consequences (positive)

- Exact arithmetic, structural equality, and no rounding policy to define anywhere.
- The unit suite runs in milliseconds — no kernel, no I/O, no framework.
- Adding a denomination is one enum case plus the answers static analysis demands.

### Consequences (negative)

- Every amount entering or leaving the system needs explicit conversion; forgetting `toDecimalString()` would leak raw cents into the API, which only tests and review protect against.
- A second currency would require reworking `Money` (an amount alone is not a currency-safe type), and multi-currency arithmetic rules would have to be designed then rather than now.
- Integer cents put a theoretical ceiling on representable amounts; irrelevant for a vending machine, worth naming rather than pretending it does not exist.
