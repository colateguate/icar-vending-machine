import { defineConfig, devices } from '@playwright/test';

/**
 * The fifth test level, and the only one that needs a browser and a running
 * stack. What belongs here, and what each spec watches, is written down in
 * `e2e/README.md`.
 *
 * The one thing worth saying twice: there is no `webServer`. `make up` brings
 * the stack up and this only points at it.
 */
const baseURL = process.env.E2E_BASE_URL ?? 'http://localhost:3000';

export default defineConfig({
  testDir: './e2e',

  /*
   * One worker, and no parallelism inside a file either. The machine is a
   * single aggregate: two specs writing to it at once would be testing
   * optimistic locking by accident, and failing for a reason that has nothing
   * to do with what they ask.
   */
  fullyParallel: false,
  workers: 1,

  /*
   * No retries, here of all places. A smoke test that passes on the second
   * attempt has found something and hidden it — and every one of these
   * questions has a deterministic answer, because the state each needs is put
   * there through the API first.
   */
  retries: 0,

  forbidOnly: Boolean(process.env.CI),
  reporter: process.env.CI ? [['github'], ['list']] : [['list']],

  use: {
    baseURL,
    trace: 'retain-on-failure',
  },

  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
});
