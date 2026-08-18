# 0013 — Enforce architectural boundaries with Deptrac and PHPStan at max level

- **Status**: accepted
- **Date**: 2026-08-19

## Context and problem statement

ADR-0001 promises a framework-free core. A promise in a README decays the first time someone imports a convenient Symfony class into the domain under deadline pressure. The boundary needs to be mechanical — a build failure, not a code-review opinion.

## Decision drivers

- The dependency rule must survive contributors (human or AI) who have not read the docs.
- Quality gates must land before business code, so every commit in history was born green.
- No escape hatches that normalize decay.

## Considered options

1. Deptrac (layer rules as executable config) + PHPStan level max without a baseline, both in CI.
2. Convention + code review only.
3. PHPStan alone with a custom architecture ruleset.

## Decision outcome

**Chosen: Deptrac + PHPStan max, no baseline, wired into CI before any domain code exists.** Deptrac encodes the exact dependency table from CLAUDE.md (`deptrac.php`); a planted violation was verified to fail the build (`DependsOnDisallowedLayer`, exit 1). PHPStan max catches type-level defects the compiler can't. Review-only enforcement (option 2) is the mechanism that produced every layered-architecture-in-name-only codebase; PHPStan-only (option 3) reimplements poorly what Deptrac does natively.

### Consequences (positive)

- `Domain` importing Symfony is a CI failure with a file:line message, not a debate.
- CI additionally runs Deptrac with `--fail-on-uncovered`: every new dependency must be classified into a layer before it builds.

### Consequences (negative)

- Every new vendor namespace requires a layer entry in `deptrac.php` before CI passes — friction that is the point, but friction nonetheless.
- PHPStan max without baseline means third-party quirks must be solved, not suppressed, which occasionally costs real time.
