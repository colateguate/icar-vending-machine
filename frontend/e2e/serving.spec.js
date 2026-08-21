import { expect, test } from '@playwright/test';

/**
 * What only the real nginx can answer.
 *
 * Everything here is decided in `docker/nginx.conf` and nothing else in the
 * repository watches it. Each of these three is a single character away from
 * breaking the whole application without turning one existing test red, which
 * is exactly the shape of thing this level exists for. No browser page is
 * opened for the first and the third: the questions are about responses, and
 * `request` asks them without paying for a render.
 */
test('the API path arrives at the backend the way it was asked for', async ({ request }) => {
  /*
   * `proxy_pass http://backend:8080` carries no URI part, which is what makes
   * nginx forward the original path untouched. Add a trailing slash and the
   * `/api/` prefix is stripped instead: the backend is asked for /machine, has
   * no such route, and every screen in the panel goes blank behind a 404.
   */
  const response = await request.get('/api/machine');

  expect(response.status()).toBe(200);

  /*
   * A 200 alone would also be the answer of an index.html served by the SPA
   * fallback, so what is asserted is that this came from the API: a JSON
   * content type, and a machine document inside it. Deliberately nothing about
   * what the machine holds — a question about a reverse proxy that fails
   * because someone emptied a slot is a question that reports the wrong fault.
   */
  expect(response.headers()['content-type']).toContain('application/json');
  expect((await response.json()).machine).toBeDefined();
});

/**
 * `try_files $uri $uri/ /index.html`. There is one screen today, so nothing
 * navigates anywhere — which is precisely why this would rot unnoticed until
 * the first deep link somebody adds comes back as nginx's own 404 page.
 */
test('a path the bundle owns falls into the app, not into an nginx 404', async ({ page }) => {
  const response = await page.goto('/service/whatever-a-route-might-be');

  expect(response.status()).toBe(200);

  await expect(page.getByRole('heading', { level: 1, name: 'Vending machine' })).toBeVisible();
});

/**
 * The document must not be cached and the assets must be. Get it the wrong way
 * round and a browser keeps serving a deployment-old index.html that points at
 * asset filenames this deployment no longer has — a blank page that clears
 * itself only when someone thinks to hard-refresh.
 *
 * The headers are asserted together on purpose: they come out of one `map` and
 * one set of `add_header` directives at server level, and the reason they were
 * written that way is that a `location` declaring any header of its own
 * **replaces** every inherited one. That trap deleted the security headers from
 * every route once already, silently, so they are checked here too rather than
 * left to be discovered the same way twice.
 */
test('the document is never cached, the content-addressed assets always are', async ({
  request,
}) => {
  const document = await request.get('/');

  expect(document.status()).toBe(200);
  expect(document.headers()['cache-control']).toBe('no-cache');
  expect(document.headers()['x-content-type-options']).toBe('nosniff');
  expect(document.headers()['content-security-policy']).toContain("default-src 'self'");

  // Whatever Vite named the bundle this build, taken from the document itself
  // rather than pinned in the test: the filename is a hash and changes on every
  // meaningful change to the panel.
  const asset = (await document.text()).match(/\/assets\/[\w.-]+\.js/);

  expect(asset).not.toBeNull();

  const bundle = await request.get(asset[0]);

  expect(bundle.status()).toBe(200);
  expect(bundle.headers()['cache-control']).toBe('public, max-age=31536000, immutable');
  expect(bundle.headers()['x-content-type-options']).toBe('nosniff');
});
