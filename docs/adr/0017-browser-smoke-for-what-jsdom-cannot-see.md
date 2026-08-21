# 0017 — Add a fifth test level: browser smoke for what jsdom cannot see

- **Status**: accepted
- **Date**: 2026-08-21

## Context and problem statement

Four suites cover the backend and two cover the panel, and between them they answer every question about rules, orchestration, adapters, HTTP contract, rendering and state. All of them share one blind spot, and ticket 17c walked into it twice in a single afternoon.

`vite.config.js` sets `test.css: false`. That is the right call and this ADR does not revisit it: components are queried by role rather than by class, so parsing stylesheets would be time spent for nothing. But it means no stylesheet is applied in any test, and jsdom has neither layout nor hit testing to apply one to. A rule that changes behaviour is therefore invisible to the entire suite.

Both failures were real:

- **`text-transform: uppercase` renames controls.** Chrome puts the transformed text into the accessibility tree, so "Service" reached a screen reader as "SERVICE" and the region called "Products" became "PRODUCTS". The 106 tests of that day stayed green. It was caught by comparing accessibility trees by hand.
- **A decorative overlay can swallow every click underneath it.** The sheen over the cabinet glass is drawn across the whole window with the product buttons under it, and `pointer-events: none` is the single rule keeping it decorative. `userEvent.click` dispatches the event at the node and never consults the layer model, so deleting that line leaves the suite green while every product button goes dead to the mouse.

Ticket 13b then added a third category with the same property. The panel's nginx decides three things — that `/api/…` reaches the backend with its path intact, that a path the bundle owns falls into `index.html`, and which responses may be cached — and each is one character away from breaking the application without turning an existing test red. The trailing slash on `proxy_pass` is the sharpest: it rewrites `/api/machine` to `/machine`, the backend answers 404, and the whole panel is dead.

The question this ADR answers is not "should there be end-to-end tests". It is whether these particular blind spots are worth a fifth level, a browser download and a running stack.

## Decision drivers

- Every one of the failures above is **silent**. There is no partial signal to notice, no flaky test to investigate: the suite is fully green and the application is broken.
- They are also **cheap to cause**: deleting one CSS declaration, adding one character to a config.
- The panel is not what is evaluated. Time spent here is time not spent defending the backend, and a suite that grows fat is a suite people start skipping.
- The stack these would run against already exists: ticket 13b builds both images and `make up` serves them.
- ADR-0016 already conceded that the frontend's discipline is upheld by review rather than by CI. Adding a level that CI can run is the first thing that changes that, and it should be judged on whether it earns it.

## Considered options

1. **Leave it to review**, as ADR-0016 does for the layer rule.
2. **Enable CSS in jsdom** (`test.css: true`) and assert on computed styles.
3. **Cypress**, the other mature browser runner.
4. **Playwright, with a hard limit on what belongs in it.**

## Decision outcome

**Playwright, five specs, and a written rule about what may join them.** `frontend/e2e/README.md` states the boundary: only what *needs* a browser or the real image. A case Vitest can answer is a slow test for no reason.

### Why not leave it to review

This is the status quo and it is what ADR-0016 chose for the layer rule, so the asymmetry deserves a reason rather than a preference.

The layer rule is visible in an import list: a reviewer reading a diff sees `machineApi` imported inside `components/` and knows immediately. None of the failures above is visible that way. Deleting `pointer-events: none` looks like tidying up a stylesheet; adding a slash to a URL looks like a typo fix. A reviewer would have to hold the whole layer model of a browser in their head to see the consequence — and the person best placed to catch it is the one who just wrote the change and is sure it is harmless.

Review catches what is legible in a diff. These are not.

### Why not jsdom with CSS enabled

It would not work, which settles it before the cost question. jsdom parses CSS and can report computed styles, but it performs **no layout and no hit testing**: there is no geometry, so nothing is ever on top of anything, and `elementFromPoint` has no meaning. The overlay failure is invisible to it in principle rather than by configuration. The accessibility-tree failure fares no better: jsdom has no accessibility tree.

It would also slow down all 131 tests to answer none of these questions.

### Why Playwright and not Cypress

Both would drive a real browser and either would catch the overlay. The three deciders:

- **Playwright drives CDP**, which is what makes the accessibility check possible at all — see below, because that turned out to be the crux.
- Its `request` fixture asks the three nginx questions **without opening a page**, so three of the five specs cost an HTTP round trip rather than a render. The whole suite runs in about two seconds.
- Its actionability check is precisely the assertion wanted: it verifies what is on top of the click point before clicking, and names the element that intercepted. The failure message for the overlay names `machine__window` as intercepting pointer events, which is the diagnosis rather than a symptom.

### The crux: Playwright's accessible name is not the browser's

The obvious way to write the second spec is a role query for the button named "Service". It does not work, and the reason is worth recording because it is the opposite of what one would assume of a browser-driving tool.

Measured on this page with `text-transform: uppercase` reintroduced:

| Source | Name |
|---|---|
| `textContent` | `Service` |
| `innerText` | `SERVICE` |
| **Chrome's accessibility tree, over CDP** | **`SERVICE`** |
| Playwright's role query for `Service`, `exact: true` | still matches |

Playwright computes the accessible name from text content, so `text-transform` is invisible to it. A sentinel built on a role query would have been exactly as blind as jsdom — green under the very regression it was written to catch, and worse than no test, because the tick would have been believed.

The names are therefore read from `Accessibility.getFullAXTree` over CDP. That is Chromium-only, which is also the whole browser list here: this suite exists to ask what a browser really does, and one browser answers it.

## Consequences (positive)

- The two stylesheet failures of ticket 17c and the three nginx behaviours of 13b now have something watching them, and each was **watched failing** before being kept: the protecting line was removed, the image rebuilt, the red observed, the line restored. Five mutations, five reds, each landing on the assertion it should.
- CI runs them, so a config edit is caught in the pull request rather than by whoever next opens the panel. A cold build of both images with no layer cache measures 33 seconds; the suite itself runs in under two.
- The boundary is written down in `frontend/e2e/README.md` rather than left to taste, which is what a level like this needs to not become the place slow duplicate tests accumulate.
- `prefers-reduced-motion` and similar browser-only checks that were done by hand in 17c now have a home.

## Consequences (negative)

- **A level that cannot run on a bare checkout.** It needs Docker, a built stack and a 114 MB browser. It sits outside `make qa` for that reason — the same trade mutation testing already made — and a gate outside the one command everybody runs is a gate that can be forgotten. `make front-e2e` fails loudly when nothing answers on :3000, which is mitigation rather than a fix.
- **The mutation discipline is expensive to repeat.** Proving one of these red means editing a stylesheet, rebuilding the image and running again. That is under a minute today, and it is exactly the kind of cost that stops being paid when someone is in a hurry — at which point new specs here are back to being tests nobody has watched fail.
- **The smoke job lives in the frontend workflow only.** `ci-frontend.yml` ignores `backend/**`, so a backend-only change does not run it. The three nginx questions are frontend concerns and the backend has its own acceptance suite over HTTP, so the gap is narrow — but it is a gap: a backend change that breaks the panel is caught by the panel's own CI only on the next frontend commit.
- **Chromium only.** Nothing here checks Firefox or WebKit, and the CDP dependency means the accessibility spec cannot be run against them without being rewritten. This suite answers "what does a browser do", not "do all browsers agree".
- One more dependency, one more thing to keep up to date, on the half of the repository that is explicitly not what is being evaluated.
