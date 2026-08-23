import { request } from './httpClient';

/**
 * One function per endpoint of the published contract, and nothing else. No
 * caching, no retries, no state: what this module knows is which verb, which
 * path and which body, which is exactly what `docs/openapi.yaml` says.
 *
 * Every writing call answers with the machine's full state, so callers do not
 * need to read it back afterwards — the response of an action *is* the new
 * state. That property of the contract is why this client carries no
 * data-fetching library (ADR-0016).
 *
 * Amounts are decimal strings on the way in and on the way out. Nothing here
 * converts one to a number, because JavaScript only offers the float that
 * ADR-0004 refuses.
 *
 * Only the read is cancellable, and that asymmetry is deliberate. Dropping a
 * question you no longer need the answer to is free; dropping a request that
 * changes the machine is not, because an aborted purchase leaves the caller
 * unable to say whether a can came out. The four writing calls therefore take
 * no signal, and there is nothing to pass them one with.
 */
export function getState(signal) {
  return request('GET', '/machine', { signal });
}

export function insertCoin(coin) {
  return request('POST', '/machine/coins', { body: { coin } });
}

/** The RETURN-COIN button. */
export function returnCoins() {
  return request('POST', '/machine/coins/return');
}

export function purchase(selector) {
  return request('POST', '/machine/purchases', { body: { selector } });
}

/**
 * A service visit sets what the machine stocks, what it holds and which coins it
 * takes, so it sends all three. `acceptedCoins` is optional in the contract and
 * this client always states it: absent means "leave the acceptor as it was", and
 * an empty list means "take nothing at all", which is a machine out of service.
 * A caller with a form in front of it knows which of the two it means.
 */
export function service(products, changeReserve, acceptedCoins) {
  return request('PUT', '/machine/service', { body: { products, changeReserve, acceptedCoins } });
}
