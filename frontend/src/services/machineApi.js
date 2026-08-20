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
 */
export function getState() {
  return request('GET', '/machine');
}

export function insertCoin(coin) {
  return request('POST', '/machine/coins', { coin });
}

/** The RETURN-COIN button. */
export function returnCoins() {
  return request('POST', '/machine/coins/return');
}

export function purchase(selector) {
  return request('POST', '/machine/purchases', { selector });
}

/** A service visit sets what the machine stocks and holds, so it sends both. */
export function service(products, changeReserve) {
  return request('PUT', '/machine/service', { products, changeReserve });
}
