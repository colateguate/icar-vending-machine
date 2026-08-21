# End-to-end tests

The fifth test level, and the only one that drives a real browser against the
running stack. Five specs, about two seconds, and every one of them was watched
failing before it was kept.

```bash
make up            # the stack these run against: panel on :3000, API on :8000
make front-e2e     # or, from frontend/: npx playwright test
```

`E2E_BASE_URL` points them somewhere else. There is no `webServer` in
`playwright.config.js` on purpose: two of these specs are questions about the
nginx in the panel's image, and a Playwright-started dev server would be a
different program answering them.

## What belongs here

Only what **needs** a browser or the real image. The bar is high: anything
Vitest can answer is a slow test for no reason here, and a level that grows fat
is a level people start skipping.

The four suites below this one — unit, component and module tests under
`frontend/src`, beside the code they cover — already answer everything about
rendering, state and the API client. What they cannot answer is anything that
depends on **layout, hit testing, the accessibility tree, or nginx**, because
jsdom has none of those and `vite.config.js` sets `css: false` deliberately.

So, concretely:

| Belongs here | Belongs in `frontend/src` |
|---|---|
| A control that a decorative overlay swallows the clicks of | Whether a click calls the right handler |
| The name a screen reader actually reads out | Whether an element has an accessible name at all |
| `/api/…` surviving the reverse proxy | What the API client does with a response |
| A deep link falling into `index.html` | Anything about routing the bundle owns |
| Which responses carry which cache and security headers | — |

## What each spec is watching, and why it would go unnoticed otherwise

**`machine.spec.js` — the stylesheet as a behaviour change.** Both of these
happened in ticket 17c and both were found by hand.

- The sheen over the glass is drawn across the whole window with the product
  buttons underneath it, and `pointer-events: none` is the only rule keeping it
  decorative. `userEvent.click` dispatches at the node and never consults the
  layer model, so deleting that line leaves every component test green while
  every product button in the real cabinet goes dead to the mouse.
- `text-transform: uppercase` renames controls: Chrome puts the transformed text
  into the accessibility tree, so "Service" reaches a screen reader as
  "SERVICE". The names here are read from Chrome's own tree over CDP, and that
  is not ceremony — measured on this page with the rule reintroduced,
  `textContent` says "Service", Chrome's tree says "SERVICE", and Playwright's
  own `getByRole({ name: 'Service', exact: true })` still matches. Its
  accessible name comes from the text content, so a sentinel built on
  `getByRole` would be exactly as blind as jsdom. That is Chromium-only, which
  is the whole browser list here.

**`serving.spec.js` — the three things `docker/nginx.conf` decides and nothing
else in the repository watches.** Each is one character away from breaking the
application without turning an existing test red.

- `proxy_pass` carries no URI part, so `/api/machine` arrives as `/api/machine`.
  Add a trailing slash and the backend is asked for `/machine`, answers 404, and
  the panel is dead — proved by adding it: three of the five specs went red.
- `try_files … /index.html` puts a path the bundle owns into the app instead of
  nginx's own 404 page. There is one screen today, which is exactly why this
  would rot unnoticed until the first deep link.
- The document must not be cached and the content-addressed assets must be. The
  security headers are asserted in the same test, because they come out of the
  same single place: a `location` that declares any `add_header` of its own
  **replaces** every inherited one, a trap that silently deleted them from every
  route once already.

## The rule these were written under

Every spec here was made to fail on purpose before being kept, by removing the
line it protects and rebuilding the image. An end-to-end test nobody has watched
fail is not evidence that something works; it is evidence that something ran.
