import { afterEach, describe, expect, it, vi } from 'vitest';

import { ApiProblem } from './problemDetails';
import { request } from './httpClient';
import { getState, insertCoin, purchase, returnCoins, service } from './machineApi';

/**
 * The seam here is httpClient, not fetch. What this module knows is which verb,
 * which path and which body — so those three are what the assertions read, and
 * a path with one slash too many is caught by the very first argument. That
 * `/api` and `/machine` concatenate correctly is httpClient's promise and lives
 * in its own test; asserting it again from here would only tie these tests to a
 * neighbour's implementation, so that a header added to httpClient would break
 * five tests about a module that had not changed.
 *
 * Every function gets both faces, the answer and the refusal, because a client
 * that only works when the machine says yes is not finished.
 */
vi.mock('./httpClient', () => ({ request: vi.fn() }));

const problem = (status, code, extras = {}) =>
  ApiProblem.from({
    type: `/problems/${code.replaceAll('_', '-')}`,
    title: code,
    status,
    detail: 'The machine refused.',
    code,
    ...extras,
  });

const machineState = { machine: { products: [], changeReserve: { coins: [], amount: '0.00' } } };

afterEach(() => {
  vi.resetAllMocks();
});

describe('getState', () => {
  it('reads the machine', async () => {
    request.mockResolvedValue(machineState);

    await expect(getState()).resolves.toEqual(machineState);
    expect(request).toHaveBeenCalledWith('GET', '/machine');
  });

  it('surfaces the problem when no machine has been provisioned', async () => {
    request.mockRejectedValue(problem(503, 'machine_not_provisioned'));

    await expect(getState()).rejects.toMatchObject({ code: 'machine_not_provisioned', status: 503 });
  });
});

describe('insertCoin', () => {
  it('sends the coin as the decimal string it is', async () => {
    request.mockResolvedValue(machineState);

    await insertCoin('0.25');

    expect(request).toHaveBeenCalledWith('POST', '/machine/coins', { coin: '0.25' });
  });

  it('surfaces the problem when the machine does not take that coin', async () => {
    request.mockRejectedValue(problem(422, 'unsupported_coin'));

    await expect(insertCoin('0.03')).rejects.toMatchObject({ code: 'unsupported_coin' });
  });
});

describe('returnCoins', () => {
  it('presses the button, which takes no argument', async () => {
    request.mockResolvedValue({ returned: { coins: [], amount: '0.00' } });

    await returnCoins();

    expect(request).toHaveBeenCalledWith('POST', '/machine/coins/return');
  });

  it('surfaces the problem when the machine changed underneath the request', async () => {
    request.mockRejectedValue(problem(409, 'concurrent_modification'));

    await expect(returnCoins()).rejects.toMatchObject({ code: 'concurrent_modification' });
  });
});

describe('purchase', () => {
  it('names the selector it wants', async () => {
    request.mockResolvedValue(machineState);

    await purchase('JUICE');

    expect(request).toHaveBeenCalledWith('POST', '/machine/purchases', { selector: 'JUICE' });
  });

  /**
   * The edge case the whole domain is built around, seen from the client: the
   * refusal arrives with the amount it could not compose, as a string, in an
   * extension rather than buried in the sentence.
   */
  it('surfaces exact-change-required with the change it could not compose', async () => {
    request.mockRejectedValue(problem(409, 'exact_change_required', { changeDue: '0.30' }));

    const thrown = await purchase('WATER').catch((error) => error);

    expect(thrown).toBeInstanceOf(ApiProblem);
    expect(thrown.code).toBe('exact_change_required');
    expect(thrown.extensions.changeDue).toBe('0.30');
  });
});

describe('service', () => {
  it('sends the whole shelf and the whole till, because a service visit sets both', async () => {
    request.mockResolvedValue(machineState);
    const products = [{ selector: 'TEA', name: 'Iced Tea', price: '0.80', count: 4 }];
    const changeReserve = [{ denomination: '0.25', count: 2 }];

    await service(products, changeReserve);

    expect(request).toHaveBeenCalledWith('PUT', '/machine/service', { products, changeReserve });
  });

  it('surfaces the field at fault when the payload is not valid input', async () => {
    request.mockRejectedValue(problem(422, 'invalid_request_payload', { field: 'products[0].count' }));

    const thrown = await service([], []).catch((error) => error);

    expect(thrown.code).toBe('invalid_request_payload');
    expect(thrown.extensions.field).toBe('products[0].count');
  });
});
