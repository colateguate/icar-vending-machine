# 0012 — Answer every failure with RFC 7807, from a catalog, under one status rule

- **Status**: accepted
- **Date**: 2026-08-19

## Context and problem statement

The domain names the failures it anticipates: an unsupported coin, a product this machine does not stock, an empty slot, change that cannot be composed, a machine that was never provisioned. The HTTP edge has to turn each of those into a status code, and every such translation is a claim about *whose fault it is*. Get it wrong and the API lies: a 500 tells the client the server broke when in fact they sent a price of "free", and a 400 tells them they wrote a bad request when in fact the machine is sold out.

Two questions have to be answered together, because either one alone leaves a hole. Which status does each failure get, and **who decides that a request was invalid in the first place** — the edge, or the domain that eventually chokes on it?

## Decision drivers

- A status code is read by machines. It has to mean the same thing every time, derivable from a rule rather than from whoever wrote that controller.
- Nothing the caller can send may produce a 500. A 500 is an admission of our own bug, and spending it on client mistakes makes it useless as a signal.
- An error response must not describe our internals — no class names, no file paths, no SQL.
- The commands carry primitives and declare their shape in PHPDoc. The analyser checks that on our side of the wire and cannot check it on the client's.
- The frontend (tickets 15–17) needs to tell "insert more money" apart from "pick something else" without parsing English.

## Considered options

**For the error contract:**

1. RFC 7807 `application/problem+json`, built from an explicit FQCN → status table.
2. RFC 7807 built from a `match` inside the exception subscriber.
3. An ad-hoc error envelope of our own (`{"error": "...", "message": "..."}`).

**For who decides a request is invalid:**

1. Request DTOs at the edge that reject the wrong shape before a command exists.
2. Symfony Validator with constraint attributes on the DTOs.
3. Nothing at the edge: let the value objects refuse, and map whatever they throw.

## Decision outcome

**Chosen: option 1 in both cases.** `ErrorCatalog` is a constant map from exception class to `{status, code, title}`; `ProblemDetailsFactory` renders it; `DomainExceptionSubscriber` is the single place where a throwable becomes a response. Each controller maps its request body to a typed DTO first, so a malformed payload is a 422 before a command object exists.

### The rule, keyed on whose problem it is

| Status | Says |
|---|---|
| **422** | The value you sent is not valid input — an unsupported coin, a price that is not an amount, a payload of the wrong shape |
| **409** | The value is valid and conflicts with the state of the machine — sold out, not enough money, change that cannot be composed |
| **404** | You named something that does not exist here — the caller asked for SODA and there is no SODA |
| **400** | We could not read the request at all — the body is not JSON |
| **503** | The machine is not ready. Nobody named anything wrong; the fault is ours |
| **500** | Something we did not anticipate, with the detail suppressed |

Two rows are worth defending out loud. A machine that was never provisioned is **not** a 404: the route is the singleton `/api/machine`, so the caller named nothing that could be missing, and answering 404 would tell them to fix a URL that is correct. And the split between 422 and 409 is the whole rule in miniature — `0.02` is refused because two cents is not a coin *anywhere*, while `SODA` is refused because *this machine, right now* has none, and a client can retry the second after inserting a coin but never the first.

### Why a table rather than a match

They compile to the same behaviour; they do not read the same. The table answers "what can this API return, and for what?" by being read, which is the question an integrator asks and the question a `match` buried in a subscriber makes you trace control flow to answer. It also makes a completeness test possible: `ErrorCatalogTest` walks every class in `Domain/` implementing `VendingMachineError` and fails if one is missing. Without it, adding a domain error and forgetting the table degrades silently to a 500 — the exact failure this ADR exists to prevent, arriving quietly six months later.

### Why our own request DTOs and not Symfony Validator

The Validator is the obvious answer and it was rejected on scope rather than on quality. What is being checked here is *shape* — is `products` a list, is `count` an integer, is `price` a string — which is the same information the command's PHPDoc already states and PHPStan already enforces internally. Expressing it a second time as constraint attributes would mean two declarations of one contract that can disagree, plus mapping `ConstraintViolationList` into problem+json, plus a dependency. `JsonBody` is sixty lines with no configuration: every accessor returns the type it promises or throws naming the field, including inside a nested list, so a request DTO reads as a list of what the payload must contain and has nowhere to put an unchecked cast. If this API ever grows cross-field rules ("this price must be below that one"), that is when the Validator earns its place.

Two of the edge's checks are not about shape at all: a repeated selector and a repeated denomination. Inside the model those can only be a broken invariant — a 500 — because an inventory cannot hold one selector twice. On the wire they are a technician typing the same row twice, so they are refused at the edge and named.

**What the edge deliberately does not check** is whether the values are *valid*: that `"0.65"` is an amount, that `WATER` is a well-formed selector, that `0.25` is a coin this machine takes. Those questions already have an owner — the value object whose constructor refuses to hold them — and asking them twice is how two answers start to differ. Both paths land on 422, from different layers, on purpose.

### Consequences (positive)

- One rule produces every status, so a new failure has an obvious home and reviewers argue about the rule instead of about the case.
- The full error surface is one screen of data, which is most of the API documentation ticket 14 has to write.
- A domain error that is added and not catalogued fails the build rather than reaching a client as a 500.
- Nothing the client can send produces a 500 — asserted, not assumed: a data-provider drives fourteen malformed service payloads through HTTP and every one comes back 422.
- Controllers catch nothing. A refusal travels as an exception, which is why the same use case can say "sold out" over HTTP, over the CLI (ticket 12) and in a test, and mean the same thing.
- Failures the framework raises before a controller — unknown route, method not allowed — leave in the same envelope, so the API has one contract rather than two.

### Consequences (negative)

- The catalog is keyed on the exact class, so a subclass of a catalogued error would fall through to 500. Deliberate — every domain error here is `final` — but it is a sharp edge for whoever first writes a hierarchy.
- 422 now covers two genuinely different mistakes: a body of the wrong shape and a well-shaped body carrying a value the domain refuses. The `code` tells them apart, the status does not.
- `JsonBody` is a small piece of infrastructure we own and must maintain, and it will look like reinvention to anyone who reaches for the Validator by reflex. The line it must not cross is business rules; the moment a check needs to know what the machine holds, it belongs in the domain, not here.
- Suppressing the detail of an unanticipated failure makes production debugging depend entirely on the logs. That is the right trade for an error the caller cannot act on, and it is a real cost the first time someone reads a 500 with nothing in it.
