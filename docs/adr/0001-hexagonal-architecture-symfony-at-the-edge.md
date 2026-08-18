# 0001 — Hexagonal architecture with Symfony confined to the edge

- **Status**: accepted
- **Date**: 2026-08-19

## Context and problem statement

The challenge asks for a maintainable, extensible system that will evolve over time, and explicitly warns against a single-file script. We need an application structure where business rules can be understood, tested and changed without touching — or even knowing about — the delivery mechanism and the framework.

## Decision drivers

- Business logic must be testable in milliseconds, without booting a kernel or a database.
- The design must accommodate new products, actions and business rules (explicit evaluation criterion).
- The framework choice must remain replaceable in principle; framework upgrades must not ripple into business code.

## Considered options

1. Hexagonal architecture (ports & adapters): pure-PHP core, Symfony only in driving/driven adapters.
2. Idiomatic Symfony application: controllers + entities with ORM attributes + services, framework used everywhere.
3. Framework-less core with a micro-framework (Slim) for HTTP only.

## Decision outcome

**Chosen: hexagonal with Symfony at the edge.** `Domain` and `Application` contain zero framework imports — enforced mechanically by Deptrac in CI, not by convention. Symfony appears only in `Delivery/` (HTTP controllers, CLI) and `Infrastructure/` (persistence adapters, bus implementations). Option 2 couples business rules to the framework release cycle and makes unit tests boot infrastructure. Option 3 maximizes purity but discards mature DI, console and messaging components we would then hand-write.

### Consequences (positive)

- Business rules are plain PHP objects: fast unit tests, no mocking of framework classes.
- Adapters are swappable per port (proven by the in-memory + Doctrine repository pair, ADR-0009).
- The dependency rule is CI-verified: `grep -r "Symfony" backend/src/VendingMachine/Domain` returns zero hits and Deptrac fails the build otherwise.

### Consequences (negative)

- More indirection than an idiomatic Symfony app: a request crosses controller → bus → handler → aggregate instead of controller → service.
- We give up framework ergonomics inside the core (attributes, autoconfiguration), paying for it with explicit YAML/XML wiring that must be maintained by hand.
