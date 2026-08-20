# Architecture decision records

One record per decision, written in the same commit as the decision it records — not reconstructed afterwards, which is how an ADR turns into a justification.

Every one of them names the alternatives that were actually considered and at least one real downside of what was chosen. An ADR with no negative consequences is marketing.

| # | Decision | The question it settles |
|---|---|---|
| [0001](0001-hexagonal-architecture-symfony-at-the-edge.md) | Hexagonal architecture, Symfony at the edge | Where does the framework live? |
| [0002](0002-single-bounded-context-layers-inside.md) | One bounded context, layers nested inside it | `src/Domain/VendingMachine` or `src/VendingMachine/Domain`? |
| [0003](0003-cqrs-as-buses-no-event-sourcing.md) | CQRS as command/query buses, no event sourcing | Why a bus for four use cases — and why events are not the source of truth |
| [0004](0004-money-as-integer-cents.md) | Money as integer cents, decimal strings on the wire | Why a float is never allowed near an amount |
| [0005](0005-single-aggregate-root.md) | A single aggregate root | Why inventory, coins and escrow are not three aggregates |
| [0006](0006-pluggable-change-strategy-optimal-default.md) | Pluggable change strategy, optimal by default | Why greedy is kept in the codebase as a tested counterexample |
| [0007](0007-reject-purchase-when-change-unavailable.md) | Refuse the sale when change cannot be composed, keep the coins | The situation the brief never mentions |
| [0008](0008-doctrine-sqlite-xml-mapping.md) | Doctrine on SQLite, XML mapping, aggregate stored as one row | Why the catalogue is a JSON column and not a table |
| [0009](0009-two-adapters-shared-contract-test.md) | Two repository adapters, one abstract contract test | How do you know the in-memory double is not lying? |
| [0010](0010-command-handlers-may-return-physical-outcome.md) | A command may return what physically left the machine | The one exception to "commands return nothing" |
| [0011](0011-optimistic-locking-for-concurrent-purchases.md) | Optimistic locking, answered as 409 | Two people, one last can |
| [0012](0012-rfc7807-errors-with-explicit-status-rule.md) | RFC 7807 errors from an explicit catalog | 422 or 409 or 404 — and who decides a request is invalid |
| [0013](0013-enforce-boundaries-with-deptrac-phpstan-max.md) | Deptrac + PHPStan at max, no baseline | Why the architecture is checked and not merely agreed |
| [0014](0014-four-test-levels-mutation-gated-domain.md) | Four test levels, mutation testing over the core | What each suite is for, and why MSI instead of line coverage |
| [0015](0015-openapi-as-a-tested-contract.md) | A hand-written OpenAPI document, tested against real responses | Why not generate the spec from the code — and why not a Postman collection |

## The four worth reading first

If you have ten minutes and want the decisions this project would be defended on:

1. **[0005](0005-single-aggregate-root.md)** — one aggregate, because a purchase enforces one invariant across stock, coins and escrow at the same instant, and a dispensed can cannot be compensated.
2. **[0007](0007-reject-purchase-when-change-unavailable.md)** — the brief's silence turned into a decision, with the money left where the customer can still get it back.
3. **[0008](0008-doctrine-sqlite-xml-mapping.md)** — the most arguable one: the aggregate is stored as a single row, and the cost is stated rather than hidden.
4. **[0012](0012-rfc7807-errors-with-explicit-status-rule.md)** — one rule generates every status code, and a test makes sure no domain failure escapes it.

And [0015](0015-openapi-as-a-tested-contract.md) if you want the one where the alternative nearly won: a generated spec cannot drift, and it still lost.

Format is [MADR](0000-template.md), lightly adapted.
