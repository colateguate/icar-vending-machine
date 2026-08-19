# 0009 — Keep two repository adapters and hold both to one abstract contract test

- **Status**: accepted
- **Date**: 2026-08-19

## Context and problem statement

The application suite runs against an in-memory repository: it is fast, it needs no database, and it lets a use-case test say what it means without setup noise. That convenience is also a risk, and it is the oldest one in testing — a double that is more forgiving than the real thing makes the suite pass on code that breaks in production.

So: is the double allowed to exist at all, and if it is, what stops it from quietly lying?

## Decision drivers

- The application suite must stay fast enough to run on every save; that is what makes it useful.
- A green suite has to mean something. A double that behaves differently from the database it stands in for turns a green suite into a false report.
- The port is the thing the domain depends on. What it promises has to be written down somewhere other than in the head of whoever wrote the first adapter.
- The brief weighs testing strategy as heavily as architecture.

## Considered options

1. Two adapters, and one abstract test class both must pass.
2. One adapter only — the real one — with the application tests running against SQLite.
3. Two adapters, each with its own tests.

## Decision outcome

**Chosen: option 1.** `tests/Support/Contract/VendingMachineRepositoryContract.php` holds the expectations; each adapter's test extends it and supplies an instance. `InMemoryVendingMachineRepositoryTest` and `DoctrineVendingMachineRepositoryTest` together are three lines of their own plus whatever is genuinely specific to them.

Option 3 is what usually happens and is the one this exists to avoid: two sets of tests written by two people at two times, describing the same port differently, with the differences invisible until something breaks. Option 2 is defensible and costs the thing that makes the application suite worth having — the application tests are about orchestration, and making each one boot a database to answer "does this use case call the right things" trades signal for ceremony.

The detail that makes this work is *when* the contract was written. It was written with the **first** adapter, in ticket 06, months of commits before Doctrine existed. That forces the question "what must any implementation guarantee?" instead of "what does this one happen to do?", which is the question you end up answering if you retrofit a contract onto two implementations that already exist. The proof is that this ticket added the second adapter without editing the contract at all.

### What is deliberately not in the contract

Anything about object identity. Doctrine keeps an identity map, so two reads inside one unit of work hand back the very same object; the in-memory double copies on read, so they are independent. Both are correct — the port promises **state**, not instances — so both expectations live in the adapter test that can actually keep them.

That line is the useful part of this decision. A contract that included copy-on-read would have forced the Doctrine adapter to clone on every read to go green, making the real adapter worse to satisfy a test. A contract must state what the consumer may rely on, and no consumer relies on getting a fresh instance.

### What the in-memory adapter is for after this

It is not dead code kept for sentiment. It runs the application suite, and it is the demonstration that the port is a real seam: the whole application swaps to it in one line of `services.yaml`, and the same contract test proves the swap is safe. That is the extensibility claim of a hexagonal architecture, made checkable instead of asserted.

### Consequences (positive)

- Adding a third adapter cannot quietly promise less: extending the contract is how it gets tested at all.
- The application suite stays fast, and its speed no longer costs confidence.
- The contract file reads as documentation of the port — what a repository must do, in four tests, in one screen.
- The two adapter-specific tests are now the only place with adapter-specific expectations, and each says why.

### Consequences (negative)

- Two implementations of the same port must be maintained. Small today (one is thirty lines) and a real cost if the port ever grows a query-shaped method.
- The contract is only as good as its authors' imagination. It cannot catch a guarantee nobody thought to write down — a transactional subtlety, say — and the in-memory double will keep agreeing with the contract while diverging on whatever is missing from it.
- Extending an abstract test class is inheritance in tests, which reads worse than composition and makes the actual assertions one file away from the class under test. A trait would read the same way with the same drawback; the win is that `extends VendingMachineRepositoryContract` is a visible statement that this adapter signs the contract.
