# 0002 — Single bounded context with layers nested inside it

- **Status**: accepted
- **Date**: 2026-08-19

## Context and problem statement

The source tree needs a top-level organizing principle: by layer (`src/Domain`, `src/Application`, ...) or by bounded context (`src/VendingMachine/{Domain,...}`). The choice determines what it costs to add a second context (payments, telemetry, fleet management) later — the kind of evolution the challenge asks us to design for.

## Decision drivers

- The unit of modularity in DDD is the bounded context, not the layer.
- Adding a future context should cost zero refactoring of existing code.
- Deptrac should be able to forbid cross-context calls as well as cross-layer ones.

## Considered options

1. Context-first: `src/VendingMachine/{Domain,Application,Delivery,Infrastructure}` plus a minimal `src/Shared/`.
2. Layer-first: `src/{Domain,Application,Delivery,Infrastructure}` with context subfolders inside each layer.

## Decision outcome

**Chosen: context-first, one context (`VendingMachine`) today.** The folder that gets duplicated when the system grows is the context folder; layers nest inside it. Layer-first implicitly claims layers are the dominant axis of change — the modular-monolith anti-pattern — and turns the arrival of a second context into a repo-wide move commit. `Shared/` is kept deliberately starved (bus contracts and `AggregateRoot` only): the shared kernel is the most expensive thing to change, so nothing enters it speculatively.

### Consequences (positive)

- A second bounded context is a sibling directory plus one Deptrac layer block; nothing existing moves.
- The context boundary is visible in every import statement.

### Consequences (negative)

- With exactly one context, the extra directory level is pure ceremony today — paths are longer and the structure looks heavier than the current feature set warrants.
- `Money` and `CoinCollection` stay inside `VendingMachine/Domain` even though a future context might want them; promoting them to `Shared/` later is a (small, explicit) refactor we accept in exchange for not guessing now.
