# Testing strategy

The brief asks for tests that "demonstrate your understanding of what and how to test at different levels". This is the written answer.

## Four suites, four questions

A test's level is decided by **the question it answers**, never by the machinery it happens to need.

| Suite | Tests | Boots kernel | Repository | Answers |
|---|---:|---|---|---|
| `unit` | 285 | no | none — the aggregate is built directly | Are the business rules correct? |
| `application` | 36 | no | in-memory | Does the use case orchestrate correctly? |
| `integration` | 43 | yes | Doctrine + real SQLite | Does the adapter honour the port? |
| `acceptance` | 80 | yes | Doctrine + real SQLite | Does it work end to end, error contract included? |
| **total** | **444** | | | 3 086 assertions |

Run one at a time — `make test-unit` is the fast loop, and it is fast because it touches nothing:

```bash
make test-unit         # 285 tests, no kernel, no database, no network
make test-application
make test-integration
make test-acceptance
make test              # all four
```

Two of the unit tests do touch the disk — one walks the source tree looking for exceptions nobody catalogued, one reads the published API contract — which is why the line above says "no database" rather than "no I/O". Reading a file is not what makes a test slow or fragile; a kernel, a connection and a socket are.

The "boots kernel" and "repository" columns describe what each level *typically* needs, not a rule every test must obey. The clearest case is the in-memory repository test: it needs neither a kernel nor a database, and it still belongs to `integration/`, because "does this adapter honour the port?" is an integration question whatever it costs to ask. The same logic puts the DBAL type tests and the parser rules of the CLI where they are.

## What each level is for, concretely

**Unit.** The rules, with nothing in the way. `VendingMachinePurchaseTest` builds a machine, sells something and asserts the stock, the change and the escrow. `OptimalChangeStrategyTest` proves the change policy never overdraws the reserve. Nothing here starts a framework, so a failure points at one class.

**Application.** The orchestration: does the handler load, delegate, save and announce — in that order, and nothing more? The in-memory repository keeps the question about the use case rather than about SQL, and `SpyEventBus` records what was published so the test can assert the announcement instead of trusting it.

**Integration.** The adapters. The Doctrine repository is exercised against real SQLite built from the real XML mapping; the custom DBAL types are round-tripped; two EntityManagers over one file prove that a stale write is refused rather than silently winning.

**Acceptance.** The promise, through the door a client actually uses. Every endpoint, the problem+json envelope, and the three worked examples of the brief — once over HTTP and once over the CLI, asserting the literal output the statement prints. Every response it produces is also checked against the published contract, which is the next section.

## The contract test

`tests/Support/Contract/VendingMachineRepositoryContract.php` is the single most useful test in this repository, and the reason is *when* it was written: with the **first** adapter, months of commits before Doctrine existed.

That forces the question "what must **any** implementation guarantee?" instead of "what does this one happen to do?" — and the proof that it worked is that the Doctrine adapter arrived later and passed it without a single edit.

Both adapters extend it. Its assertions are `final`, so neither can quietly relax a guarantee by overriding one.

What it deliberately leaves out is as interesting as what it contains: anything about object identity. Doctrine keeps an identity map, so two reads inside one unit of work return the very same object; the in-memory double copies on read, so they are independent. Both are correct — **the port promises state, not instances** — so each expectation lives in the adapter test that can honestly keep it. A contract demanding copy-on-read would have forced the real adapter to clone on every read to satisfy a test nobody needed.

This is the answer to "how do you know your in-memory double isn't lying?".

## The published contract, used as an assertion

Two gates keep `docs/openapi.yaml` from drifting away from the API. `ApiTestCase` checks every response it produces against the document — status declared, content type offered, body satisfying the schema — which is eighty-three responses and no new HTTP calls. `OpenApiErrorCoverageTest` then walks the error catalog against the document in both directions, because the first gate can only check the failures the suite happens to provoke, and `concurrent_modification` needs two connections racing.

Why a written document with a test rather than one generated from the code — and why not a Postman collection — is [ADR-0015](adr/0015-openapi-as-a-tested-contract.md). What belongs here is whether the gate works.

**Verified rather than assumed.** Three mutations of one response class, each run against the suite:

| Mutation | Result |
|---|---|
| `amount` returned as a JSON number instead of a decimal string | 26 responses refused — the exact mistake [ADR-0004](adr/0004-money-as-integer-cents.md) exists to prevent |
| an extra field added to the coin bag | 26 responses refused |
| the `amount` field removed | 26 responses refused |

The check is an assertion rather than a bare `fail()`, which is why the acceptance assertion count moved from 699 to 782: a gate that registers nothing when it passes leaves no trace of having run.

Two requests sit outside the contract, through a method named `requestOutsideTheContract()` rather than a silent "skip when the spec has no such path". The silent version is the trap: it would stop validating a response the moment someone typo'd a URI, and leave the suite green while checking nothing. The same reasoning is why the walk that finds catalogued failures anchors on a *named* directory instead of counting levels up from a file — a gate that keeps passing while it stops guarding is worse than one that fails.

## Mutation testing, and why not line coverage

```bash
make test-mutation     # ~4 minutes
```

Line coverage tells you which code **ran**. It cannot tell you whether anything would have noticed if that code changed. Infection answers the second question: it edits the code in small, plausible ways and checks whether some test fails.

Current state over `Domain/` + `Application/`:

```
309 mutants generated · 0 escaped · 0 uncovered
302 killed · 1 fatal error · 6 timed out
MSI 100% · Covered Code MSI 100%
```

A mutant that crashes or runs out of time counts as detected, and rightly: in both cases changing the code made something break, which is what the suite was being asked to do. Only *escaped* and *uncovered* move the score.

The gate is `minMsi: 85`. It is scoped to the business core on purpose: mutating infrastructure glue produces noise, and the number that means something is "would these tests catch a regression in the rules?".

**It found a real bug at 100% line coverage.** In `CoinCollection::add()`, Infection changed `?? 0` to `?? -1` and every test still passed — because the tests only ever added a coin to a collection that already had that denomination, so the null branch never ran. With the mutation, adding the *first* coin of a denomination produced a count of zero, which the canonical form drops, which empties the collection. A real defect, invisible to coverage.

**And it forced a rule about silence.** The change algorithm left fourteen mutants that no test could kill, because with the coin set `{5, 10, 25}` the number of coins is monotone and any rule that lets a later candidate win produces an identical selection. That was not assumed — each of the fourteen was implemented in isolation and run against an exhaustive oracle over ~2 300 payable reserves, with zero discrepancies. They are suppressed in `infection.json5`, narrowly, with the evidence beside them and an expiry condition: *add a non-canonical denomination and delete these entries, because then they become killable*.

The rule that came out of it:

| | Suppress | Do not suppress |
|---|---|---|
| Could a test ever kill it? | No, by construction | Yes, tests are just missing |
| Scope of the suppression | Named mutators, named methods | A file or a folder |
| Is the evidence written beside it? | Yes, with data | No, just intuition |
| Does it expire? | Yes | No |

Suppressing without justification is cheating. Leaving a warning nobody will act on is worse than having none, because it trains people to stop reading warnings. Suppressing with the proof and the expiry written next to it is triage.

## What the pipeline checks

Six jobs, all blocking:

| Job | Question |
|---|---|
| Code style | Does it follow the agreed style? |
| Static analysis | PHPStan at `max`, no baseline |
| Architecture | Deptrac: does the dependency rule still hold? |
| Schema drift | Do the migration and the XML mapping describe the same table? |
| Tests | All four suites, with warnings and deprecations failing the build |
| Mutation | Would the tests catch a regression? |

`make qa` runs everything except mutation, which is a separate target because it takes four minutes.

**Schema drift** is worth a sentence, because it guards a hole nothing else sees: the suites build their schema from the mapping, so a migration that drifted would leave them green and break the first real deployment. That job migrates a throwaway database and validates it against the mapping.

## Things this suite deliberately does not do

- **No mocked value objects.** `Money`, `CoinCollection` and their kin are built for real everywhere. They are cheap, and their behaviour is part of what is under test; a mocked `Money` tests the mock.
- **No line-coverage threshold.** The gate is MSI, and adding a coverage number next to it would invite optimising the one that is easier to game.
- **No business rule tested through HTTP.** The three examples of the brief exist at four levels on purpose — same behaviour, a different question each time — but a rule whose *only* test boots a kernel is a rule tested at the wrong level.
