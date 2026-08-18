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
- Commands carry **primitives**, handlers translate to VOs; VO constructors ARE the validation. Command handlers return `void` unless the physical result cannot be recovered by a later query (`PurchaseResult`, `ReturnedCoinsResult` — the coins have physically left the machine).
- Concurrency: **optimistic locking** (Doctrine `<version/>` column) → HTTP 409. Retries/pessimistic locking deliberately out of scope.
- Errors over HTTP: RFC 7807 `application/problem+json` via an explicit `ErrorCatalog` map. Rule: **422** = invalid input value · **409** = valid value conflicting with machine state · **404** = named thing doesn't exist.

## Test levels — which question each answers

| Suite | Dir | Boots kernel | Repository | Answers |
|---|---|---|---|---|
| Unit | `backend/tests/Unit/` | no | none (aggregate built directly) | Are the business rules correct? |
| Application | `backend/tests/Application/` | no | **InMemory** | Does the use case orchestrate correctly? |
| Integration | `backend/tests/Integration/` | yes | **Doctrine + real SQLite** | Does the adapter honor the port? |
| Acceptance | `backend/tests/Acceptance/` | yes | Doctrine + real SQLite | Does it work end-to-end through HTTP/CLI, error contract included? |

Non-negotiables: the three challenge examples exist as executable specification (HTTP acceptance + CLI); the repository **contract test** is abstract and runs against *both* adapters; never mock value objects; test business rules at unit level, not through the kernel. Infection (mutation testing) gates `Domain/` + `Application/` only.

## Commands (once scaffolding lands — tickets 2–3)

```bash
make test           # all four PHPUnit suites
make test-unit      # fast domain suite
make qa             # PHPUnit + PHPStan (max) + Deptrac + php-cs-fixer
make test-mutation  # Infection on Domain + Application
make up             # docker compose up (backend + frontend)
```

Until the Makefile exists, run tools directly from `backend/` (`vendor/bin/phpunit --testsuite unit`, `vendor/bin/deptrac`, `vendor/bin/phpstan analyse`).

## Workflow

- **Tickets** live in `.claude/tasks/<priority>-<slug>.md` (epic mode: `.claude/tasks/<epic>/NN-<priority>-<slug>.md`); completed tickets are **moved** to `.claude/completed_tasks/`. Created only via the `create-ticket` skill.
- **Features** → `implement-feature` skill (TDD, red first, active verification before claiming done). **Bugs/refactors/trivial** → `fix-bug` skill. **Before any push** → `review-before-push` skill (dispatches the 4 reviewer agents in parallel, aggregates a PASS/KO verdict).
- Reviewer agents: `security-reviewer`, `architecture-reviewer`, `clean-code-reviewer`, `test-quality-reviewer`. Each emits its own `### Veredicto: PASS/KO`. Use these before generic plugin reviewers (`agent-skills:code-reviewer`, etc.).
- **Skill precedence**: project skills > `superpowers:*` (harness mechanics) > `agent-skills:*` (opt-in domain craft). `agent-skills` plans go in `tasks/plan.md` by its convention — **ignore that**; tickets live in `.claude/tasks/`.
- Language: code, tests, commits, README, ADRs in **English**; skills, agents, tickets in **Spanish**.
- **Study docs**: `documentation/` (gitignored, Spanish) holds the user's personal study notes — `symfony-basico.md` and `glosario.md`. When a ticket introduces a new framework feature or architecture concept (Messenger, Doctrine mapping, Infection...), **update the relevant section there in the same session**, written for someone coming from Laravel. New acronym used anywhere → new glossary entry.
