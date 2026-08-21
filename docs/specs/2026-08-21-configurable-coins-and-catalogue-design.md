# Configurable coins and a manageable catalogue — Design

> **Status:** approved in the PM session of 2026-08-21. Tickets in `.claude/tasks/monedas-y-catalogo/`.

## Summary

The delivered machine accepts a fixed set of four coins and its service form can only edit stock counts. This epic makes the coin set configurable per machine — the hardware learns to read 0.50 and 2.00, and a technician can enable or disable each denomination from the service form, loading the till for the enabled ones — and turns the service form into a full catalogue manager: add, remove and edit products. The backend already accepts arbitrary catalogues (`PUT /api/machine/service` sets absolute values); the product half of this epic is UI work plus the client-side validation it implies.

## Problem / goal

Real vending machines take 0.50 and 2.00 coins (and never 0.01/0.02), and operators turn denominations on and off; this machine hardcodes four coins forever. Separately, stocking a *new* product — which the API has supported since day one, as the README demonstrates with curl — is impossible from the panel, because the service form renders products as read-only labels with a count input. Success criteria are listed below and are testable.

## Scope

- `CoinDenomination` grows to six cases: 0.05, 0.10, 0.25, 0.50, 1.00, 2.00 — what the hardware can *read*.
- A per-machine **enabled set** as aggregate state — what this machine currently *takes* — managed by the service visit and defaulting to the brief's four.
- Dispensability: 0.50 joins the dispensable set; 1.00 and 2.00 go in and never come out (the brief's own rule, applied to the newcomers).
- A disabled coin is out entirely: the slot refuses it (422), the service payload may not load it into the till (422), and it never leaves as change.
- API: `acceptedCoins` keeps its meaning (the enabled set — existing clients unchanged); a new `supportedCoins` field lists all six with an `enabled` flag for the service form.
- Panel service form: a toggle per denomination in the till section, and full catalogue management — add, remove and edit product rows (selector, name, price, count) with client-side selector validation mirroring the server's format.

## Non-scope (explicit)

- **Data-driven denominations** (adding coins without a deployment) — it would dismantle the typed-extensibility argument the README demonstrates (§"A new coin"); revisit only if coin sets ever vary per market.
- **Gradual retirement** (refuse at the slot but keep dispensing what remains) — two behaviours under one flag; if ever wanted, it is a third state, not a reinterpretation of this one.
- **Banknotes, 0.01, 0.02** — vending machines do not take them; excluded by the requester.
- **Technician authentication** — unchanged from the delivered trade-off table; this epic neither needs it nor worsens its absence.

## Interview decisions

| # | Question | Decision | Type |
|---|---|---|---|
| K1 | Coin model | Enum of 6 (hardware capability) + per-machine enabled set (aggregate state) | user |
| K2 | Which new coins are dispensable | 0.50 yes; 2.00 no (1.00 stays no) | user |
| K3 | Meaning of "disabled" | Out entirely: slot, till and change | user |
| K4 | Factory default | The brief's four enabled; 0.50/2.00 born disabled — the three brief examples stay green untouched | user |
| K5 | Catalogue UI scope | Add + remove + edit (selector, name, price, count) | user |
| K6 | API shape | `acceptedCoins` unchanged in meaning; new `supportedCoins` with `enabled` flag | user |

## Assumptions (defaulted minor gaps, validated at definition approval)

- Client-side selector validation mirrors `/^[A-Z][A-Z0-9_-]{0,31}$/` (`docs/openapi.yaml:616`); the server's 422 remains the safety net.
- Existing machines migrate to the brief's four enabled (new migration alongside `backend/migrations/Version20260819152732.php`).
- The service payload's till rows carry the flag (exact request shape is ticket 03's design decision); new error codes join `ErrorCatalog` and the OpenAPI document — the two bidirectional contract tests prevent drift.
- `unsupported_coin` and `invalid_product_selector` become reachable from the panel; the reachability comments in `MachineDisplay.jsx:47-83` change accordingly.
- `backend/infection.json5:71` ("THIS SUPPRESSION EXPIRES if a non-canonical denomination is ever…") is revisited in ticket 01.
- The e2e bar in `frontend/e2e/README.md` holds: fixtures update, new browser specs only if something jsdom cannot see appears.

## System-level design

One repo, both halves. **Domain** (`backend/src/VendingMachine/Domain/`): `CoinDenomination` gains `FIFTY_CENTS = 50` and `TWO_UNITS = 200` (`CoinDenomination.php:21-24`) and the exhaustive `isDispensableAsChange()` match (`:40-45`) answers for them; `VendingMachine` gains the enabled set as a field beside `changeReserve` (`VendingMachine.php:64`), `service()` (`:192`) takes it, `insert()` refuses disabled coins with a new named domain error, `requiresExactChange()` (`:176`) intersects dispensable with enabled, and the change-strategy pool is narrowed the same way (strategies filter via `CoinCollection::dispensableOnly()`, `CoinCollection.php:108` — the filter grows an enabled dimension whose exact seam ticket 01 decides). **Persistence**: one new mapped field in `config/doctrine/Machine.VendingMachine.orm.xml` (beside `:35-36`) with a DBAL custom type, plus a migration defaulting existing rows to the brief's four. **Contract** (`Delivery/Http/`): `ServiceMachineRequest::changeReserveIn` (`ServiceMachineRequest.php:87`) reads the flag and refuses count>0 on disabled rows; `GetMachineStateHandler` (`GetMachineStateHandler.php:31`) stops publishing `CoinDenomination::cases()` raw and publishes enabled-only as `acceptedCoins` plus all six as `supportedCoins`; `ErrorCatalog.php:43` gains the new 422 row(s); `docs/openapi.yaml:607` (the `Denomination` enum) widens and the machine-state schema gains `supportedCoins`. **Panel**: `ServiceDrawer.jsx` grows the per-coin toggle (seeded from `supportedCoins` instead of `acceptedCoins`, `serviceForm.js:15-24`) and full product-row management (today counts only, `ServiceDrawer.jsx:85-90`); `CoinButtons.jsx:15-34` needs no change (still `acceptedCoins`). No queues, no cross-repo seams.

## Ticket breakdown

| NN | Title | Repo | Priority | Depends on |
|---|---|---|---|---|
| 01 | Domain: six-case enum and the enabled set on the aggregate (+ADR) | icar-vending-machine | high | — |
| 02 | Persistence: enabled-set column, DBAL type and default migration | icar-vending-machine | high | 01 |
| 03 | Contract: service manages coins, `supportedCoins`, new errors, OpenAPI | icar-vending-machine | high | 02 |
| 04 | Panel: till section with a toggle per denomination | icar-vending-machine | medium | 03 |
| 05 | Panel: catalogue with add, remove and edit | icar-vending-machine | medium | 03 |
| 06 | Deliverable docs: README coins/extend sections, assumptions, testing strategy | icar-vending-machine | low | 04, 05 |

Branching: `release/configurable-machine` cut from `main`; one `feat/*` branch per ticket against it; PRs opened by the human (repo rules).

## Risks

- **The three brief examples are literal acceptance tests** → mitigated by K4: factory default keeps the brief's exact behaviour, and criterion 1 makes it a gate.
- **Greedy-vs-optimal narrative under a new coin set** → {5, 10, 25, 50, 100} keeps the 0.30/{0.25,0.10×3} counterexample intact; ticket 01 re-checks the suppressed mutants (`infection.json5:64-71`).
- **`additionalProperties: false` on the service request** means the frontend must send exactly the negotiated shape — the current form deliberately strips extra fields (`ServiceDrawer.jsx:102-104`); ticket 03 owns the shape, tickets 04/05 follow it.
- **Semantic drift of `acceptedCoins`** → prevented by K6: its meaning is frozen; anything new gets a new name.

## Success criteria

1. The three brief examples pass unmodified (CLI + HTTP acceptance, literal output).
2. From the panel: enable 0.50, load the till, and the 0.50 button appears; a purchase overpaid by 0.50 returns it as change.
3. From the panel: disable a coin (till at 0) and inserting it answers 422, surfaced by `code`.
4. From the panel: add TEA 0.80×4 and it is immediately purchasable; remove a product and it disappears; edit a price and it shows.
5. `make qa` green · MSI 100% holds · OpenAPI validated in both directions · Deptrac clean.

## References

- Investigation (frontend): `CoinButtons.jsx:15-34` (buttons are data-driven), `serviceForm.js:15-24` (till rows seeded from `acceptedCoins`), `ServiceDrawer.jsx:85-90` (counts are the only mutation), `ServiceDrawer.jsx:92-107` (payload shape and the deliberate flag strip), `machineApi.js:41-43` (service signature).
- Backend seams: `CoinDenomination.php:21-45` · `VendingMachine.php:64,131,176,192` · `CoinCollection.php:108` · `ServiceMachineRequest.php:51-98` · `GetMachineStateHandler.php:31` · `MachineStateResponse.php:29,45-57` · `ErrorCatalog.php:43-45` · `ProvisionMachineCommand.php:40-75` (factory catalogue and reserve) · `Machine.VendingMachine.orm.xml:32-36` · `docs/openapi.yaml:607`.
- Prior decisions this design leans on: ADR-0005 (single aggregate), ADR-0006 (pluggable change strategy), ADR-0007 (refuse without change), ADR-0012 (error contract), README §"How to extend it".
