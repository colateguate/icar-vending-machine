# Vending Machine

A vending machine modeled as a production-grade backend service, built for a senior backend engineering challenge.

- **Backend** — PHP 8.2+ / Symfony 7. Hexagonal architecture (ports & adapters), tactical DDD, CQRS via command/query buses. Serves JSON only.
- **Frontend** — React (JavaScript) + Vite. A thin machine panel with zero business logic.
- **Persistence** — one repository port, two adapters: in-memory (tests) and Doctrine ORM over SQLite (runtime). No database service required.

> **Status: work in progress.** The project is built ticket by ticket — the backlog lives in [`.claude/tasks/vending-machine-challenge/`](.claude/tasks/vending-machine-challenge/) and every architectural decision gets an ADR in [`docs/adr/`](docs/adr/) as it is made. This README grows as tickets land; placeholders below name the ticket that fills them.

## Machine behavior

- Accepts coins: **0.05, 0.10, 0.25, 1.00**
- Items: **Water (0.65), Juice (1.00), Soda (1.50)** — each with a selector, a price and a stock count
- **RETURN-COIN** refunds all inserted coins; overpaying returns the item plus change
- **SERVICE** lets a technician set the available change and item counts
- The machine tracks available items, available change and currently inserted money

## Requirements

**Docker (recommended):**
- Docker + Docker Compose v2

**Manual setup:**
- PHP >= 8.2 with Composer 2
- Node.js >= 20
- GNU Make (optional — every `make` target is a thin wrapper; on Windows without make, run the underlying commands shown in the [`Makefile`](Makefile) directly)

## Quick start

```bash
make up      # full stack via docker compose  (arrives with ticket 13)
```

Manual backend setup (arrives with ticket 02):

```bash
cd backend
composer install
php -S localhost:8000 -t public
curl localhost:8000/api/health
```

## Architecture

The domain and application layers are pure PHP — no framework imports, enforced by Deptrac in CI. Symfony lives only at the edges: `Delivery/` (driving adapters: HTTP controllers and a CLI runner) and `Infrastructure/` (driven adapters: persistence). See [`CLAUDE.md`](CLAUDE.md) for the layer rules and [`docs/adr/`](docs/adr/) for the decision records. A full walkthrough lands in `docs/architecture.md` (ticket 14).

## Testing

Four PHPUnit suites, each answering a different question — unit (business rules), application (use-case orchestration), integration (adapters against real SQLite), acceptance (HTTP/CLI end to end, including the three worked examples from the challenge as executable specification). The domain is additionally gated by mutation testing (Infection).

```bash
make test           # all suites          (arrives with ticket 03)
make qa             # tests + PHPStan max + Deptrac + code style
make test-mutation  # Infection over Domain + Application
```

The full testing strategy is documented in `docs/testing-strategy.md` (ticket 14).
