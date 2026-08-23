# 18. Coins the hardware reads, and coins the machine takes

Date: 2026-08-22

## Status

Accepted

## Context

The machine was delivered accepting four denominations — 0.05, 0.10, 0.25 and 1.00 — held as a closed enum, `CoinDenomination`. That was right while every machine took the same coins: the set is a physical property of a coin acceptor, closed and known when the code is compiled, and making it an enum bought `tryFrom()` as validation, `cases()` as exhaustiveness and a `match` that static analysis forces an answer out of whenever the set changes.

Two facts arrived that the model could not express. Real machines also take 0.50 and 2.00 pieces (and never the 0.01 and 0.02, which no vending machine accepts). And operators switch denominations on and off: a jamming acceptor, a coin being withdrawn, or simply taking the machine out of service. The delivered model has no place to put either. Widening the enum answers the first and not the second, because "which coins does this machine take" then has one answer for every machine ever deployed.

There is also a question the brief never asks and an operator does: what happens to the coins already in the till when their denomination is switched off. Money does not leave the machine because a setting changed.

## Decision

Split the one question into the two it always was.

**`CoinDenomination` stays an enum and grows to six cases** — 5, 10, 25, 50, 100, 200. It answers what the coin acceptor can *read*, which is hardware, closed, and known at compile time. Adding the two cases made PHPStan demand an answer from `isDispensableAsChange()` for each, which is the design working exactly as ADR-0006 intended.

**`AcceptedCoins` is a new value object held by the aggregate** — the set of denominations this machine currently *takes*. It is state: a service visit replaces it outright, alongside the catalogue and the till, and two machines in the same lobby may disagree.

Three rules follow, and they are the whole feature:

- **The slot refuses a coin the machine is not taking**, with `CoinNotAccepted` — deliberately not `UnsupportedCoin`, which says something else and permanent. Both are 422; a client can tell "this machine is not taking 0.50 today" from "no machine of this model reads that piece".
- **An empty set is legal, and it means out of service.** Nobody can insert, so no escrow can be built, so nothing can be bought. The state needs no flag of its own because the model already says it.
- **A machine never hands back a coin it would refuse to take.** Coins of a switched-off denomination stay in the till — they are the machine's money — and are excluded from what a change policy may draw on. They are stranded, not lost: a sale narrows the pool it offers the policy, never the collection it stores.

Dispensability follows the brief's own rule one denomination further up: 0.50 comes back as change, 1.00 and 2.00 go in and never come out.

Machines that already exist take the four the brief names. That is not a neutral default; it is the behaviour they had the instant before the migration ran.

## Consequences

The three examples of the brief keep passing untouched, because a machine provisioned from the CLI still takes exactly the four coins it used to. The feature exists on top of the delivered behaviour rather than in place of it, and enabling 0.50 from a service visit is now the shortest demonstration of the extensibility the challenge asks about.

The public state gains a real distinction: `acceptedCoins` keeps its meaning and narrows to what the machine takes today, so nothing published changes shape for a machine that was never reconfigured.

**The negative consequences, stated plainly.**

Stranded money is money the machine holds and cannot pay out, and `exactChangeOnly` can light up with a full till. That is the honest reading of "never hand back a coin you would refuse", and it is a real operational cost: a technician who switches a denomination off should take its coins out on the same visit, and nothing in the software makes them.

`PUT /api/machine/service` has no authentication — the brief describes no actors, and that trade-off was recorded at delivery. This decision widens what its absence exposes: the same unauthenticated request that could already empty the stock can now take the machine out of service by switching every denomination off. A minimum-of-one-denomination invariant was considered and rejected: it would forbid a state a technician legitimately wants, while leaving stock-emptying and price-rewriting untouched — a guard that stops one shape of the same hole. The hole is authentication, and it is named here rather than papered over.

The service payload's coin field is optional, meaning "this visit does not touch the acceptor". An absent field and an empty list therefore mean different things — leave it alone, and take nothing from now on — which is a distinction a client can get wrong. It is carried in the command as a nullable field and resolved in the application layer, so the aggregate is always told outright and never has to reason about "unspecified".

## Alternatives considered

**Make denominations data.** Drop the enum and let coins be rows, so a new denomination needs no deployment. It buys flexibility this problem does not have — the set of coins in a currency changes on the timescale of decades — and it costs the property that makes the current design defensible: adding a coin today makes static analysis stop the build until someone answers whether it comes back as change. That question would become a nullable column nobody is forced to fill in.

**Widen the enum and stop there.** Six coins, always all accepted. It is a two-line change and answers half the request: an operator still cannot switch a jamming denomination off, and "out of service" remains unrepresentable.

**Forbid the empty set** (at least one denomination always enabled). It was the first instinct, and it was wrong twice over: it makes a legitimate operation impossible, and the denial-of-service it appears to prevent is available anyway through stock and prices. Out of service is a state a machine genuinely has.

**Keep stranded coins dispensable** — the acceptor and the dispenser are different mechanisms, so a machine could hand back a 0.50 it would no longer take. It drains money that would otherwise sit there, and was rejected because it hands the customer a coin the machine in front of them will refuse. Consistency with the 1.00 rule, which is also about what comes out, decided it.

**Validate the till against the accepted set** — refuse a service payload that declares coins of a switched-off denomination. It sounds like tightening an invariant and instead makes the truth unsayable: those coins are physically in the machine, and SERVICE states what is in the machine.
