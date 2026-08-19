# Assumptions

Everywhere the brief was silent, a decision was still required. This is the list, so a reader can tell a deliberate interpretation from an oversight.

## The machine never gives back a 1.00 coin

The brief accepts four coins (0.05, 0.10, 0.25, 1) but lists only three as valid *responses*. Example 3 confirms it: 1.00 for a 0.65 item comes back as 0.25 + 0.10, never as a coin of 1.00. Modelled explicitly as `CoinDenomination::isDispensableAsChange()` and enforced inside the change policy, so no caller can forget it.

## SERVICE sets absolute values

"A service person opens the machine and set the available change and how many items we have" is read as *set*, not *add*. Topping up would leave a technician unable to remove a discontinued product. Any money a customer had inserted is returned first: someone opening the machine does not get to keep it.

## Coins already inserted can pay their own change

The coins a customer just put in are physically inside the machine, so they join the reserve when change is composed. A machine with an empty till can still hand back 0.05 out of the nickel it was just fed.

## A sale that cannot be given change is refused, and the money stays put

The brief never says what happens when the change cannot be composed. The sale is refused and the coins stay in escrow, so the customer can take them back or pick something cheaper. Recorded in ADR-0007.

## There is one machine, and its identity is configuration

The API route is `/api/machine`, not `/api/machines/{id}`. The aggregate already carries a `MachineId`, so serving a fleet is a routing change rather than a redesign — but building it now would be speculative.

## A single implicit currency

Amounts are integer cents with no currency attached. A `Currency` value object inside `Money` is the extension point; it is deliberately not built, because nothing in the brief needs it.

## No authentication, including on the service endpoint

`PUT /api/machine/service` replaces the entire inventory and change reserve, and it is unauthenticated. That is a consequence of the exercise's scope rather than an oversight: the brief describes no actors, no roles and no credentials, and inventing an auth scheme would add a system to defend without a requirement to justify it.

A real deployment would need at minimum an authenticated technician identity and an audit trail tying each service visit to a person. The `MachineServiced` event already records what was loaded, so the missing piece is *who*. Written down here rather than left to be noticed, because an unauthenticated write endpoint that nobody mentions reads as a mistake.

## Request payloads are validated at the edge, not in the handlers

Commands carry primitives, and their PHPDoc declares the exact shape expected. Checking that a JSON body actually has that shape is the delivery layer's job: a malformed request is answered with 422 before a command object exists. A handler that receives a malformed payload is therefore looking at a bug in the adapter, not at bad user input, which is why it fails with a plain 500 rather than pretending the caller is at fault.

## Idempotency is not implemented

A retried purchase request vends twice. The intended solution is an `Idempotency-Key` header with a store of processed keys; it was left out because the alternative — writing a purchase record in the same transaction as the machine change — would put two aggregates in one transaction and break the rule that justifies the single aggregate (ADR-0005, ADR-0010).

## Concurrency is detected, not prevented

Two simultaneous purchases are handled with optimistic locking: the second writer fails and gets a 409. Automatic retries, pessimistic locking and distributed locks are deliberately out of scope — real contention on one machine is effectively zero, so the cheap detector is the right tool and the expensive serialiser is not.

## Coin collections travel as lists, not as maps keyed by cents

A bag of coins is `[{"denomination": "0.25", "count": 4}]` in both directions, rather than `{"25": 4}`. The map is closer to how the model counts — integer cents — and that is the argument against it: every other amount in this API is a decimal string, and a response mixing `"price": "0.65"` with a key of `25` describes two units in one document. Translating the list into the cents the command carries is the delivery layer's job, which is where the rest of the wire format is decided too.
