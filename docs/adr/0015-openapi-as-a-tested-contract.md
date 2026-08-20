# 0015 — Publish the API as a hand-written OpenAPI document, and test it against real responses

- **Status**: accepted
- **Date**: 2026-08-20

## Context and problem statement

Until now the only way to learn what this API returns was to read the acceptance tests or the `Delivery/Http/Response/` classes. That is fine for whoever wrote them and useless for everyone else: the person integrating the panel in ticket 16, the reviewer who wants to poke the running container, the evaluator who would rather import something into Postman than read PHP.

The obvious fix — write the shapes down somewhere — has an obvious failure mode. Every API document ever written was accurate on the day it was written. What decides whether it is worth having is not how well it is written but whether anything notices when it stops being true, and by default nothing does: the document lives in `docs/`, the code lives in `src/`, and no build step has an opinion about the distance between them.

So the question is not "which format" but **what keeps the document honest**.

## Decision drivers

- Money on the wire is a decimal string, never a JSON number (ADR-0004). That is the single most important thing a client must get right, and the one a hand-kept document is most likely to get subtly wrong once and then keep wrong.
- The error contract is eleven codes across five status codes, plus the 500 that is the absence of a contract rather than part of one (ADR-0012). A client branches on `code`; a document that omits one leaves the client meeting an error it was never told about.
- The frontend tickets consume this API. A contract they can read is worth more to them than prose.
- Whatever is published has to be importable by the tools people actually use — Postman, Insomnia, Bruno, Swagger UI — without a second artifact to maintain.
- The repository already prefers gates over agreements: Deptrac for layering, PHPStan for types, a contract test for the repository port. A document with no gate would be the one unchecked claim in the project.

## Considered options

1. A hand-written OpenAPI 3.1 document, validated against the responses the acceptance suite already produces.
2. An OpenAPI document generated from PHP attributes on the controllers (`nelmio/api-doc-bundle`, `zircote/swagger-php`).
3. A Postman collection committed to the repository.
4. API Blueprint / Apiary.
5. Prose in the README and nothing else.

## Decision outcome

**Chosen: option 1.** `docs/openapi.yaml` is written by hand, and `tests/Support/OpenApi/OpenApiContract.php` validates every response the acceptance suite produces against it. Both gates are described below. The examples in the document were captured from real runs of that suite rather than typed from memory.

### The two gates, and why one is not enough

**Every response is validated.** `ApiTestCase` sends its request, then checks the response against the document: the status must be declared for that operation, the content type must be one the operation offers, and the body must satisfy the schema. Forty-four test methods across eight classes get this for free, without a single new HTTP call — eighty-nine responses checked, and the acceptance suite's assertion count says so, because the check is an assertion rather than a bare `fail()`. A gate that registers nothing when it passes leaves no trace of having run, and "the suite is green" stops being evidence that anything was compared.

Every response schema sets `additionalProperties: false`, because a schema without it only notices fields that disappear — which is half of drift.

**Every catalogued failure must be documented.** The first gate can only check the failures the suite happens to provoke, and `concurrent_modification` is provoked by two connections racing — an integration-level setup that acceptance never reaches. Left to the first gate alone, that error could stay undocumented forever with the whole suite green. So `OpenApiErrorCoverageTest` walks the error catalog and fails if a `(status, code)` pair is missing from the document — and fails the other way too, if the document promises a failure the catalog can no longer produce.

### Why not generate it from the code

This is the serious alternative, and it wins on exactly one axis: a generated document **cannot** drift, whereas a written one can and needs a test to stop it. That is a real advantage and it is why the option was weighed rather than dismissed.

It loses on three.

A generated document describes what the code *does*. A written one describes what the API *promises*. When those differ, the first calls it documentation and the second calls it a bug — and a document that automatically agrees with a mistake cannot be the thing that catches it. The gate here turns the document into a test; generation turns it into a mirror.

The saving is smaller than it looks. These controllers return arrays, not typed response objects, so the response schemas would have to be written out inside attributes anyway. The choice is not "write schemas or don't", it is "write them in YAML that a reviewer can read in a pull request, or write them in PHP attributes that render into YAML nobody reviews".

And it would put a large, unrelated vocabulary back on the controllers. Keeping attributes out of the code is a running theme of this project (ADR-0001) — routes in YAML, handlers by `_instanceof`, ORM mapping in XML. `Delivery/` is allowed to import Symfony, so this would not be a layering violation, but it would still be the one place where a controller is half description.

### Why not a Postman collection, Apiary, or prose

A **Postman collection** is a derived artifact. OpenAPI imports into Postman, so publishing the spec produces the collection for free; publishing the collection produces nothing else and adds a `collection.json` that desynchronises in silence. Choosing the source over the derivative is the whole of it.

**API Blueprint** has been effectively frozen since Apiary was acquired, and its tooling has thinned to the point where the ecosystem argument that once favoured it now runs the other way.

**Prose** is what the README already has, and it is good prose; what it cannot be is imported, validated, or diffed against a response.

### Two deliberate omissions

**500 is not documented.** It is not a promise this API makes — it is the promise failing. Leaving it undeclared means an unexpected 500 in any acceptance test fails the contract loudly instead of passing it quietly, which is worth more than the completeness.

**404 for an unknown route and 405 for a wrong method are not documented.** OpenAPI keys everything by declared path, so "a path this API never declared" has nowhere to live in the document. Those two come from the router rather than the domain, and the two tests that probe them opt out through a method named `requestOutsideTheContract()` — a named opt-out rather than a silent "skip when the spec has no such path", because the silent version would stop validating a response the moment someone typo'd a URI and leave the suite green while checking nothing.

## Consequences (positive)

- The document cannot drift from the API without the build going red. Verified rather than asserted: returning `amount` as a JSON number instead of a string, adding a field, and removing a field each fail the acceptance suite.
- The money rule is now enforced at the boundary in both directions — the model keeps cents, and the contract refuses anything that is not `^\d+\.\d{2}$` on the wire.
- Importing `docs/openapi.yaml` into Postman, Insomnia, Bruno or Swagger UI gives a working collection with worked examples, and there is no second artifact to keep in step.
- Every example in the document was captured from a real run, so none of them is a plausible-looking response that the API has never actually produced.
- A new error is now a three-step change that the suite completes for you: add the exception, catalogue it, document it — and skipping the third step fails.

## Consequences (negative)

- **The shapes are declared twice.** They already live as PHPStan array shapes on the response DTOs, and now they live in YAML too. The gate is the only thing making those one truth rather than two, which is precisely the criticism a generated document would escape. It is the cost of the trade in the section above, and it should be stated rather than glossed.
- The gate is only as wide as the acceptance suite. A status documented but never provoked — `concurrent_modification` in every one of its four operations — is checked by the coverage test for existence, and by nothing at all for shape.

  **Narrowed on 2026-08-20**, in the ticket that produced this failure over HTTP. This entry and the sentence above calling the setup integration-level are both left standing rather than rewritten, because the assumption they share is the part worth reading: that provoking a concurrent modification needs two connections racing. It does not. Swapping the repository port for a double that loses every race provokes it in all four operations at acceptance level, which is the thing a port is for — the race does not have to be real for the edge to have to answer it.

  It also cost the positive consequence above one word of honesty. "Every example in the document was captured from a real run" was true of every example except this one, which could not have been captured because nothing produced it: the published title and detail were plausible and wrong, and both now say what the API answers. What the entry gets right stays true — the first gate is only as wide as the suite, and the next failure nobody thinks to provoke will sit exactly where this one sat.
- Three more dev dependencies, and the validator is a `0.x` package. It is the maintained League fork and it is the only PHP library that parses OpenAPI 3.1, but a `0.x` version pin is a `0.x` version pin. The third, `devizzent/cebe-php-openapi`, arrives transitively through the validator and is declared anyway, because the contract test imports from it by name: depending implicitly on something you import means a minor release of the middleman can break you without touching your `composer.json`.
- The suite got slower, though less than expected: parsing the document once and validating every response costs about 10% of the acceptance suite's wall time (0.83s → 0.91s over three runs each, measured when the suite produced eighty-three of them). Cheap, but it is a cost that grows with every acceptance test added, and the unit suite is the one that has to stay fast.
- Anyone who reads the document expecting completeness will notice 500 missing and read it as an oversight. The comment at the top of the file exists for that reader and will not always be read.
