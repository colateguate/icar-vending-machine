import { useCallback, useEffect, useState } from 'react';

import { getState, insertCoin, purchase, returnCoins, service } from '../services/machineApi';

/**
 * The only module above `services/` that talks to the API, and therefore the
 * only place in the panel that knows the machine is remote at all. Everything
 * else receives props and emits callbacks.
 *
 * There is no refetch after an action anywhere in here, and that is the whole
 * reason this panel needs no data-fetching library: every writing endpoint
 * answers with the machine's full state, so the response of an action *is* the
 * new state and it is stored as it arrives. There is no cache to invalidate
 * because there is nothing to invalidate it against (ADR-0016).
 *
 * What that costs is stated in the same ADR: no in-flight deduplication. `busy`
 * is the mitigation — the panel locks its controls while a request is
 * unanswered, which is the only thing standing between one double click and two
 * purchases.
 */
export function useMachine() {
  const [machine, setMachine] = useState(null);
  const [error, setError] = useState(null);
  const [tray, setTray] = useState(null);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);

  /**
   * The panel can be closed before the machine answers, so the read is dropped
   * rather than merely ignored. The signal is also what makes the cleanup
   * checkable from outside: a flag closed over by this effect would be correct
   * and invisible, and a guard no test can fail is a guard nobody is watching.
   *
   * The two `aborted` checks are not defensive noise. StrictMode runs every
   * effect twice in development — mount, cleanup, mount — so this aborts its own
   * first request while the panel is still on screen, and the rejection that
   * comes back is ours. Reporting it would announce an unreachable machine on
   * every page load.
   */
  useEffect(() => {
    const controller = new AbortController();

    getState(controller.signal)
      .then(({ machine: found }) => {
        setMachine(found);
      })
      .catch((failure) => {
        if (!controller.signal.aborted) {
          setError(failure);
        }
      })
      .finally(() => {
        if (!controller.signal.aborted) {
          setLoading(false);
        }
      });

    return () => controller.abort();
  }, []);

  /**
   * Every action goes through here, so the three things that are true of all of
   * them are stated once: the panel is busy while it waits, a refusal is kept
   * without disturbing the state already held — a rejected sale changes nothing
   * physical, so what is on screen is still true — and anything that works
   * clears the last refusal.
   */
  const run = useCallback(async (call) => {
    setBusy(true);

    try {
      const answer = await call();

      setMachine(answer.machine);
      setError(null);

      return answer;
    } catch (failure) {
      setError(failure);

      return null;
    } finally {
      setBusy(false);
    }
  }, []);

  const insert = useCallback(
    async (coin) => {
      await run(() => insertCoin(coin));
    },
    [run],
  );

  const buy = useCallback(
    async (selector) => {
      const answer = await run(() => purchase(selector));

      if (answer) {
        setTray({ kind: 'purchase', dispensed: answer.dispensed });
      }
    },
    [run],
  );

  const refund = useCallback(async () => {
    const answer = await run(() => returnCoins());

    if (answer) {
      setTray({ kind: 'return', returned: answer.returned });
    }
  }, [run]);

  // Nothing physically leaves the machine on a service visit, so unlike the
  // other two writing actions this one has no tray to fill.
  const visit = useCallback(
    async (products, changeReserve, acceptedCoins) => {
      await run(() => service(products, changeReserve, acceptedCoins));
    },
    [run],
  );

  return {
    machine,
    error,
    tray,
    loading,
    busy,
    insertCoin: insert,
    purchase: buy,
    returnCoins: refund,
    service: visit,
  };
}
