# 0016 — Layer the panel by role, and ship it without a data-fetching library

- **Status**: accepted
- **Date**: 2026-08-20

## Context and problem statement

The backend is delivered and the panel is what is left. It is deliberately thin: React and JavaScript, no TypeScript, one screen, no business logic — every calculation the machine makes happens in the API.

The temptation with a thin client is to decide nothing. Put `fetch` where it is needed, keep state wherever it first lands, and let "it is only the frontend" excuse the rest. That produces the one part of the deliverable that argues against the other part: a reviewer who reads hexagonal discipline in PHP and then finds a three-hundred-line `App.jsx` with HTTP calls inside it does not conclude that the frontend was out of scope. They conclude the discipline was performed rather than held.

Two questions genuinely need answering, and each has a fashionable default that would be wrong here.

## Decision drivers

- The panel is not scored, but it is read. Consistency with the rest of the repository is the whole point of spending any structure on it at all.
- Money crosses the wire as a decimal string (ADR-0004). JavaScript has exactly one numeric type, and it is the IEEE-754 float that ADR forbids in the backend. Whatever the structure is, it has to make it hard for an amount to meet `Number()`.
- **Every writing endpoint answers with the full machine state.** `POST /api/machine/coins`, `POST /api/machine/purchases`, `PUT /api/machine/service` and `POST /api/machine/coins/return` all return `machine: {...}` alongside whatever physically left the machine. That is a property of the contract (ADR-0012, ADR-0015), not an accident, and it changes which client-side problems exist.
- Clients branch on the stable `code` of a problem document, never on its `detail` (ADR-0012).
- Nobody is going to configure a Deptrac equivalent for a dozen files. Whatever rule governs the layers has to be simple enough to hold by reading the code.
- The backend is what is evaluated. Time spent here is time not spent defending it.

## Considered options

**Structure**: layer-based (`pages` / `components` / `hooks` / `services`) · feature-sliced design · flat `src/` with everything beside `App.jsx`.

**Server communication**: native `fetch` behind a service module · TanStack Query · SWR · axios.

**Test seam**: mock the `services/` module · Mock Service Worker intercepting at the network level.

## Decision outcome

**Layer-based with one direction of dependency, and native `fetch` behind `services/`.** The layer table lives in `CLAUDE.md` § "Frontend architecture — the layer rule"; the rule it exists to state is one sentence: `components/` does not know `services/`.

### Why layer-based and not feature-sliced

Feature-sliced design is the current recommendation for scaling a React codebase, and the word "scalable" makes it sound free. It is not free: it costs a folder tree per feature and a rule about which slice may import which, and it earns that cost when features have to be removable independently.

There is one screen. Feature-slicing a single feature is ceremony wearing the vocabulary of rigour, and the challenge statement warns against over-engineering in the same breath as under-engineering. The honest reading of FSD's own advice is that it applies above the size at which this project stops.

What the layers here buy is smaller and real: a place for the rule that matters. A presentational component that cannot reach the network cannot grow a business decision, because it has no way to ask a question.

### Why no data-fetching library

TanStack Query exists to solve cache invalidation and refetching: you mutate something, the server's copy is now newer than yours, and the library orchestrates finding out what changed.

This API answered that question in its own design. A mutation's response *is* the new state, complete. There is nothing stale to invalidate and nothing to refetch — the client replaces what it holds with what just came back. Adding a cache in front of that would mean explaining, in an interview, why there is a cache in front of an API that hands back the truth on every write. There is no good answer.

This is deliberately **not** the argument "the app is small so we skipped the library". Small would be a weak reason; a library that pays for itself at any size should be adopted at any size. The reason is a property of the contract: the problem the library solves was designed out of existence upstream.

**Why not axios**: `fetch` is in every runtime that matters. axios is a dependency bought to get a nicer surface over something already present, and the one feature worth having — interceptors — is a nine-line `httpClient` here, where it also becomes the single place that understands `application/problem+json`.

### Why the tests mock the module, not the network

Mock Service Worker intercepts at the network level, so components exercise the real service layer, which is higher fidelity and is what the 2026 guidance suggests.

It was rejected because the seam already exists as a module. `services/` **is** the boundary between the panel and the contract — mocking it is mocking the port, which is precisely the doctrine the backend already applies (mock the port, never the value object). MSW would put a second, parallel boundary next to the one that is already there, and `services/httpClient.js` would still need its own test against a mocked `fetch`, because somebody has to prove the translation from a problem document to an error object. Two seams to learn instead of one, for fidelity that a thin client does not need.

## Consequences (positive)

- The rule that matters fits in a sentence and can be checked by reading an import list. `frontend-architecture-reviewer` exists to read exactly that.
- An amount has one path: it arrives as a string from `services/`, travels as a prop, and reaches the DOM. There is no arithmetic anywhere for it to be converted for, so `Number()` on a price is visible as an anomaly rather than buried in a calculation.
- Zero data dependencies. The `npm audit` surface is React plus the build toolchain, and the shipped bundle carries nothing that exists to solve a problem this API does not have.
- `services/` is the only module that knows the contract, so a change to the API is a change to one folder, and the OpenAPI document of ADR-0015 is what it is written against.
- Adding a second screen is adding a file to `pages/` and a route; the decision to restructure into features can be taken later, when there is something to slice.

## Consequences (negative)

- **No in-flight deduplication, and that is a real defect surface.** Double-clicking a coin button sends two requests. The mitigation is disabling controls while an action is pending, which `useMachine` tracks — but that is discipline, not a guarantee: a new control that forgets to read the pending flag reintroduces the bug, and nothing fails when it does. A library would have made this structural instead of remembered.
- **The layer rule has no CI teeth.** The backend's equivalent is a Deptrac failure; this one is a reviewer agent and a paragraph in `CLAUDE.md`, both of which a hurried afternoon can ignore. The asymmetry is worth stating rather than glossing: the frontend's architecture is upheld by attention, and attention is the thing that runs out.
- The decision is revisitable but not free to revisit. If a second screen ever shares server state with this one, "no library" stops being right, and adopting it then costs more than adopting it now would have. Accepted knowingly — no second screen is on the backlog.
- Layer-based structure spreads one feature's pieces across four folders. With one feature that is invisible; at six it is the exact friction feature-slicing exists to remove, and whoever gets there will be paying for this decision rather than benefiting from it.
