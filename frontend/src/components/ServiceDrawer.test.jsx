import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import ServiceDrawer from './ServiceDrawer';

/**
 * The technician's door. What is worth testing here is mostly what you cannot
 * see by looking at the screen: where the focus goes, what the keyboard does,
 * and which fields end up in the request.
 */
const products = [
  { selector: 'WATER', name: 'Water', price: '0.65', count: 5 },
  { selector: 'JUICE', name: 'Orange juice', price: '1.00', count: 0 },
];

/**
 * Every coin the acceptor can read, which is what the technician's form is
 * about: the four this machine is taking today, and the two it is not. The
 * customer's buttons are the other list and are not this component's business.
 */
const supportedCoins = [
  { denomination: '0.05', dispensableAsChange: true, enabled: true },
  { denomination: '0.10', dispensableAsChange: true, enabled: true },
  { denomination: '0.25', dispensableAsChange: true, enabled: true },
  { denomination: '0.50', dispensableAsChange: true, enabled: false },
  { denomination: '1.00', dispensableAsChange: false, enabled: true },
  { denomination: '2.00', dispensableAsChange: false, enabled: false },
];

// The till holds three denominations, and one of them is money the machine has
// stopped taking. CoinBag omits the ones it has none of, which is the shape the
// drawer has to cope with rather than a convenience.
const changeReserve = {
  coins: [
    { denomination: '0.05', count: 8 },
    { denomination: '0.25', count: 2 },
    { denomination: '0.50', count: 4 },
  ],
  amount: '2.90',
};

const drawer = (props = {}) =>
  render(
    <ServiceDrawer
      changeReserve={changeReserve}
      onService={() => {}}
      products={products}
      supportedCoins={supportedCoins}
      {...props}
    />,
  );

const open = async (user) => {
  await user.click(screen.getByRole('button', { name: 'Service' }));

  return screen.getByRole('dialog', { name: 'Service' });
};

/** The drawer opens on Products; the coins live behind the other tab. */
const openCoins = async (user) => {
  await user.click(screen.getByRole('tab', { name: 'Coins' }));
};

describe('ServiceDrawer', () => {
  describe('the door', () => {
    it('is shut until someone with a key opens it', () => {
      drawer();

      expect(screen.getByRole('button', { name: 'Service' })).toHaveAttribute(
        'aria-expanded',
        'false',
      );
      expect(screen.queryByRole('dialog')).toBeNull();
    });

    it('says it is open, in the attribute a screen reader reads', async () => {
      const user = userEvent.setup();
      drawer();

      await open(user);

      expect(screen.getByRole('button', { name: 'Service' })).toHaveAttribute(
        'aria-expanded',
        'true',
      );
    });

    /**
     * Opening a panel and leaving the focus behind is the commonest way to make
     * something usable with a mouse and unusable without one: the panel exists,
     * and the keyboard is still somewhere else on the page.
     */
    it('moves the focus into the drawer when it opens', async () => {
      const user = userEvent.setup();
      drawer();

      const panel = await open(user);

      expect(panel).toHaveFocus();
    });

    it('closes on Escape, which is where a keyboard reaches for first', async () => {
      const user = userEvent.setup();
      drawer();
      await open(user);

      await user.keyboard('{Escape}');

      expect(screen.queryByRole('dialog')).toBeNull();
    });

    /**
     * The drawer is non-modal, so Tab can walk out of it and back into the
     * panel. Escape has to keep working from there — a handler bound to the
     * panel itself would go quiet exactly when someone reaches for the key.
     */
    it('closes on Escape even after the focus has wandered out of it', async () => {
      const user = userEvent.setup();
      drawer();
      await open(user);

      document.body.focus();
      await user.keyboard('{Escape}');

      expect(screen.queryByRole('dialog')).toBeNull();
    });

    /**
     * And the focus has to come back. Left on a node that no longer exists, it
     * falls to the document body and the next Tab starts from the top of the
     * page — the drawer is gone and so is your place in it.
     */
    it('hands the focus back to the button it came from', async () => {
      const user = userEvent.setup();
      drawer();
      await open(user);

      await user.keyboard('{Escape}');

      expect(screen.getByRole('button', { name: 'Service' })).toHaveFocus();
    });

    it('also closes from its own close control, for whoever is using a mouse', async () => {
      const user = userEvent.setup();
      drawer();
      await open(user);

      await user.click(screen.getByRole('button', { name: 'Close' }));

      expect(screen.queryByRole('dialog')).toBeNull();
      expect(screen.getByRole('button', { name: 'Service' })).toHaveFocus();
    });
  });

  /**
   * Two things are managed behind this door and they have nothing to do with
   * each other: what the machine sells and what it takes. Stacked in one
   * column they were nearly two screens of scrolling, and nothing on screen
   * said which of the two you were editing.
   */
  describe('its two halves', () => {
    it('opens on the products, with the coins behind their own tab', async () => {
      const user = userEvent.setup();
      drawer();
      await open(user);

      expect(screen.getByRole('tab', { name: 'Products' })).toHaveAttribute(
        'aria-selected',
        'true',
      );
      expect(screen.getByRole('tab', { name: 'Coins' })).toHaveAttribute('aria-selected', 'false');
      expect(screen.getByRole('textbox', { name: 'WATER — name' })).toBeVisible();
      expect(screen.queryByRole('checkbox', { name: '0.25 — accepted' })).toBeNull();
    });

    it('shows the coins, and only the coins, once they are asked for', async () => {
      const user = userEvent.setup();
      drawer();
      await open(user);

      await openCoins(user);

      expect(screen.getByRole('checkbox', { name: '0.25 — accepted' })).toBeVisible();
      expect(screen.queryByRole('textbox', { name: 'WATER — name' })).toBeNull();
    });

    /**
     * A tab strip that only answers to a mouse is half a tab strip. The arrows
     * are the part people who cannot use a mouse rely on, and the part nobody
     * notices is missing until they need it.
     */
    it('moves between tabs with the arrow keys', async () => {
      const user = userEvent.setup();
      drawer();
      await open(user);

      await user.click(screen.getByRole('tab', { name: 'Products' }));
      await user.keyboard('{ArrowRight}');

      expect(screen.getByRole('tab', { name: 'Coins' })).toHaveAttribute('aria-selected', 'true');
      expect(screen.getByRole('tab', { name: 'Coins' })).toHaveFocus();

      await user.keyboard('{ArrowLeft}');

      expect(screen.getByRole('tab', { name: 'Products' })).toHaveAttribute(
        'aria-selected',
        'true',
      );
      expect(screen.getByRole('tab', { name: 'Products' })).toHaveFocus();
    });

    /**
     * One Apply for both halves, because SERVICE states the whole machine: an
     * "apply the coins only" would have to send the products anyway, so two
     * buttons would be two lies about what they do. The test proves the hidden
     * half still travels — the form holds the state, not the DOM.
     */
    it('sends both halves from one Apply, including the one not on screen', async () => {
      const onService = vi.fn();
      const user = userEvent.setup();
      drawer({ onService });
      await open(user);

      await openCoins(user);
      await user.click(screen.getByRole('checkbox', { name: '0.50 — accepted' }));
      await user.click(screen.getByRole('button', { name: 'Apply' }));

      const [sentProducts, , sentAcceptor] = onService.mock.calls[0];

      expect(sentProducts).toHaveLength(2);
      expect(sentAcceptor).toContain('0.50');
    });

    /**
     * The trap this tab strip introduces, and the reason the focus code had to
     * change: the field that stopped the visit can be on the half nobody is
     * looking at. Refusing without showing why is the exact failure the
     * validation was written to prevent, and hiding the field brings it back.
     */
    it('shows the half that stopped the visit, and puts the cursor in it', async () => {
      const onService = vi.fn();
      const user = userEvent.setup();
      drawer({ onService });
      await open(user);

      await user.clear(screen.getByRole('textbox', { name: 'WATER — price' }));
      await user.type(screen.getByRole('textbox', { name: 'WATER — price' }), 'free');
      await openCoins(user);
      await user.click(screen.getByRole('button', { name: 'Apply' }));

      expect(onService).not.toHaveBeenCalled();
      expect(screen.getByRole('tab', { name: 'Products' })).toHaveAttribute(
        'aria-selected',
        'true',
      );
      expect(screen.getByRole('textbox', { name: 'WATER — price' })).toHaveFocus();
    });
  });

  describe('what it shows', () => {
    it('offers one field per product, seeded with what the slot holds', async () => {
      const user = userEvent.setup();
      drawer();
      await open(user);

      expect(screen.getByRole('spinbutton', { name: /WATER/ })).toHaveValue(5);
      expect(screen.getByRole('spinbutton', { name: /JUICE/ })).toHaveValue(0);
    });

    /**
     * A service visit states the catalogue rather than adding to it, so a
     * technician correcting a price or a name is doing the thing the endpoint
     * was built for. Printed as a sentence, as they were, those two were the
     * only facts on this form nobody could change.
     */
    it('offers the name and the price of each product as fields, not as a sentence', async () => {
      const user = userEvent.setup();
      drawer();
      await open(user);

      expect(screen.getByRole('textbox', { name: 'WATER — name' })).toHaveValue('Water');
      expect(screen.getByRole('textbox', { name: 'WATER — price' })).toHaveValue('0.65');
    });

    /**
     * The price is a text field on purpose, and this is the assertion that
     * pins it. `type="number"` would make the browser the first thing to touch
     * an amount — normalising the separator by locale and accepting `1e21` —
     * and JavaScript only offers the float ADR-0004 refuses. Units are integers
     * and stay a spinbutton; money is a decimal string all the way to the DOM.
     */
    it('asks for the price as text, because an amount is not a number here', async () => {
      const user = userEvent.setup();
      drawer();
      await open(user);

      expect(screen.queryByRole('spinbutton', { name: 'WATER — price' })).toBeNull();
      expect(screen.getByRole('textbox', { name: 'WATER — price' })).toBeVisible();
    });

    /**
     * The selector is the identity of the row, not a field of it: changing it
     * would not rename a product, it would swap one product for another and
     * silently discontinue the first. Editing it is what the *new* rows of the
     * next ticket are for.
     */
    it('does not let the selector of an existing product be edited', async () => {
      const user = userEvent.setup();
      drawer();
      await open(user);

      expect(screen.queryByRole('textbox', { name: /WATER — selector/ })).toBeNull();
      expect(screen.getByRole('button', { name: 'Remove WATER' })).toBeVisible();
    });

    /**
     * The denomination the till ran out of is the whole reason to open this
     * drawer — it is the one that lit the EXACT CHANGE ONLY lamp. A form that
     * rendered only what `changeReserve` returned could never refill it, because
     * a coin bag omits the denominations it holds none of. And a form seeded
     * from what the machine *takes* could never switch anything back on, which
     * is why the rows come from what the acceptor can *read*.
     */
    it('offers a count for every coin the acceptor reads, taken today or not', async () => {
      const user = userEvent.setup();
      drawer();
      await open(user);
      await openCoins(user);

      expect(screen.getByRole('spinbutton', { name: /^0\.05/ })).toHaveValue(8);
      expect(screen.getByRole('spinbutton', { name: /^0\.10/ })).toHaveValue(0);
      expect(screen.getByRole('spinbutton', { name: /^0\.25/ })).toHaveValue(2);
      expect(screen.getByRole('spinbutton', { name: /^0\.50/ })).toHaveValue(4);
      expect(screen.getByRole('spinbutton', { name: /^1\.00/ })).toHaveValue(0);
      expect(screen.getByRole('spinbutton', { name: /^2\.00/ })).toHaveValue(0);
    });

    it('shows, per denomination, whether the machine is taking it', async () => {
      const user = userEvent.setup();
      drawer();
      await open(user);
      await openCoins(user);

      expect(screen.getByRole('checkbox', { name: '0.25 — accepted' })).toBeChecked();
      expect(screen.getByRole('checkbox', { name: '0.50 — accepted' })).not.toBeChecked();
      expect(screen.getAllByRole('checkbox')).toHaveLength(6);
    });

    /**
     * Loading 1.00 coins does not turn the lamp off, and the person holding the
     * coins should know that before they count them out. It is a description
     * rather than part of the label so the field is still called what it is.
     */
    it('warns that the coin it never gives back will not help', async () => {
      const user = userEvent.setup();
      drawer();
      await open(user);
      await openCoins(user);

      expect(screen.getByRole('spinbutton', { name: /^1\.00/ })).toHaveAccessibleDescription(
        /never given back as change/i,
      );
      expect(screen.getByRole('spinbutton', { name: /^0\.25/ })).toHaveAccessibleDescription('');
    });

    /**
     * The 0.50 in this till is money the machine holds and will not hand back,
     * because it has stopped taking that coin. Someone counting the drawer needs
     * to be told that before they wonder why the amount does not add up to
     * change it can pay.
     */
    it('warns that coins of a denomination it has stopped taking are stranded', async () => {
      const user = userEvent.setup();
      drawer();
      await open(user);
      await openCoins(user);

      expect(screen.getByRole('spinbutton', { name: /^0\.50/ })).toHaveAccessibleDescription(
        /not taken at the slot/i,
      );
    });
  });

  describe('what it sends', () => {
    /**
     * A service visit is a PUT: it states the result rather than a delta, so
     * whatever is left out of the body leaves the machine. Sending only the
     * counts would reprovision the catalogue with no names and no prices.
     */
    it('states every product whole, not just the counts it was asked about', async () => {
      const onService = vi.fn();
      const user = userEvent.setup();
      drawer({ onService });
      await open(user);

      await user.clear(screen.getByRole('spinbutton', { name: /WATER/ }));
      await user.type(screen.getByRole('spinbutton', { name: /WATER/ }), '12');
      await user.click(screen.getByRole('button', { name: 'Apply' }));

      const [sentProducts] = onService.mock.calls[0];

      expect(sentProducts).toEqual([
        { selector: 'WATER', name: 'Water', price: '0.65', count: 12 },
        { selector: 'JUICE', name: 'Orange juice', price: '1.00', count: 0 },
      ]);
    });

    /**
     * The price makes the whole round trip as the string that was typed. Not
     * `0.7`, not `0.70000000000000004`: the panel never asks JavaScript what
     * this amount is worth, because the only answer it has is a float
     * (ADR-0004).
     */
    it('sends a corrected price as the string it was typed as', async () => {
      const onService = vi.fn();
      const user = userEvent.setup();
      drawer({ onService });
      await open(user);

      await user.clear(screen.getByRole('textbox', { name: 'WATER — price' }));
      await user.type(screen.getByRole('textbox', { name: 'WATER — price' }), '0.70');
      await user.click(screen.getByRole('button', { name: 'Apply' }));

      const [sentProducts] = onService.mock.calls[0];

      expect(sentProducts[0].price).toBe('0.70');
    });

    it('sends a corrected name', async () => {
      const onService = vi.fn();
      const user = userEvent.setup();
      drawer({ onService });
      await open(user);

      await user.clear(screen.getByRole('textbox', { name: 'WATER — name' }));
      await user.type(screen.getByRole('textbox', { name: 'WATER — name' }), 'Still Water');
      await user.click(screen.getByRole('button', { name: 'Apply' }));

      const [sentProducts] = onService.mock.calls[0];

      expect(sentProducts[0]).toEqual({
        selector: 'WATER',
        name: 'Still Water',
        price: '0.65',
        count: 5,
      });
    });

    /**
     * Taking a product off the shelf is saying nothing about it: a PUT states
     * the catalogue, so what is left out is discontinued. There is no delete
     * endpoint and there does not need to be one.
     */
    it('discontinues a product by leaving it out', async () => {
      const onService = vi.fn();
      const user = userEvent.setup();
      drawer({ onService });
      await open(user);

      await user.click(screen.getByRole('button', { name: 'Remove WATER' }));
      await user.click(screen.getByRole('button', { name: 'Apply' }));

      const [sentProducts] = onService.mock.calls[0];

      expect(sentProducts).toEqual([
        { selector: 'JUICE', name: 'Orange juice', price: '1.00', count: 0 },
      ]);
    });

    /**
     * The row that goes is the row that was asked for, and its neighbours keep
     * what was typed into them. A form keyed by position loses that the moment
     * anything is removed: the fields stay put and the values slide up a row.
     */
    it('keeps what was typed into the rows it did not remove', async () => {
      const onService = vi.fn();
      const user = userEvent.setup();
      drawer({ onService });
      await open(user);

      await user.clear(screen.getByRole('textbox', { name: 'JUICE — name' }));
      await user.type(screen.getByRole('textbox', { name: 'JUICE — name' }), 'Apple juice');
      await user.click(screen.getByRole('button', { name: 'Remove WATER' }));
      await user.click(screen.getByRole('button', { name: 'Apply' }));

      const [sentProducts] = onService.mock.calls[0];

      expect(sentProducts).toEqual([
        { selector: 'JUICE', name: 'Apple juice', price: '1.00', count: 0 },
      ]);
    });

    /**
     * An empty shelf is a legal thing to ask for — the same technician who can
     * empty the till can retire the last product — and it is the one case where
     * "send nothing" and "send an empty list" would look alike from here.
     */
    it('can empty the shelf entirely', async () => {
      const onService = vi.fn();
      const user = userEvent.setup();
      drawer({ onService });
      await open(user);

      await user.click(screen.getByRole('button', { name: 'Remove WATER' }));
      await user.click(screen.getByRole('button', { name: 'Remove JUICE' }));
      await user.click(screen.getByRole('button', { name: 'Apply' }));

      const [sentProducts] = onService.mock.calls[0];

      expect(sentProducts).toEqual([]);
    });

    it('can load a denomination the till had none of, from zero', async () => {
      const onService = vi.fn();
      const user = userEvent.setup();
      drawer({ onService });
      await open(user);
      await openCoins(user);

      await user.clear(screen.getByRole('spinbutton', { name: /^0\.10/ }));
      await user.type(screen.getByRole('spinbutton', { name: /^0\.10/ }), '20');
      await user.click(screen.getByRole('button', { name: 'Apply' }));

      const [, sentReserve] = onService.mock.calls[0];

      expect(sentReserve).toContainEqual({ denomination: '0.10', count: 20 });
    });

    /**
     * All four go, including the ones left at zero. The request accepts
     * `count: 0` even though the response omits an empty denomination, and
     * leaving one out would mean "the machine no longer holds any" said by
     * silence — which is exactly the kind of thing a PUT should not have to
     * guess.
     */
    it('states the whole till, zeroes included', async () => {
      const onService = vi.fn();
      const user = userEvent.setup();
      drawer({ onService });
      await open(user);

      await user.click(screen.getByRole('button', { name: 'Apply' }));

      const [, sentReserve] = onService.mock.calls[0];

      expect(sentReserve).toEqual([
        { denomination: '0.05', count: 8 },
        { denomination: '0.10', count: 0 },
        { denomination: '0.25', count: 2 },
        { denomination: '0.50', count: 4 },
        { denomination: '1.00', count: 0 },
        { denomination: '2.00', count: 0 },
      ]);
    });

    /**
     * `dispensableAsChange` and the acceptor flag arrive on every coin and must
     * leave on none: the request body declares additionalProperties false, so a stray
     * field is a refusal rather than something the API politely ignores. Which
     * coins the machine takes travels in its own list, not as a flag on a till
     * row — a denomination can be in either without the other.
     */
    it('does not send back the flags it was told about', async () => {
      const onService = vi.fn();
      const user = userEvent.setup();
      drawer({ onService });
      await open(user);

      await user.click(screen.getByRole('button', { name: 'Apply' }));

      const [, sentReserve] = onService.mock.calls[0];

      for (const coin of sentReserve) {
        expect(Object.keys(coin)).toEqual(['denomination', 'count']);
      }
    });

    it('states the acceptor as the denominations left switched on', async () => {
      const onService = vi.fn();
      const user = userEvent.setup();
      drawer({ onService });
      await open(user);

      await user.click(screen.getByRole('button', { name: 'Apply' }));

      const [, , sentAcceptor] = onService.mock.calls[0];

      expect(sentAcceptor).toEqual(['0.05', '0.10', '0.25', '1.00']);
    });

    it('takes a denomination the machine was refusing, once its switch is on', async () => {
      const onService = vi.fn();
      const user = userEvent.setup();
      drawer({ onService });
      await open(user);
      await openCoins(user);

      await user.click(screen.getByRole('checkbox', { name: '0.50 — accepted' }));
      await user.click(screen.getByRole('button', { name: 'Apply' }));

      const [, , sentAcceptor] = onService.mock.calls[0];

      expect(sentAcceptor).toEqual(['0.05', '0.10', '0.25', '0.50', '1.00']);
    });

    /**
     * The state the whole feature exists to make reachable, and the one place
     * the panel must not be clever: every switch off is an empty list, which the
     * API reads as a machine out of service. Sending nothing instead would mean
     * "leave the acceptor as it was" — the opposite instruction.
     */
    it('asks for a machine out of service when every switch is off', async () => {
      const onService = vi.fn();
      const user = userEvent.setup();
      drawer({ onService });
      await open(user);
      await openCoins(user);

      for (const denomination of ['0.05', '0.10', '0.25', '1.00']) {
        await user.click(screen.getByRole('checkbox', { name: `${denomination} — accepted` }));
      }
      await user.click(screen.getByRole('button', { name: 'Apply' }));

      const [, , sentAcceptor] = onService.mock.calls[0];

      expect(sentAcceptor).toEqual([]);
    });

    /**
     * The money one. A service visit states the till in absolutes, so a form
     * that zeroed the count of a denomination it just switched off would write
     * off coins that are physically still inside the machine. Switching the
     * slot shut and emptying the till are two instructions, and this form only
     * gives the one it was asked for.
     */
    it('keeps the coins of a denomination it switches off', async () => {
      const onService = vi.fn();
      const user = userEvent.setup();
      drawer({ onService });
      await open(user);
      await openCoins(user);

      await user.click(screen.getByRole('checkbox', { name: '0.05 — accepted' }));
      await user.click(screen.getByRole('button', { name: 'Apply' }));

      const [, sentReserve, sentAcceptor] = onService.mock.calls[0];

      expect(sentReserve).toContainEqual({ denomination: '0.05', count: 8 });
      expect(sentAcceptor).not.toContain('0.05');
    });

    it('can still empty the till of a denomination it does not take', async () => {
      const onService = vi.fn();
      const user = userEvent.setup();
      drawer({ onService });
      await open(user);
      await openCoins(user);

      await user.clear(screen.getByRole('spinbutton', { name: /^0\.50/ }));
      await user.type(screen.getByRole('spinbutton', { name: /^0\.50/ }), '0');
      await user.click(screen.getByRole('button', { name: 'Apply' }));

      const [, sentReserve] = onService.mock.calls[0];

      expect(sentReserve).toContainEqual({ denomination: '0.50', count: 0 });
    });
  });

  describe('taking on a product it never had', () => {
    it('offers a blank row with a selector of its own to fill in', async () => {
      const user = userEvent.setup();
      drawer();
      await open(user);

      await user.click(screen.getByRole('button', { name: 'Add product' }));

      expect(screen.getByRole('textbox', { name: /selector/ })).toHaveValue('');
    });

    const fillIn = async (user, { selector, name, price, units }) => {
      await user.click(screen.getByRole('button', { name: 'Add product' }));
      await user.type(screen.getByRole('textbox', { name: /selector/ }), selector);
      await user.type(screen.getByRole('textbox', { name: `${selector} — name` }), name);
      await user.clear(screen.getByRole('textbox', { name: `${selector} — price` }));
      await user.type(screen.getByRole('textbox', { name: `${selector} — price` }), price);
      await user.clear(screen.getByRole('spinbutton', { name: `${selector} — units` }));
      await user.type(screen.getByRole('spinbutton', { name: `${selector} — units` }), units);
    };

    it('sends the new product alongside the ones already on the shelf', async () => {
      const onService = vi.fn();
      const user = userEvent.setup();
      drawer({ onService });
      await open(user);

      await fillIn(user, { selector: 'TEA', name: 'Iced Tea', price: '0.80', units: '4' });
      await user.click(screen.getByRole('button', { name: 'Apply' }));

      const [sentProducts] = onService.mock.calls[0];

      expect(sentProducts).toContainEqual({
        selector: 'TEA',
        name: 'Iced Tea',
        price: '0.80',
        count: 4,
      });
      expect(sentProducts).toHaveLength(3);
    });

    it('lets a blank row be taken back before it is ever sent', async () => {
      const onService = vi.fn();
      const user = userEvent.setup();
      drawer({ onService });
      await open(user);

      await user.click(screen.getByRole('button', { name: 'Add product' }));
      await user.click(screen.getByRole('button', { name: 'Remove the new product' }));
      await user.click(screen.getByRole('button', { name: 'Apply' }));

      expect(screen.queryByRole('textbox', { name: /selector/ })).toBeNull();
      expect(onService.mock.calls[0][0]).toHaveLength(2);
    });
  });

  describe('what it refuses to send', () => {
    /**
     * The whole reason this form validates at all: the visit is not attempted,
     * so nothing the technician typed into the other rows is lost to a 422 that
     * refuses the lot.
     */
    it('does not send a shelf it knows the machine will refuse', async () => {
      const onService = vi.fn();
      const user = userEvent.setup();
      drawer({ onService });
      await open(user);

      await user.click(screen.getByRole('button', { name: 'Add product' }));
      await user.type(screen.getByRole('textbox', { name: /selector/ }), 'iced tea');
      await user.click(screen.getByRole('button', { name: 'Apply' }));

      expect(onService).not.toHaveBeenCalled();
    });

    /**
     * And it says what is wrong beside the box that is wrong. A message in one
     * place about a field somewhere else is how a form makes someone hunt.
     */
    it('says what is wrong where it is wrong', async () => {
      const user = userEvent.setup();
      drawer();
      await open(user);

      await user.click(screen.getByRole('button', { name: 'Add product' }));
      await user.type(screen.getByRole('textbox', { name: /selector/ }), 'iced tea');
      await user.click(screen.getByRole('button', { name: 'Apply' }));

      const field = screen.getByRole('textbox', { name: /selector/ });

      expect(field).toHaveAccessibleDescription(/capital/i);
      expect(field).toBeInvalid();
    });

    /**
     * Pressing a button and having nothing happen is the failure this avoids:
     * the focus lands on the field that stopped the visit, so the answer to
     * "why did nothing happen" is already under the cursor.
     */
    it('puts the cursor in the field that stopped it', async () => {
      const user = userEvent.setup();
      drawer();
      await open(user);

      await user.click(screen.getByRole('button', { name: 'Add product' }));
      await user.type(screen.getByRole('textbox', { name: /selector/ }), 'iced tea');
      await user.click(screen.getByRole('button', { name: 'Apply' }));

      expect(screen.getByRole('textbox', { name: /selector/ })).toHaveFocus();
    });

    it('refuses a price the machine could not read', async () => {
      const onService = vi.fn();
      const user = userEvent.setup();
      drawer({ onService });
      await open(user);

      await user.clear(screen.getByRole('textbox', { name: 'WATER — price' }));
      await user.type(screen.getByRole('textbox', { name: 'WATER — price' }), '1,50');
      await user.click(screen.getByRole('button', { name: 'Apply' }));

      expect(onService).not.toHaveBeenCalled();
      expect(screen.getByRole('textbox', { name: 'WATER — price' })).toBeInvalid();
    });

    /**
     * Two rows claiming one selector is refused here rather than by the API,
     * which refuses the visit whole. Both rows are marked, because either one
     * could be the one that was meant.
     */
    it('refuses a new row claiming a selector the shelf already uses', async () => {
      const onService = vi.fn();
      const user = userEvent.setup();
      drawer({ onService });
      await open(user);

      await user.click(screen.getByRole('button', { name: 'Add product' }));
      await user.type(screen.getByRole('textbox', { name: /selector/ }), 'WATER');
      await user.click(screen.getByRole('button', { name: 'Apply' }));

      expect(onService).not.toHaveBeenCalled();

      /*
       * Marked on the new row and only there. The WATER already on the shelf
       * shows the machine's word for it as a heading rather than a field, so a
       * message about it would have nowhere to sit and nothing the reader could
       * do about it — and there are now two textboxes whose name starts with
       * WATER, which is why this one is addressed by its whole name.
       */
      const claim = screen.getByRole('textbox', { name: 'WATER — selector' });

      expect(claim).toBeInvalid();
      expect(claim).toHaveAccessibleDescription(/another row/i);
    });

    /**
     * Nothing complains before the first Apply: a form that objects to "W" on
     * the way to "WATER" teaches people to ignore what it says.
     */
    it('says nothing while a new selector is still being typed', async () => {
      const user = userEvent.setup();
      drawer();
      await open(user);

      await user.click(screen.getByRole('button', { name: 'Add product' }));
      await user.type(screen.getByRole('textbox', { name: /selector/ }), 'T');

      expect(screen.getByRole('textbox', { name: /selector/ })).toBeValid();
    });

    /**
     * And once a field has been told off it stops waiting for the next press:
     * the error goes as the mistake goes.
     */
    it('stops complaining about a field as it is corrected', async () => {
      const user = userEvent.setup();
      drawer();
      await open(user);

      await user.click(screen.getByRole('button', { name: 'Add product' }));
      await user.type(screen.getByRole('textbox', { name: /selector/ }), 'iced tea');
      await user.click(screen.getByRole('button', { name: 'Apply' }));

      await user.clear(screen.getByRole('textbox', { name: /selector/ }));
      await user.type(screen.getByRole('textbox', { name: /selector/ }), 'TEA');

      expect(screen.getByRole('textbox', { name: 'TEA — selector' })).toBeValid();
    });
  });

  describe('while the machine is answering', () => {
    it('refuses a second visit until the first is answered', async () => {
      const onService = vi.fn();
      const user = userEvent.setup();
      drawer({ disabled: true, onService });
      await open(user);

      await user.click(screen.getByRole('button', { name: 'Apply' }));

      expect(onService).not.toHaveBeenCalled();
      expect(screen.getByRole('button', { name: 'Apply' })).toBeDisabled();
      expect(screen.getByRole('spinbutton', { name: /WATER/ })).toBeDisabled();

      await openCoins(user);

      expect(screen.getByRole('checkbox', { name: '0.25 — accepted' })).toBeDisabled();
    });
  });
});
