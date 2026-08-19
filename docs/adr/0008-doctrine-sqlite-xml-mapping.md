# 0008 — Persist with Doctrine ORM on SQLite, mapped in XML, storing the aggregate as one row

- **Status**: accepted
- **Date**: 2026-08-19

## Context and problem statement

The domain has been written for eight tickets without a database in sight, behind a port with two methods: `find(MachineId)` and `save(VendingMachine)`. Something now has to implement that port for real, and the way it is implemented decides whether the previous eight tickets were an architecture or a costume.

Two questions have to be answered together. What maps the aggregate — and where does the mapping live? Get the second one wrong and the domain ends up decorated with the persistence library it was designed not to know about.

## Decision drivers

- `grep -r "Doctrine" backend/src/VendingMachine/Domain` must return nothing, and Deptrac fails the build if it ever does. This is the project's load-bearing constraint, not a preference.
- The domain model is already written and is not up for renegotiation to suit a database.
- The evaluator has to be able to run this with no infrastructure: no server to install, no credentials, no container required.
- Money must survive the round trip exactly. A price that comes back as a float is a defect, not a rounding detail.
- The port only ever asks for a whole machine. There is no query in this bounded context that wants a product on its own.

## Considered options

**Storage engine:** SQLite in a file · PostgreSQL or MySQL in a container · a JSON file written by hand.

**Where the mapping lives:** XML under `config/doctrine/` · ORM attributes on the aggregate · a parallel set of persistence entities mapped with attributes, translated to and from the domain by hand.

**What shape the catalogue takes:** a JSON column inside the machine's row · a `products` table associated to the machine.

## Decision outcome

**Chosen: SQLite, XML mapping, and the aggregate stored as a single row.**

### SQLite

`git clone && composer install && make test` has to work, and every alternative adds a service to start before anything can be run. The brief asks for a machine that can be evaluated, not for a deployment. SQLite is a real SQL engine with real transactions and real constraints, and the DBAL means the DSN is the only thing that changes if a real server is ever wanted.

### XML, not attributes

Attributes are more ergonomic and everyone reaches for them first. They also mean `#[ORM\Entity]` sitting on top of `VendingMachine`, and at that moment the class that models the business rules imports a persistence library. That single import is the difference between "the framework is at the edge" being a claim and being a fact, and it is checked mechanically rather than by review.

The cost is real and worth stating: the mapping is now in a file the class does not mention, a rename in PHP does not follow into the XML, and the loop between changing a field and finding out you broke the mapping is longer. `doctrine:schema:validate` runs in `make qa` for exactly that reason.

The third option — a separate set of persistence entities — buys the same purity and costs a second copy of the aggregate plus a translator between the two, with all the drift that invites. It would be the right answer for a model rich enough that the storage shape genuinely differs from the domain shape. This one is four fields.

### The catalogue is a column, not a table

This is the decision most worth defending, because it looks like the wrong one at first glance.

A `products` table is the reflex. Doctrine can only express "many products" as a `Doctrine\Common\Collections\Collection` on the owning entity — that is what a to-many association requires — so the aggregate would have to hold one. The domain is not allowed to name that class. The way out is the parallel persistence model rejected above, and it means writing and maintaining a second aggregate for the sake of a join nothing performs.

Because that is the other half: the port asks for a whole machine or writes a whole machine, and nothing else. A products table would make every read a join and every write a cascade, in exchange for queries this bounded context never issues. "Which machines are out of water?" is a fleet question, and ADR-0005 already sends fleet questions to a different context that consumes the events this one records.

So the row holds everything the aggregate owns: the catalogue and both bags of coins as JSON, through custom DBAL types that hand the model value objects rather than strings. The aggregate is the consistency boundary; storing it as one document is the shape that matches.

### Consequences (positive)

- The domain is unchanged by this ticket in every respect that matters: not one import, not one attribute, not one method added for Doctrine's benefit.
- The whole test suite runs against a real database engine with no service to start, and each test builds and discards its own.
- Swapping the adapter is one line in `services.yaml`, and the in-memory one still passes the same contract test (ADR-0009) to prove it.
- Money never becomes a float: prices are stored as the integer cents the model counts in, checked by a round-trip test per type.
- One row per machine means a purchase touches one row, which is what makes the optimistic lock of ADR-0011 a single version column rather than a coordination problem.

### Consequences (negative)

- **The catalogue is not queryable in SQL.** Nobody can answer "which machines are low on water" with a `WHERE`. Today nothing asks; the day something does, this decision is what has to be revisited, and the answer would be a read model fed by events rather than a join.
- **The mapping can drift from the class silently.** Rename a private field in PHP and nothing in the IDE follows it into the XML. `doctrine:schema:validate` in `make qa` catches the shape, and the contract test catches the behaviour, but the first failure will be a test rather than a red squiggle.
- **A JSON column has no schema.** A hand-edited row, or a row written by an older version of the type, can hold something the model would refuse. The types therefore check every field before building a value object, and fail loudly — but that is a check we now own instead of one the database enforces.
- **Two concessions were made in the domain**, both documented where they live: an integer `version` (ADR-0011) and `__toString()` on `MachineId`, which Doctrine needs to key its identity map. Neither imports anything, and both are the kind of thing an aggregate in the literature carries anyway — but they are there because of persistence, and pretending otherwise would be dishonest.
- SQLite locks the whole database on write. Irrelevant for one machine and one process, and a real constraint of the choice: it is named in ADR-0011 rather than left to be discovered.
