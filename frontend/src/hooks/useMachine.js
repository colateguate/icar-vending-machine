import { useCallback, useEffect, useState } from 'react';

import { getState, insertCoin, purchase, returnCoins } from '../services/machineApi';

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

  useEffect(() => {
    let ignore = false;

    getState()
      .then(({ machine: found }) => {
        if (!ignore) {
          setMachine(found);
        }
      })
      .catch((failure) => {
        if (!ignore) {
          setError(failure);
        }
      })
      .finally(() => {
        if (!ignore) {
          setLoading(false);
        }
      });

    // The panel can be closed before the machine answers. Nothing here can be
    // cancelled — the API client takes no abort signal — so the guard is a flag
    // that makes the late answer arrive to nobody.
    return () => {
      ignore = true;
    };
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

  return {
    machine,
    error,
    tray,
    loading,
    busy,
    insertCoin: insert,
    purchase: buy,
    returnCoins: refund,
  };
}
