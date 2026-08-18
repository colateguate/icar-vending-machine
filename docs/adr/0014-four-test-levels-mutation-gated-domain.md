# 0014 — Test at four levels and gate the domain with mutation testing

- **Status**: accepted
- **Date**: 2026-08-19

## Context and problem statement

The challenge explicitly evaluates "your understanding of what and how to test at different levels". A single flat test folder answers no question in particular; a coverage percentage measures execution, not detection.

## Decision drivers

- Each test level should answer exactly one question, at the cheapest possible cost.
- The metric that gates the build should measure whether tests would catch a regression, not whether lines ran.
- Business rules must be testable in milliseconds without booting a kernel.

## Considered options

1. Four suites (unit / application / integration / acceptance) + Infection mutation testing scoped to Domain+Application with `minMsi: 85`.
2. Two suites (unit + end-to-end) with a line-coverage gate (e.g. 90%).
3. Mutation testing over the whole codebase.

## Decision outcome

**Chosen: option 1.** Unit asks "are the business rules correct?" (no kernel, no I/O); application asks "does the use case orchestrate?" (InMemory adapter); integration asks "does the adapter honor the port?" (real SQLite); acceptance asks "does the whole thing work through HTTP/CLI?". Line coverage (option 2) is a weak proxy — a test with no assertions covers lines. Whole-codebase mutation (option 3) burns CI minutes mutating infrastructure glue whose failures the integration suite already catches.

### Consequences (positive)

- A failing test localizes the defect by construction: the suite name says which layer is broken.
- MSI ≥ 85 on the core means a behavior change that no test notices fails the build.

### Consequences (negative)

- Four suites cost boilerplate (directory conventions, per-suite Makefile targets) that a small project would not otherwise need.
- Mutation testing is slow and requires a coverage driver (pcov in CI); on the local Windows setup without pcov/xdebug it cannot run, so the mutation gate is CI-only.
- The same behavior is sometimes tested at two levels (the challenge examples exist as unit and acceptance tests) — deliberate, but it looks like duplication until explained.
