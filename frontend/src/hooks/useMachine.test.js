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
