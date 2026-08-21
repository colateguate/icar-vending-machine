# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository. It is also the **authoritative rubric** cited by the reviewer agents in `.claude/agents/` — they do not invent rules, they point here.

## What this repo is

A senior backend engineering challenge: model a vending machine. PHP backend (Symfony), minimal React frontend. Evaluated on architectural decisions, maintainability, extensibility, **testing strategy by level**, business-logic modeling and engineering principles — and defended in a technical interview. The git log itself is an evaluated deliverable: atomic Conventional Commits in English, one ticket = one commit.

**The original challenge statement lives in `CHALLENGE-DESCRIPTION.md`, which is gitignored on purpose** (it contains the company name, which must never appear in the repo or its history). Never commit it, never quote the company name in any file.

## Project layout

Polyglot monorepo. `composer.json` lives in `backend/`, `package.json` in `frontend/` — the root has neither.

- `backend/` — Symfony 7 API serving **JSON only** (no Twig, no HTML). Hexagonal + DDD tactical + CQRS.
- `frontend/` — React + **JavaScript** (deliberately no TypeScript) + Vite SPA. Thin client, zero business logic, talks to the API over HTTP.
- `docs/adr/` — Architecture Decision Records (MADR format, English). Every ADR needs a real "alternatives considered" and a negative consequence — an ADR with no downsides reads as marketing.
- `.claude/` — project skills, reviewer agents, and tickets (committed: the challenge welcomes AI-assisted workflows and evaluates *how* software is built).

## Backend architecture — the dependency rule

```
backend/src/
├── Shared/
│   ├── Domain/            # Bus contracts (Command/Query/Event + handler interfaces), AggregateRoot
│   └── Infrastructure/    # Messenger implementations of the bus ports
└── VendingMachine/        # single bounded context; layers nest INSIDE the context
    ├── Domain/            # pure PHP: aggregate, VOs, domain events, domain services, ports, exceptions
    ├── Application/       # use cases: Command/<UseCase>/{Command,Handler}, Query/<UseCase>/{Query,Handler,View}
    ├── Delivery/          # PRIMARY (driving) adapters: Http/ (invokable JSON controllers, request/response
    │                      #   DTOs, problem+json exception subscriber), Cli/ (app:machine:run — accepts the
    │                      #   challenge's literal syntax "1, 0.25, 0.25, GET-SODA")
    └── Infrastructure/    # SECONDARY (driven) adapters: Persistence/{InMemory,Doctrine}, DBAL types, logging
```

**Deptrac-enforced dependency rule** (a violation is a CI failure, not an opinion):

| Layer | May depend on |
|---|---|
| `Shared/Domain` | nothing |
| `Domain` | `Shared/Domain` |
| `Application` | `Domain`, `Shared/Domain` |
| `Delivery` | `Application`, `Domain`, `Shared/Domain`, `Symfony\*`, `Psr\*` |
| `Infrastructure` | everything above + `Doctrine\*` |
| `Shared/Infrastructure` | `Shared/Domain`, `Symfony\*`, `Doctrine\*`, `Psr\*` |

Layer vocabulary for the interview: `Delivery/` = primary/driving adapters (the outside world delivers requests to the core); `Infrastructure/` = secondary/driven adapters (the core delegates outward through ports). That pair **is** the hexagon.

### The four mechanisms that keep Symfony out of the domain

1. **Namespace containment** — `Symfony\*` / `Doctrine\*` are only reachable from `Kernel.php`, `Delivery/`, `Infrastructure/`, `Shared/Infrastructure/`. Enforced by Deptrac in CI. `grep -r "Symfony" backend/src/VendingMachine/Domain` must return zero hits.
2. **No attributes in Domain/Application** — no `#[Route]` (routes in `config/routes/api.yaml`), no `#[AsMessageHandler]` (handlers tagged via `_instanceof` on the marker interfaces in `config/services/application.yaml`), no ORM attributes (XML mapping in `config/doctrine/`).
3. **Container ignorance of the domain** — `services.yaml` excludes `Domain/` from autoregistration; the container knows only the explicitly wired domain services (e.g. the `ChangeStrategy` binding).
4. **Ports declared by the consumer** — `VendingMachineRepository` and `ChangeStrategy` interfaces live in `Domain/`; adapters live in `Infrastructure/` and are bound in `config/services/infrastructure.yaml`. Classic dependency inversion.

## Domain model (decided — do not re-litigate without an ADR)

- **One aggregate root: `VendingMachine`** (`Domain/Machine/VendingMachine.php` — the canonical pattern file). Inventory, products and coin collections are internal to it. Rationale: a purchase enforces one invariant spanning stock + coin reserve + escrow *atomically* ("a product leaves stock iff its price is covered and exact change is composable from coins physically inside the machine"); you cannot compensate a dispensed soda, so it is one transactional boundary. It scales by *instance* (one aggregate per physical machine), not by size.
- **`Money` is integer cents** (`final readonly`). Floats in money arithmetic are forbidden — IEEE-754 makes equality and accumulation unsound. JSON serializes money as decimal **strings** (`"0.65"`), never numbers.
- **`CoinDenomination` is a backed enum** (5, 10, 25, 100). The spec accepts four coins but only ever returns three: **the 1.00 coin is never dispensed as change** (`isDispensableAsChange()`). This is an interpreted requirement, documented in `docs/adr/`.
- **`ChangeStrategy` is a domain service port** with two implementations: `OptimalChangeStrategy` (bounded-coin DP, wired default) and `GreedyChangeStrategy` (kept + tested to prove where greedy fails: needing 0.30 from {0.25×1, 0.10×3} greedy refuses a sale the optimal serves). Passed to `purchase()` as a **method parameter** (double dispatch), never a constructor dependency of the aggregate.
- **`CannotDispenseChange` is a domain error**: the sale is rejected, coins stay in escrow, `RETURN-COIN` remains the single refund path. The aggregate uses compute-then-commit — no field is mutated before all checks pass. `requiresExactChange()` is exposed in the API as `exactChangeOnly`.
- Commands carry **primitives**, handlers translate to VOs; VO constructors ARE the validation. Command handlers return `void` unless the physical result cannot be recovered by a later query (`DispensedGoods`, `CoinCollection` — the can and the coins have physically left the machine).
- Concurrency: **optimistic locking** (Doctrine `<version/>` column) → HTTP 409. Retries/pessimistic locking deliberately out of scope.
- Errors over HTTP: RFC 7807 `application/problem+json` via an explicit `ErrorCatalog` map. Rule, keyed on whose problem it is: **422** = the value you sent is not valid input · **409** = the value is valid but conflicts with current machine state · **404** = you named something that does not exist (`UnknownProductSelector` — the caller asked for SODA and there is no SODA). A machine that was never provisioned is **503**, not 404: the caller named nothing (the route is the singleton `/api/machine`) and the fault is ours, so the honest answer is "not ready yet". Anything the domain does not anticipate is **500** with the detail suppressed.

## Test levels — which question each answers

| Suite | Dir | Boots kernel | Repository | Answers |
|---|---|---|---|---|
| Unit | `backend/tests/Unit/` | no | none (aggregate built directly) | Are the business rules correct? |
| Application | `backend/tests/Application/` | no | **InMemory** | Does the use case orchestrate correctly? |
| Integration | `backend/tests/Integration/` | yes | **Doctrine + real SQLite** | Does the adapter honor the port? |
| Acceptance | `backend/tests/Acceptance/` | yes | Doctrine + real SQLite | Does it work end-to-end through HTTP/CLI, error contract included? |

**A test's level is decided by the question it answers, not by the machinery it happens to need.** The "boots kernel" and "repository" columns describe what each level typically requires, not a requirement every test in that suite must meet: the in-memory repository test needs neither kernel nor database, yet "does this adapter honor the port?" is an integration question, so it belongs there.

Non-negotiables: the three challenge examples exist as executable specification (HTTP acceptance + CLI); the repository **contract test** is abstract (`tests/Support/Contract/`) and every adapter extends it — written as a contract from the *first* adapter, so it states what any implementation must guarantee rather than what one happens to do. Expectations that legitimately differ between adapters (Doctrine's identity map returns the same instance twice; the in-memory double copies on read) stay in the adapter's own test, never in the contract. Never mock value objects; test business rules at unit level, not through the kernel. Infection (mutation testing) gates `Domain/` + `Application/` only.

## Frontend architecture — the layer rule

The panel is a thin client over the API: it renders state and dispatches actions, and it decides nothing about vending.

```
frontend/src/
├── main.jsx           # bootstrap, nothing else
├── App.jsx            # layout and composition; owns no remote state
├── pages/             # one file per screen. There is one: MachinePage
├── hooks/             # useMachine — the only module that talks to services/
├── components/        # presentational: props in, callbacks out, no network
└── services/          # httpClient (the only fetch), machineApi, problemDetails
```

| Layer | May depend on |
|---|---|
| `components/` | React, other `components/` |
| `hooks/` | `services/`, React |
| `pages/` | `hooks/`, `components/`, React |
| `App.jsx` / `main.jsx` | `pages/`, React |
| `services/` | `fetch` — nothing of the UI |

There is **no Deptrac for the frontend**. The rule is upheld by review (`frontend-architecture-reviewer`) rather than by CI — a deliberate trade recorded in `docs/adr/0016`. That makes it easier to break here than in the backend, which is exactly why it is written down in one place and pointed at rather than assumed.

**Layer-based, not feature-sliced, on purpose.** Organising by feature earns its keep when features have to be removable independently. There is one screen. Feature-slicing a single feature is ceremony, and knowing where to stop is part of what the challenge evaluates.

**No data-fetching library.** Every writing endpoint returns the full machine state in its response, so there is no cache to invalidate and no refetch to orchestrate: the answer to a mutation *is* the new state. TanStack Query and SWR were considered and rejected on that ground. The cost is real and stated in `docs/adr/0016` — no in-flight deduplication, so controls are disabled while an action is pending.

**Money never becomes a number.** Amounts arrive as decimal strings (`"0.65"`) and stay strings all the way to the DOM. `Number()`, `parseFloat` or arithmetic on an amount is the same Critical here as in the backend: JavaScript offers only the float that ADR-0004 refuses.

**Errors are read by `code`, never by `detail`.** The `ErrorCatalog` codes (`insufficient_funds`, `exact_change_required`, …) are the stable interface; `detail` is English prose that may be reworded without warning.

**Frontend test levels**: component tests (Vitest + Testing Library, mocking the `services/` module) and module tests (`services/`, mocking `fetch`). The seam is the module, never `global.fetch` inside a component test. Queries go by role and accessible name — `data-testid` is a last resort the test has to justify, and accessible markup is testable markup. `frontend/e2e/` is reserved for Playwright and deliberately empty. No mutation gate and no coverage threshold here: the evaluated suite is the backend's.

## Commands

```bash
make test           # all four PHPUnit suites
make test-unit      # fast domain suite
make qa             # both halves; every CI gate that needs no network
make test-mutation  # Infection on Domain + Application
make up             # docker compose up (the panel joins the stack in ticket 13b)

make front-install  # npm ci
make front-dev      # the panel against the API on :8000
make front-test     # Vitest
make front-lint     # ESLint, accessibility rules included
make front-build    # production bundle
```

The whole repo is driven from `make`, both halves. The `front-*` targets warn
rather than stop when the running Node is below the floor `frontend/package.json`
declares: without Node nothing can run, but the suite does pass today on a
version jsdom says it does not support, and refusing to run a suite that works
would be a decision with its own ticket rather than a guard.

Direct equivalents from `backend/`: `vendor/bin/phpunit --testsuite unit|application|integration|acceptance`, `vendor/bin/phpstan analyse`, `vendor/bin/deptrac analyse` (config auto-detected from `deptrac.php`), `vendor/bin/php-cs-fixer fix`. Note: PHPUnit config is `phpunit.dist.xml` (PHPUnit 11 recipe convention).

Mutation testing **does run locally**: Xdebug is installed with `xdebug.mode=off`, which is why it costs nothing on every other command, and the `make test-mutation` target turns coverage on for its own run (`XDEBUG_MODE=coverage`). It takes ~4 minutes and it is the only gate not in `make qa` for that reason — run it whenever a change lands in `Domain/` or `Application/`, which is the scope Infection is configured for.

`make schema-check` (also part of `make qa`) migrates a throwaway SQLite file and runs `doctrine:schema:validate`, so the mapping and the migration cannot drift apart in silence.

## Branching model (git flow)

```
main                     production; only receives PRs from a release branch
 └── release/backend     deliverable branch for the backend sprint (tickets 01-14)
      ├── feat/<slug>    one branch per feature ticket
      ├── fix/<slug>     one branch per bug ticket
      └── chore/<slug>   tooling/process changes that are neither
```

Rules, in order:

1. **Never commit directly to `main` or to a release branch.** Every change starts as a branch off the current release branch.
2. Branch name comes from the ticket: `feat/` for new tickets, `fix/` for bugs, `chore/` for tooling. Kebab-case, short, no ticket number.
3. When the ticket is done: push the branch. **The human opens the PR** into `release/backend` — Claude does not open or merge PRs.
4. After the PR merges, `git checkout release/backend && git pull` before cutting the next branch.
5. Merges into a release branch use `--no-ff` so the branch topology survives in the log (this is part of the evaluated deliverable).
6. Frontend tickets (15-17) get their own `release/frontend` cut from `main`, after `release/backend` merges.
7. `release/backend` → `main` is the final PR, once tickets 01-14 are in.

## Workflow

- **Tickets** live in `.claude/tasks/<priority>-<slug>.md` (epic mode: `.claude/tasks/<epic>/NN-<priority>-<slug>.md`); completed tickets are **moved** to `.claude/completed_tasks/`. Created only via the `create-ticket` skill.
- **Features** → `implement-feature` skill (TDD, red first, active verification before claiming done). **Bugs/refactors/trivial** → `fix-bug` skill. **Before any push** → `review-before-push` skill (dispatches the 4 reviewer agents in parallel, aggregates a PASS/KO verdict).
- Reviewer agents: six, dispatched four at a time. Security and clean-code review both halves of the repo; the architecture and test lenses come in matched pairs, because one rubric cannot cover Deptrac and Testing Library at once. Backend diff → `security-reviewer`, `architecture-reviewer`, `clean-code-reviewer`, `test-quality-reviewer`. Frontend diff → `security-reviewer`, `frontend-architecture-reviewer`, `clean-code-reviewer`, `frontend-test-quality-reviewer`. A diff touching both gets the union. Each emits its own `### Veredicto: PASS/KO`. Use these before generic plugin reviewers (`agent-skills:code-reviewer`, etc.).
- **Skill precedence**: project skills > `superpowers:*` (harness mechanics) > `agent-skills:*` (opt-in domain craft). `agent-skills` plans go in `tasks/plan.md` by its convention — **ignore that**; tickets live in `.claude/tasks/`.
- Language: code, tests, commits, README, ADRs in **English**; skills, agents, tickets in **Spanish**.
- **Study docs**: `documentation/` (gitignored, Spanish) holds the user's personal study notes — `symfony-basico.md` and `glosario.md`. When a ticket introduces a new framework feature or architecture concept (Messenger, Doctrine mapping, Infection...), **update the relevant section there in the same session**, written for someone coming from Laravel. New acronym used anywhere → new glossary entry.
