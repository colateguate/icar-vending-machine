import { StrictMode } from 'react';
import { act, renderHook, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { getState, insertCoin, purchase, returnCoins } from '../services/machineApi';
import { useMachine } from './useMachine';

/**
 * The seam is the API module, the same one the component tests mock. Nothing in
 * the panel above `services/` has any business knowing that a fetch exists.
 */
vi.mock('../services/machineApi', () => ({
  getState: vi.fn(),
  insertCoin: vi.fn(),
  purchase: vi.fn(),
  returnCoins: vi.fn(),
}));

const bag = (amount, coins = []) => ({ coins, amount });

const idle = {
  products: [{ selector: 'WATER', name: 'Water', price: '0.65', count: 5 }],
  changeReserve: bag('8.00'),
  insertedCoins: bag('0.00'),
  exactChangeOnly: false,
};

const withAQuarterIn = {
  ...idle,
  insertedCoins: bag('0.25', [{ denomination: '0.25', count: 1 }]),
};

const refusal = (code, extensions = {}) => Object.assign(new Error(code), { code, extensions });

/**
 * What a real request does when its signal is pulled: it rejects, rather than
 * quietly never settling. `mockResolvedValue` cannot express that, and the
 * difference is the whole point of the cancellation tests.
 *
 * The microtask rejects rather than merely declining to resolve, so a signal
 * that was already aborted before the call still settles. Nothing here passes
 * one — but a fake that hangs where the real thing rejects fails by timeout
 * instead of by assertion, and a fake easier to satisfy than reality is a fake
 * that lets something through.
 */
const aborted = () => new DOMException('This operation was aborted', 'AbortError');

const answersUnlessAborted = (payload) => (signal) =>
  new Promise((resolve, reject) => {
    signal.addEventListener('abort', () => reject(aborted()));

    queueMicrotask(() => {
      if (signal.aborted) {
        reject(aborted());

        return;
      }

      resolve(payload);
    });
  });

const loaded = async () => {
  getState.mockResolvedValue({ machine: idle });

  const rendered = renderHook(() => useMachine());

  await waitFor(() => expect(rendered.result.current.loading).toBe(false));

  return rendered;
};

afterEach(() => {
  vi.resetAllMocks();
});

describe('useMachine', () => {
  it('reads the machine once, when the panel opens', async () => {
    const { result } = await loaded();

    expect(result.current.machine).toEqual(idle);
    expect(getState).toHaveBeenCalledTimes(1);
  });

  /**
   * The test that could not be written before the read became cancellable. The
   * old guard was a flag closed over by the effect: correct, invisible, and
   * impossible to fail. A signal is something the caller handed in, so the
   * caller can ask afterwards what became of it.
   */
  it('withdraws the question when the panel closes', async () => {
    const { unmount } = await loaded();
    const [signal] = getState.mock.calls[0];

    expect(signal.aborted).toBe(false);

    unmount();

    expect(signal.aborted).toBe(true);
  });

  /**
   * StrictMode runs every effect twice in development — mount, cleanup, mount —
   * so the panel aborts its own first request while it is still on screen and
   * gets the rejection back with nobody unmounted to absorb it. Reporting that
   * would put "Machine unreachable" on a machine that answered perfectly well,
   * on every single page load in development.
   */
  it('does not report a failure when it was the one who cancelled', async () => {
    getState.mockImplementation(answersUnlessAborted({ machine: idle }));

    const { result } = renderHook(() => useMachine(), { wrapper: StrictMode });

    await waitFor(() => expect(result.current.loading).toBe(false));

    expect(result.current.error).toBeNull();
    expect(result.current.machine).toEqual(idle);
  });

  it('surfaces the refusal when there is no machine to show', async () => {
    getState.mockRejectedValue(refusal('machine_not_provisioned'));

    const { result } = renderHook(() => useMachine());

    await waitFor(() => expect(result.current.loading).toBe(false));

    expect(result.current.error).toMatchObject({ code: 'machine_not_provisioned' });
    expect(result.current.machine).toBeNull();
  });

  /**
   * The whole reason this panel carries no data-fetching library: every writing
   * endpoint answers with the machine's full state, so the response of an action
   * *is* the new state. Counting the reads is what makes that claim checkable —
   * a hook that quietly refetched would still show the right screen.
   */
  it('takes the new state from the action itself and never reads the machine again', async () => {
    const { result } = await loaded();
    insertCoin.mockResolvedValue({ machine: withAQuarterIn });

    await act(async () => {
      await result.current.insertCoin('0.25');
    });

    expect(insertCoin).toHaveBeenCalledWith('0.25');
    expect(result.current.machine).toEqual(withAQuarterIn);
    expect(getState).toHaveBeenCalledTimes(1);
  });

  it('drops the goods and the change into the tray after a purchase', async () => {
    const { result } = await loaded();
    const dispensed = { selector: 'WATER', name: 'Water', price: '0.65', change: bag('0.35') };
    purchase.mockResolvedValue({ dispensed, machine: idle });

    await act(async () => {
      await result.current.purchase('WATER');
    });

    expect(result.current.tray).toEqual({ kind: 'purchase', dispensed });
  });

  it('drops the coins into the tray when they are asked for back', async () => {
    const { result } = await loaded();
    const returned = bag('0.25', [{ denomination: '0.25', count: 1 }]);
    returnCoins.mockResolvedValue({ returned, machine: idle });

    await act(async () => {
      await result.current.returnCoins();
    });

    expect(result.current.tray).toEqual({ kind: 'return', returned });
  });

  /**
   * A refused sale changes nothing physical: the coins stay in escrow and the
   * can stays in the slot. The hook has to leave the state it holds alone,
   * because the state it holds is still true.
   */
  it('keeps the machine it already had when an action is refused', async () => {
    const { result } = await loaded();
    purchase.mockRejectedValue(refusal('insufficient_funds', { missingAmount: '0.40' }));

    await act(async () => {
      await result.current.purchase('WATER');
    });

    expect(result.current.error).toMatchObject({ code: 'insufficient_funds' });
    expect(result.current.machine).toEqual(idle);
  });

  it('clears the last refusal as soon as something works', async () => {
    const { result } = await loaded();
    purchase.mockRejectedValue(refusal('insufficient_funds'));
    insertCoin.mockResolvedValue({ machine: withAQuarterIn });

    await act(async () => {
      await result.current.purchase('WATER');
    });
    await act(async () => {
      await result.current.insertCoin('0.25');
    });

    expect(result.current.error).toBeNull();
  });

  it('reports itself busy for exactly as long as the request is in the air', async () => {
    const { result } = await loaded();
    let answer;
    insertCoin.mockReturnValue(
      new Promise((resolve) => {
        answer = resolve;
      }),
    );

    let pending;
    act(() => {
      pending = result.current.insertCoin('0.25');
    });

    expect(result.current.busy).toBe(true);

    await act(async () => {
      answer({ machine: withAQuarterIn });
      await pending;
    });

    expect(result.current.busy).toBe(false);
  });
});
