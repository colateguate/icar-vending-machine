import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { ApiProblem } from '../services/problemDetails';
import { getState, insertCoin, purchase, returnCoins, service } from '../services/machineApi';
import MachinePage from './MachinePage';

/**
 * The only test in the panel that assembles the whole screen, and the only one
 * that builds real `ApiProblem` instances: everything below the page mocks the
 * API module and speaks in plain shapes, but here the question is whether the
 * pieces fit, so the error that travels through them is the one httpClient
 * actually throws.
 */
vi.mock('../services/machineApi', () => ({
  getState: vi.fn(),
  insertCoin: vi.fn(),
  purchase: vi.fn(),
  returnCoins: vi.fn(),
  service: vi.fn(),
}));

const bag = (amount, coins = []) => ({ coins, amount });

const machine = {
  products: [
    { selector: 'WATER', name: 'Water', price: '0.65', count: 5 },
    { selector: 'JUICE', name: 'Orange juice', price: '1.00', count: 0 },
  ],
  changeReserve: bag('8.00'),
  insertedCoins: bag('0.00'),
  acceptedCoins: [
    { denomination: '0.05', dispensableAsChange: true },
    { denomination: '0.10', dispensableAsChange: true },
    { denomination: '0.25', dispensableAsChange: true },
    { denomination: '1.00', dispensableAsChange: false },
  ],
  supportedCoins: [
    { denomination: '0.05', dispensableAsChange: true, enabled: true },
    { denomination: '0.10', dispensableAsChange: true, enabled: true },
    { denomination: '0.25', dispensableAsChange: true, enabled: true },
    { denomination: '0.50', dispensableAsChange: true, enabled: false },
    { denomination: '1.00', dispensableAsChange: false, enabled: true },
    { denomination: '2.00', dispensableAsChange: false, enabled: false },
  ],
  exactChangeOnly: false,
  outOfService: false,
};

const withAQuarterIn = { ...machine, insertedCoins: bag('0.25', [{ denomination: '0.25', count: 1 }]) };

const problem = (status, code, extras = {}) =>
  ApiProblem.from({
    type: `/problems/${code.replaceAll('_', '-')}`,
    title: code,
    status,
    detail: 'Some English sentence that no client should ever read.',
    code,
    ...extras,
  });

const openPanel = async () => {
  getState.mockResolvedValue({ machine });
  render(<MachinePage />);

  await screen.findByRole('button', { name: /WATER/ });
};

afterEach(() => {
  vi.resetAllMocks();
});

describe('MachinePage', () => {
  it('shows the machine it found when the panel opens', async () => {
    await openPanel();

    expect(screen.getByRole('heading', { level: 1 })).toBeVisible();
    expect(screen.getByRole('status', { name: 'Display' })).toHaveTextContent('0.00');
    expect(screen.getByText('Change available')).toBeVisible();
    expect(screen.getByRole('button', { name: /JUICE/ })).toBeDisabled();
  });

  it('shows the amount the machine reports after a coin goes in', async () => {
    await openPanel();
    insertCoin.mockResolvedValue({ machine: withAQuarterIn });
    const user = userEvent.setup();

    await user.click(screen.getByRole('button', { name: '0.25' }));

    await waitFor(() =>
      expect(screen.getByRole('status', { name: 'Display' })).toHaveTextContent('0.25'),
    );
  });

  it('drops the can and its change into the tray', async () => {
    await openPanel();
    purchase.mockResolvedValue({
      dispensed: {
        selector: 'WATER',
        name: 'Water',
        price: '0.65',
        change: bag('0.35', [{ denomination: '0.25', count: 1 }, { denomination: '0.10', count: 1 }]),
      },
      machine,
    });
    const user = userEvent.setup();

    await user.click(screen.getByRole('button', { name: /WATER/ }));

    const tray = await screen.findByRole('status', { name: 'Dispense tray' });

    expect(tray).toHaveTextContent('Water');
    expect(tray).toHaveTextContent('0.35');
  });

  /**
   * The refund is the one flow whose two halves were each covered alone — the
   * button firing its callback, the hook shaping the tray — with nothing
   * checking they were joined. A button wired to the wrong action would have
   * passed both of those and failed only here.
   */
  it('drops the coins into the tray when the refund button is pressed', async () => {
    await openPanel();
    returnCoins.mockResolvedValue({
      returned: bag('0.50', [{ denomination: '0.25', count: 2 }]),
      machine,
    });
    const user = userEvent.setup();

    await user.click(screen.getByRole('button', { name: 'RETURN-COIN' }));

    const tray = await screen.findByRole('status', { name: 'Dispense tray' });

    await waitFor(() => expect(tray).toHaveTextContent('Returned 0.50'));

    expect(screen.getByText('2 × 0.25')).toBeVisible();
  });

  it('says how much is missing, with the figure the API put in the document', async () => {
    await openPanel();
    purchase.mockRejectedValue(problem(409, 'insufficient_funds', { missingAmount: '0.40' }));
    const user = userEvent.setup();

    await user.click(screen.getByRole('button', { name: /WATER/ }));

    expect(await screen.findByText('Insert 0.40 more')).toBeVisible();
  });

  /**
   * The edge case the whole domain is built around, seen from the panel. The
   * sale is refused, the coins stay in escrow, and the screen names the change
   * the machine could not compose.
   */
  it('refuses the sale and names the change it could not compose', async () => {
    await openPanel();
    purchase.mockRejectedValue(problem(409, 'exact_change_required', { changeDue: '0.35' }));
    const user = userEvent.setup();

    await user.click(screen.getByRole('button', { name: /WATER/ }));

    expect(await screen.findByText('No change for 0.35 — use exact change')).toBeVisible();
  });

  /**
   * The refusal no amount of care on this screen can prevent: someone wrote to
   * the machine between the state this panel is showing and this click. It is
   * optimistic locking answering 409 instead of overwriting silently, seen from
   * the side of whoever is standing at the machine — and it needs a real write
   * action, which is why it is here and not in the component's own suite.
   */
  it('says the machine is busy when someone else got there first', async () => {
    await openPanel();
    purchase.mockRejectedValue(problem(409, 'concurrent_modification'));
    const user = userEvent.setup();

    await user.click(screen.getByRole('button', { name: /WATER/ }));

    expect(await screen.findByText('Busy, try again')).toBeVisible();
  });

  it('lights the lamp when the machine says it can no longer give change', async () => {
    getState.mockResolvedValue({ machine: { ...machine, exactChangeOnly: true } });
    render(<MachinePage />);

    expect(await screen.findByText('Exact change only')).toBeVisible();
  });

  /**
   * There is no in-flight deduplication below this page (ADR-0016), so a second
   * press while the first is unanswered would be a second purchase. Locking the
   * controls is the whole mitigation, which makes this the test that guards it.
   */
  it('locks every control while an action is unanswered', async () => {
    await openPanel();
    purchase.mockReturnValue(new Promise(() => {}));
    const user = userEvent.setup();

    await user.click(screen.getByRole('button', { name: /WATER/ }));

    await waitFor(() => expect(screen.getByRole('button', { name: '0.25' })).toBeDisabled());

    expect(screen.getByRole('button', { name: /WATER/ })).toBeDisabled();
    expect(screen.getByRole('button', { name: 'RETURN-COIN' })).toBeDisabled();
  });

  /**
   * The technician's half of the panel, wired end to end: the door opens, the
   * form carries what the machine reported, and what comes back replaces the
   * state exactly as a purchase would.
   */
  it('services the machine from the drawer and shows what came back', async () => {
    await openPanel();
    const restocked = {
      ...machine,
      products: [{ selector: 'WATER', name: 'Water', price: '0.65', count: 20 }],
    };
    service.mockResolvedValue({ machine: restocked });
    const user = userEvent.setup();

    await user.click(screen.getByRole('button', { name: 'Service' }));
    await user.clear(screen.getByRole('spinbutton', { name: /WATER/ }));
    await user.type(screen.getByRole('spinbutton', { name: /WATER/ }), '20');
    await user.click(screen.getByRole('button', { name: 'Apply' }));

    expect(await screen.findByRole('button', { name: /WATER Water 0.65 20 left/ })).toBeVisible();
  });

  /**
   * The two coin lists are read by two different halves of this screen, and the
   * wiring is the only place that can cross them: the buttons show what the
   * machine takes, the drawer shows everything its acceptor can read. Crossed,
   * the panel would either offer the customer a coin the slot refuses or leave
   * the technician unable to switch one back on.
   */
  it('offers the customer what the machine takes and the technician everything it reads', async () => {
    await openPanel();
    const user = userEvent.setup();

    const slot = within(screen.getByRole('region', { name: 'Insert a coin' }));

    expect(slot.getAllByRole('button')).toHaveLength(4);

    await user.click(screen.getByRole('button', { name: 'Service' }));

    expect(screen.getAllByRole('checkbox')).toHaveLength(6);
  });

  /**
   * And the switch has to reach the API. This is the only test that follows one
   * from the technician's finger to the request body.
   */
  it('carries a denomination switched on in the drawer through to the machine', async () => {
    await openPanel();
    service.mockResolvedValue({ machine });
    const user = userEvent.setup();

    await user.click(screen.getByRole('button', { name: 'Service' }));
    await user.click(screen.getByRole('checkbox', { name: '0.50 — accepted' }));
    await user.click(screen.getByRole('button', { name: 'Apply' }));

    await waitFor(() => expect(service).toHaveBeenCalled());
    const [, , sentAcceptor] = service.mock.calls[0];

    expect(sentAcceptor).toEqual(['0.05', '0.10', '0.25', '0.50', '1.00']);
  });

  /**
   * The state a technician can leave behind, seen from the customer's side of
   * the glass. It arrives as one boolean the API computes; what it must not
   * look like is a machine with a coin slot that has simply gone blank.
   */
  describe('when the acceptor has been switched off', () => {
    /**
     * The empty escrow is the state the API answers with after the visit that
     * switched the acceptor off — this fixture copies it rather than choosing
     * it. That it is the *only* reachable one is a promise of the aggregate,
     * kept and proved on the other side of the network
     * (`VendingMachineServiceTest::test_servicing_returns_money_a_customer_had_inserted`
     * and the coin-acceptor tests beside it), not something this suite can
     * demonstrate or would notice breaking.
     */
    const offMachine = {
      ...machine,
      insertedCoins: bag('0.00'),
      acceptedCoins: [],
      supportedCoins: machine.supportedCoins.map((coin) => ({ ...coin, enabled: false })),
      exactChangeOnly: true,
      outOfService: true,
    };

    const openTheOffPanel = async () => {
      getState.mockResolvedValue({ machine: offMachine });
      render(<MachinePage />);

      await screen.findByRole('button', { name: /WATER/ });
    };

    it('says so on the display instead of leaving an empty coin slot', async () => {
      await openTheOffPanel();

      expect(screen.getByRole('status', { name: 'Display' })).toHaveTextContent('Out of service');
      expect(within(screen.getByRole('region', { name: 'Insert a coin' })).queryAllByRole('button')).toHaveLength(0);
    });

    /**
     * The lamp is lit in the state the API reports — a till it may not pay out
     * of cannot give change — but it would be answering a question nobody
     * asked, and pointing at the wrong cause while it did. Two notices
     * competing is how the customer reads the one that matters least.
     */
    it('says nothing about change, which is true and not the point', async () => {
      await openTheOffPanel();

      expect(screen.queryByText('Exact change only')).toBeNull();
      expect(screen.queryByText('Change available')).toBeNull();
    });

    /**
     * Nothing here can work, so nothing here pretends to. Left live, a product
     * button answers "Insert 0.65 more" — advice nobody can follow at a slot
     * that takes no coin, which is worse than a control that visibly does not
     * apply.
     */
    it('refuses to pretend anything can be bought', async () => {
      await openTheOffPanel();

      expect(screen.getByRole('button', { name: /WATER/ })).toBeDisabled();
      expect(screen.getByRole('button', { name: 'RETURN-COIN' })).toBeDisabled();
    });

    /**
     * And the one control that must survive it: the machine is switched back on
     * through this door, so locking the cabinet would lock in the state that
     * made it look locked.
     */
    it('leaves the technician a way back in', async () => {
      const user = userEvent.setup();
      await openTheOffPanel();

      await user.click(screen.getByRole('button', { name: 'Service' }));

      expect(screen.getByRole('dialog', { name: 'Service' })).toBeVisible();
      expect(screen.getByRole('checkbox', { name: '0.25 — accepted' })).toBeEnabled();
    });
  });

  /**
   * `field` is an extension the API adds so a client can say which box is
   * wrong instead of relaying a sentence. Reading it is the whole reason it is
   * in the document.
   */
  it('names the field the machine refused, rather than relaying its prose', async () => {
    await openPanel();
    service.mockRejectedValue(
      problem(422, 'invalid_request_payload', { field: 'products[0].count' }),
    );
    const user = userEvent.setup();

    await user.click(screen.getByRole('button', { name: 'Service' }));
    await user.click(screen.getByRole('button', { name: 'Apply' }));

    expect(await screen.findByText('Invalid: products[0].count')).toBeVisible();
  });

  it('says the machine is unreachable rather than blaming the customer', async () => {
    getState.mockRejectedValue(new Error('the machine could not be reached'));
    render(<MachinePage />);

    expect(await screen.findByText('Machine unreachable')).toBeVisible();
  });
});
