import { render, screen, waitFor } from '@testing-library/react';
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
  exactChangeOnly: false,
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
