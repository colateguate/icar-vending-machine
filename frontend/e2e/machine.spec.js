import { expect, test } from '@playwright/test';

/**
 * What only a real browser can answer about the panel.
 *
 * jsdom applies no stylesheet — `vite.config.js` sets `css: false` on purpose,
 * because components are queried by role and parsing CSS would buy nothing —
 * and it has no layout and no hit testing either. That leaves one class of
 * regression invisible to the whole Vitest suite: a stylesheet that changes
 * behaviour. Both cases below happened, and both were found by hand.
 */
const CATALOGUE = {
  products: [
    { selector: 'WATER', name: 'Water', price: '0.65', count: 10 },
    { selector: 'JUICE', name: 'Juice', price: '1.00', count: 10 },
    { selector: 'SODA', name: 'Soda', price: '1.50', count: 10 },
  ],
  changeReserve: [
    { denomination: '0.05', count: 20 },
    { denomination: '0.10', count: 20 },
    { denomination: '0.25', count: 20 },
  ],
};

/**
 * The state is put there through the API and read back through the screen.
 * Driving the setup through the UI as well would make every one of these
 * assertions depend on the whole panel working, which is the opposite of what a
 * test at this level is for: each one should be able to fail for exactly one
 * reason.
 */
test.beforeEach(async ({ request }) => {
  const seeded = await request.put('/api/machine/service', { data: CATALOGUE });

  expect(seeded.status()).toBe(200);
});

/**
 * The sheen over the glass is a `::after` drawn across the whole window, and
 * the product buttons are underneath it. `pointer-events: none` is the single
 * rule keeping it decorative.
 *
 * No component test can see this. `userEvent.click` dispatches the event at the
 * node, so it never consults the layer model — delete that line and jsdom stays
 * green while every product button in the real cabinet goes dead to the mouse.
 * Playwright clicks at a point and checks what is actually on top of it, which
 * is the same question a customer's finger asks.
 */
test('a product button takes the click through the sheen drawn over it', async ({ page }) => {
  await page.goto('/');

  await page.getByRole('button', { name: /WATER/ }).click();

  await expect(page.getByRole('status', { name: 'Display' })).toHaveText('Insert 0.65 more');
});

/**
 * Chrome's own accessibility tree, over CDP, rather than a role query.
 * Playwright derives an accessible name from the text content and is therefore
 * blind to `text-transform`, which is exactly the regression being watched for
 * — ADR-0017 § "The crux" has the measurements. Chromium only, which is also
 * the whole browser list here.
 */
async function namedNodes(page) {
  const cdp = await page.context().newCDPSession(page);

  await cdp.send('Accessibility.enable');

  const { nodes } = await cdp.send('Accessibility.getFullAXTree');

  return nodes
    .filter((node) => !node.ignored && node.name?.value?.trim())
    .map((node) => ({ role: node.role?.value, name: node.name.value }));
}

/**
 * `text-transform: uppercase` is not presentational: the browser puts the
 * transformed text into the accessibility tree, so a purely visual rule renames
 * every control it touches. It shipped once in ticket 17c — "Service" reached a
 * screen reader as "SERVICE" — and the 106 tests of the day stayed green,
 * because the stylesheet that broke the name was never applied.
 */
test('the controls keep the names a screen reader reads out', async ({ page }) => {
  await page.goto('/');

  /*
   * Read after the machine is in: half of these controls do not exist until it
   * answers. The wait is allowed to use Playwright's own naming — it is the
   * assertions that may not, and one of them is about a button this wait names.
   */
  await expect(page.getByRole('button', { name: /WATER/ })).toBeVisible();

  const named = await namedNodes(page);

  expect(named).toContainEqual({ role: 'heading', name: 'Vending machine' });
  expect(named).toContainEqual({ role: 'button', name: 'Service' });
  expect(named).toContainEqual({ role: 'region', name: 'Products' });
  expect(named).toContainEqual({ role: 'region', name: 'Insert a coin' });
  expect(named).toContainEqual({ role: 'status', name: 'Display' });
  expect(named).toContainEqual({ role: 'status', name: 'Dispense tray' });
  expect(named).toContainEqual({ role: 'button', name: 'WATER Water 0.65 10 left' });

  /*
   * RETURN-COIN is deliberately not one of the sentinels. It is spelled in
   * capitals in the markup because that is the vocabulary of the brief and of
   * the CLI, so an uppercasing rule would leave it exactly as it is — and a
   * sentinel that cannot detect the thing it watches for is worse than none.
   * It is asserted for the plainer reason that the button must have a name.
   */
  expect(named).toContainEqual({ role: 'button', name: 'RETURN-COIN' });
});

/**
 * Every name in the service drawer is assembled rather than written. A till
 * switch is called after the coin it governs and a shelf field after the
 * product it belongs to, and in both cases that word sits in a
 * `visually-hidden` span, so the drawer does not print the same figure three
 * and four times down a row.
 *
 * Which makes every one of those names a property of the *stylesheet*. Turn
 * that class into `display: none` — the reflex fix for "hide this" — and Chrome
 * drops the text from the accessibility tree: six checkboxes all called
 * "accepted", and three fields per product all called "name", "price" and
 * "units" with nothing to say which product. Every Vitest query for them stays
 * green throughout, because jsdom runs with `css: false` and never applies the
 * rule that broke them.
 */
test('the drawer names its controls out of text that is never on screen', async ({ page }) => {
  await page.goto('/');
  await page.getByRole('button', { name: 'Service' }).click();
  await expect(page.getByRole('dialog', { name: 'Service' })).toBeVisible();

  const named = await namedNodes(page);

  expect(named).toContainEqual({ role: 'checkbox', name: '0.50 — accepted' });
  expect(named).toContainEqual({ role: 'checkbox', name: '1.00 — accepted' });
  expect(named).toContainEqual({ role: 'textbox', name: 'WATER — name' });
  expect(named).toContainEqual({ role: 'textbox', name: 'WATER — price' });
  expect(named).toContainEqual({ role: 'spinbutton', name: 'WATER — units' });
  expect(named).toContainEqual({ role: 'button', name: 'Remove WATER' });
});

/**
 * The other half of what a stylesheet can do to this drawer, and the one this
 * ticket was bitten by: the fields were all there, correctly named and holding
 * the right values, and one of them was too narrow to show what it held. A
 * price of "0.65" arrived in a box 44px wide that needed 51 and read "0.6".
 *
 * Asked as an invariant rather than as pixels, so it survives a redesign: no
 * field may need more room than it has, and the drawer may not scroll sideways.
 * `toHaveValue` cannot see this — the DOM value is right either way — and jsdom
 * has no layout at all, so this question has no answer below this level.
 */
test('no field in the drawer is too narrow for what it holds', async ({ page }) => {
  /*
   * A phone, and the size is what makes this test able to fail at all. The
   * drawer is a fixed 24rem that gives way to `max-width: 100%`, so a narrow
   * screen is where its fields are really squeezed.
   *
   * The desktop reproduction does not survive down here, which was worth
   * finding out: on a real screen the bug needed the 17px the drawer's vertical
   * scrollbar takes, and headless Chromium draws scrollbars that occupy no
   * layout space at all. Written against a tall viewport this test passed with
   * the broken stylesheet still in place — a guard that cannot fail where it
   * runs is not a guard.
   */
  await page.setViewportSize({ width: 360, height: 640 });
  await page.goto('/');
  await page.getByRole('button', { name: 'Service' }).click();

  const drawer = page.getByRole('dialog', { name: 'Service' });

  await expect(drawer).toBeVisible();

  const clipped = await drawer
    .locator('input')
    .evaluateAll((fields) =>
      fields
        .filter((field) => field.scrollWidth > field.clientWidth)
        .map((field) => ({ field: field.id, value: field.value })),
    );

  expect(clipped).toEqual([]);
  expect(await drawer.evaluate((panel) => panel.scrollWidth > panel.clientWidth)).toBe(false);
});
