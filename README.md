# Vending Machine

The brief leaves the interface deliberately open. This solution answers with two — a JSON API and a command line that takes the statement's own syntax — because a second door is what proves the first one was not the application in disguise. Neither of them contains a price, a rule or a calculation: hexagonal architecture, tactical DDD and CQRS over command and query buses are what makes that true rather than what it is called.

```bash
make up
docker compose exec backend php bin/console app:machine:run "1, 0.25, 0.25, GET-SODA"
# -> SODA
```

> **Status.** Both halves are built and both are containerised: domain, use cases, HTTP API, CLI and persistence on one side; the React panel, its own image and a reverse proxy on the other, with `make up` serving the pair. What is left in the [backlog](.claude/tasks/vending-machine-challenge/) is follow-up the reviews turned up rather than anything the brief asked for.

---

## What the machine does

- Takes **0.05, 0.10, 0.25 and 1.00**, and gives back only the first three — it accepts a 1.00 coin and never dispenses one ([why](docs/assumptions.md))
- Sells **Water 0.65, Juice 1.00, Soda 1.50**, each with a selector, a price and a stock count
- **RETURN-COIN** hands back the very coins that went in
- Overpaying returns the item **and the change**, composed from the coins physically inside the machine
- When the change cannot be composed, **the sale is refused and the money stays put** — the situation the brief never mentions, decided in [ADR-0007](docs/adr/0007-reject-purchase-when-change-unavailable.md)
- **SERVICE** lets a technician set what the machine stocks and how much change it holds

## Requirements

**With Docker** — nothing else:

- Docker and Docker Compose v2

**Without Docker:**

- PHP >= 8.2 with `pdo_sqlite`, and Composer 2 — for the API and the CLI
- Node >= 22.22.2 and npm — only to run the panel outside its image; below that floor the `front-*` targets warn rather than stop
- GNU Make is optional; every target is a one-line wrapper you can read in the [`Makefile`](Makefile)

There is no database service to install. The machine lives in SQLite ([ADR-0008](docs/adr/0008-doctrine-sqlite-xml-mapping.md)).

## Quick start

```bash
make up
```

That builds both images, applies migrations, provisions a machine with the catalogue of the brief, and serves the panel on **http://localhost:3000** and the API on **http://localhost:8000**.

Two ports, and only the first is needed: the panel's nginx forwards `/api` to the backend, so the screen and the API answer on one origin. The API keeps its own port because the brief's examples are curl commands. It is ready when the healthcheck goes green:

```bash
curl localhost:8000/api/health
# {"status":"ok"}
```

Other targets:

```bash
make down     # stop it, keep the machine
make reset    # stop it and throw the machine away — the way to undo an evaluator's shopping
```

<details>
<summary><b>Without Docker</b></summary>

```bash
cd backend
composer install
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:machine:provision
php -S localhost:8000 -t public public/index.php
```

`app:machine:provision` is idempotent — run it as often as you like.
</details>

## Try it in a minute

The three examples printed in the brief, pasted verbatim into a terminal:

```bash
docker compose exec backend php bin/console app:machine:run "1, 0.25, 0.25, GET-SODA"
# -> SODA

docker compose exec backend php bin/console app:machine:run "0.10, 0.10, RETURN-COIN"
# -> 0.10, 0.10

docker compose exec backend php bin/console app:machine:run "1, GET-WATER"
# -> WATER, 0.25, 0.10
```

Those three lines are also acceptance tests, asserting that literal output. The same three exist again over HTTP, again at the bus level, and again as unit tests against the aggregate — four levels, four different questions.

Or drive it over HTTP:

```bash
curl -X POST localhost:8000/api/machine/coins -H 'Content-Type: application/json' -d '{"coin":"1.00"}'
curl -X POST localhost:8000/api/machine/purchases -H 'Content-Type: application/json' -d '{"selector":"WATER"}'
```

## API

JSON only. Money is always a **decimal string** — never a JSON number, because a number is a float on the client's side and floats are how you lose a cent ([ADR-0004](docs/adr/0004-money-as-integer-cents.md)).

Every response carries the resulting state of the machine under `machine`; the two things that physically left it travel alongside.

**The whole contract is published as [`docs/openapi.yaml`](docs/openapi.yaml)** (OpenAPI 3.1) — import it into Postman with *Import → File*, or into Insomnia, Bruno or Swagger UI, and you get every endpoint with worked examples. There is no Postman collection in this repository on purpose: a collection is a derived artifact, and the derivative is the copy that goes stale.

It stays true because it is executed. Every response the acceptance suite produces is validated against it, so a change of shape without a change of document fails the build ([ADR-0015](docs/adr/0015-openapi-as-a-tested-contract.md)).

| Method | Path | Does |
|---|---|---|
| `GET` | `/api/machine` | The whole state a customer can see |
| `POST` | `/api/machine/coins` | Insert one coin |
| `POST` | `/api/machine/coins/return` | The RETURN-COIN button |
| `POST` | `/api/machine/purchases` | Press a product button |
| `PUT` | `/api/machine/service` | A technician sets stock and change |
| `GET` | `/api/health` | Liveness, for the container healthcheck |

<details open>
<summary><b>GET /api/machine</b></summary>

```json
{"machine":{
  "products":[
    {"selector":"JUICE","name":"Juice","price":"1.00","count":10},
    {"selector":"SODA","name":"Soda","price":"1.50","count":10},
    {"selector":"WATER","name":"Water","price":"0.65","count":10}],
  "changeReserve":{"coins":[
    {"denomination":"0.05","count":20},
    {"denomination":"0.10","count":20},
    {"denomination":"0.25","count":20}],"amount":"8.00"},
  "insertedCoins":{"coins":[],"amount":"0.00"},
  "acceptedCoins":[
    {"denomination":"0.05","dispensableAsChange":true},
    {"denomination":"0.10","dispensableAsChange":true},
    {"denomination":"0.25","dispensableAsChange":true},
    {"denomination":"1.00","dispensableAsChange":false}],
  "exactChangeOnly":false}}
```

`exactChangeOnly` is the lamp on the front of the machine: the till holds nothing it is allowed to give back, so a client can warn before taking someone's money instead of discovering it in a refused sale.

`acceptedCoins` answers a different question from `changeReserve` — what the slot takes, not what the till holds, so a machine serviced down to nothing still accepts all four. It exists so a client does not carry the list itself, and `dispensableAsChange` for the same reason: that the 1.00 coin goes in and never comes back is an interpretation of the brief, and a rule reimplemented on the far side of a network is a rule two systems will eventually disagree about.
</details>

<details open>
<summary><b>POST /api/machine/purchases</b> — <code>{"selector":"WATER"}</code></summary>

```json
{"dispensed":{"selector":"WATER","name":"Water","price":"0.65",
  "change":{"coins":[{"denomination":"0.10","count":1},
                     {"denomination":"0.25","count":1}],"amount":"0.35"}},
 "machine":{ ... }}
```
</details>

<details>
<summary><b>POST /api/machine/coins/return</b> — no body</summary>

```json
{"returned":{"coins":[{"denomination":"0.25","count":1}],"amount":"0.25"},
 "machine":{ ... }}
```
</details>

<details>
<summary><b>PUT /api/machine/service</b></summary>

```bash
curl -X PUT localhost:8000/api/machine/service -H 'Content-Type: application/json' -d '{
  "products":[{"selector":"TEA","name":"Iced Tea","price":"0.80","count":4}],
  "changeReserve":[{"denomination":"0.25","count":10}]}'
```

SERVICE *sets*; it does not top up. Any money a customer had inserted is returned first — someone opening the machine does not get to keep it.
</details>

### When it says no

Errors are RFC 7807 `application/problem+json`, built from an explicit table rather than a `match` buried in a subscriber, so the whole error surface is readable in one screen: [`ErrorCatalog.php`](backend/src/VendingMachine/Delivery/Http/Error/ErrorCatalog.php).

One rule decides the status, keyed on **whose problem it is**:

| Status | Means |
|---|---|
| **422** | The value you sent is not valid input |
| **409** | It is valid, and conflicts with the state of the machine |
| **404** | You named something that does not exist here |
| **400** | We could not read the request at all |
| **503** | The machine is not ready — ours, not yours |
| **500** | Something we did not anticipate, with the detail suppressed |

```jsonc
// 409 — a purchase without enough money in
{"type":"/problems/insufficient-funds","title":"Insufficient funds","status":409,
 "detail":"Another 1.25 is needed before this product can be dispensed.",
 "code":"insufficient_funds","missingAmount":"1.25"}

// 422 — {"coin": 0.25} instead of {"coin": "0.25"}
{"type":"/problems/invalid-request-payload","title":"Invalid request payload","status":422,
 "detail":"The field \"coin\" must be a string.",
 "code":"invalid_request_payload","field":"coin"}

// 404 — a product this machine does not stock
{"type":"/problems/unknown-product","title":"Unknown product","status":404,
 "detail":"This machine does not stock any product under the selector \"BEER\".",
 "code":"unknown_product"}
```

Two tests keep that table honest. One walks every named failure in the domain and fails if it is missing from the table, so a new error can never quietly become a 500 ([ADR-0012](docs/adr/0012-rfc7807-errors-with-explicit-status-rule.md)). The other walks the table itself and fails if a failure is missing from the published contract — in either direction, because a document that promises an error the code can no longer produce rots just as quietly.

## Architecture

```
   Delivery/Http/  ─┐                        ┌─ Infrastructure/Doctrine
                    ├─→  Application  →  Domain  ─┤
   Delivery/Cli/   ─┘      (use cases)   (rules)  └─ Infrastructure/InMemory
   driving adapters                                  driven adapters
```

Two doors in, two ways out. The CLI and the HTTP controllers build the *same* commands and put them on the *same* bus; the domain declares the ports and the infrastructure implements them, pointing inwards.

- `Domain/` — pure PHP. `grep -r "Symfony\|Doctrine"` finds nothing, and finds nothing because **Deptrac fails the build** if it ever would.
- `Application/` — use cases that load, delegate, save and announce. They orchestrate; they do not decide.
- `Delivery/` — driving adapters: six invokable HTTP controllers and two console commands.
- `Infrastructure/` — driven adapters: the two repositories.

No attributes in the core either: routes live in YAML, handlers are registered by marker interface, the ORM mapping is XML. A `#[Route]` is an import, and an import is a dependency.

**[Full walkthrough →](docs/architecture.md)** — including a purchase traced end to end, and the same purchase when it is refused.

## Tests

```bash
make test           # 478 tests, 3 335 assertions
make test-unit      # 289 of them, no kernel and no database
make qa             # both halves; every CI gate that needs no network
make test-mutation  # Infection over Domain + Application — MSI 100%

make front-test     # the panel's own 115, in jsdom
make front-lint     # ESLint, accessibility rules included
make front-e2e      # five in a real browser, against a running `make up`
```

| Suite | Tests | Answers |
|---|---:|---|
| unit | 289 | Are the business rules correct? |
| application | 38 | Does the use case orchestrate correctly? |
| integration | 43 | Does the adapter honour the port? |
| acceptance | 108 | Does it work end to end, error contract included? |

The three examples of the brief exist as executable specification at four levels. The repository port has **one abstract contract test that both adapters must pass**, written with the first adapter long before the second existed — which is the answer to "how do you know your in-memory double is not lying?".

The domain is gated by mutation testing rather than line coverage, because coverage says which code *ran* and MSI says which behaviour your tests would *notice changing*. It caught a real bug at 100% coverage.

**[Full strategy →](docs/testing-strategy.md)**

## How to extend it

The two questions the brief asks about extensibility, answered by doing them.

### A new product: no code at all

Products are data. `ProductSelector` is a validated string and not an enum precisely for this — a machine sells whatever a technician loaded into it, and stocking something new must not need a deployment.

```bash
curl -X PUT localhost:8000/api/machine/service -H 'Content-Type: application/json' -d '{
  "products":[
    {"selector":"WATER","name":"Water","price":"0.65","count":10},
    {"selector":"SPARKLING_WATER","name":"Sparkling Water","price":"1.20","count":5}],
  "changeReserve":[{"denomination":"0.05","count":20},{"denomination":"0.10","count":20},{"denomination":"0.25","count":20}]}'
```

It is on sale immediately, and change works for it like any other:

```json
{"dispensed":{"selector":"SPARKLING_WATER","name":"Sparkling Water","price":"1.20",
  "change":{"coins":[{"denomination":"0.05","count":1}],"amount":"0.05"}}}
```

### A new coin: the tooling asks you the question

Coins are the opposite: a closed set, a physical property of the hardware, so `CoinDenomination` is an enum. Add `case TWENTY_CENTS = 20;`, run the checks, and the first thing they point at is a single line:

```
PHPStan  Match expression does not handle remaining value:
         CoinDenomination::TWENTY_CENTS          CoinDenomination.php:43
```

That is the design working. The `match` in `isDispensableAsChange()` is exhaustive on purpose, so a new denomination cannot inherit a silent default — **the analyser makes you answer whether the machine may give this coin back**, which is the one question a new coin actually raises. Leave it unanswered and there is no answer at runtime either: reading the machine reaches a `match` with no arm for the new coin, and the API answers 500.

PHPUnit points at the other place the coin set is declared. The accepted set is *published* in [`docs/openapi.yaml`](docs/openapi.yaml) and every acceptance response is validated against it, so the coin has to be declared there too — the contract enforcing itself rather than springing a surprise later. Answer the match, widen the contract, and **twelve** tests still fail: four unit, five application, three acceptance, each of them a test whose job is to write the current coin set down.

Nothing else breaks. The change algorithm is generic over denominations, and neither the aggregate nor any adapter mentions a specific coin.

(One consequence to honour: [`infection.json5`](backend/infection.json5) suppresses mutants that are unkillable *because* of the current coin set. Its comment says to delete those entries when a non-canonical denomination arrives, and it means it.)

## Assumptions and trade-offs

Everywhere the brief was silent, a decision was still required. [`docs/assumptions.md`](docs/assumptions.md) is the full list; the ones that matter most:

- **The machine never gives back a 1.00 coin.** The brief accepts four coins and lists three as valid responses; example 3 confirms it.
- **SERVICE sets absolute values** and returns any money in the escrow first.
- **Coins just inserted can pay their own change** — they are physically inside the machine.
- **A sale that cannot be given change is refused**, and the money stays in the escrow.

And what was deliberately *not* built, which is the more interesting half:

| Not built | Why, and what it would take |
|---|---|
| Authentication | The brief describes no actors, roles or credentials. `PUT /api/machine/service` is open, and that is written down rather than left to be noticed. A real deployment needs an authenticated technician identity; `MachineServiced` already records *what* was loaded, so the missing piece is *who*. |
| Idempotency | A retried purchase vends twice. The fix is an `Idempotency-Key` header with a store of processed keys — not a purchase record, which would put two aggregates in one transaction and break the rule that justifies having one. |
| Retries on conflict | Concurrency is *detected* (409), not resolved. Real contention on one machine is effectively zero, so the cheap detector is the right tool and the expensive serialiser is not. |
| A fleet | The route is `/api/machine`, not `/api/machines/{id}`. The aggregate already carries a `MachineId`; serving many is a routing change, not a redesign — but building it now would be speculative. |
| A products table | The aggregate is stored as one row, catalogue included. Nobody can ask SQL which machines are low on water. The day someone does, the answer is a read model fed by events, not a join. |
| TypeScript on the frontend | Deliberate: the panel is a thin client with no business logic, and the evaluation is of the backend. |

## Decision records

Seventeen ADRs, each written in the same commit as the decision it records, each with real alternatives and at least one honest downside.

**[Index →](docs/adr/)** · The four worth reading first: [one aggregate](docs/adr/0005-single-aggregate-root.md) · [refusing a sale without change](docs/adr/0007-reject-purchase-when-change-unavailable.md) · [the aggregate as one row](docs/adr/0008-doctrine-sqlite-xml-mapping.md) · [the error contract](docs/adr/0012-rfc7807-errors-with-explicit-status-rule.md)

## Repository layout

```
backend/
  src/
    Shared/            bus contracts (Command/Query/Event) and AggregateRoot
    VendingMachine/
      Domain/          the model: aggregate, value objects, ports, domain errors
      Application/     use cases — one folder per command or query
      Delivery/        driving adapters: Http/ and Cli/
      Infrastructure/  driven adapters: Doctrine and InMemory persistence
  config/
    doctrine/          the ORM mapping, in XML, outside the classes it maps
    routes/api.yaml    every URL in one file
    services.yaml      where the hexagon is actually wired
  tests/               unit · application · integration · acceptance · Support
docs/
  openapi.yaml         the API contract, validated against real responses by the suite
  architecture.md      the hexagon, and a purchase traced through it
  testing-strategy.md  what each of the four suites is for
  adr/                 seventeen decision records
frontend/
  src/                 the panel: pages · hooks · components · services
  docker/nginx.conf    serves the build, and forwards /api to the backend
.claude/               the tickets, skills and review agents used to build this
```

`.claude/` is committed on purpose. The brief welcomes AI assistance and says it wants to see *how* software is built; the tickets, the six review agents and their PASS/KO verdicts are part of that answer.
