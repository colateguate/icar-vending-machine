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

  describe('what it shows', () => {
    it('offers one field per product, seeded with what the slot holds', async () => {
      const user = userEvent.setup();
      drawer();
      await open(user);

      expect(screen.getByRole('spinbutton', { name: /WATER/ })).toHaveValue(5);
      expect(screen.getByRole('spinbutton', { name: /JUICE/ })).toHaveValue(0);
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
    it('sends back the names and prices it was given, untouched', async () => {
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

    it('can load a denomination the till had none of, from zero', async () => {
      const onService = vi.fn();
      const user = userEvent.setup();
      drawer({ onService });
      await open(user);

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

      await user.clear(screen.getByRole('spinbutton', { name: /^0\.50/ }));
      await user.type(screen.getByRole('spinbutton', { name: /^0\.50/ }), '0');
      await user.click(screen.getByRole('button', { name: 'Apply' }));

      const [, sentReserve] = onService.mock.calls[0];

      expect(sentReserve).toContainEqual({ denomination: '0.50', count: 0 });
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
      expect(screen.getByRole('checkbox', { name: '0.25 — accepted' })).toBeDisabled();
    });
  });
});
