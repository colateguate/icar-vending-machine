import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { ApiProblem } from './problemDetails';
import { TransportFailure, request } from './httpClient';

/**
 * This is the one file in the project allowed to mock `fetch`, because it is
 * the one module that calls it. Everything above this layer mocks the module
 * instead — mocking the transport from a component test would tie that test to
 * headers and status codes it has no business knowing.
 *
 * Responses are real `Response` objects rather than hand-rolled shapes with a
 * `json()` on them: a fake that is easier to satisfy than the real thing is a
 * fake that lets a bug through.
 */
const jsonResponse = (status, body, contentType = 'application/json') =>
  new Response(JSON.stringify(body), { status, headers: { 'content-type': contentType } });

const machineState = {
  machine: {
    products: [{ selector: 'WATER', name: 'Water', price: '0.65', count: 10 }],
    changeReserve: { coins: [], amount: '8.00' },
    insertedCoins: { coins: [], amount: '0.00' },
    exactChangeOnly: false,
  },
};

const insufficientFunds = {
  type: '/problems/insufficient-funds',
  title: 'Insufficient funds',
  status: 409,
  detail: 'Another 0.75 is needed before this product can be dispensed.',
  code: 'insufficient_funds',
  missingAmount: '0.75',
};

describe('request', () => {
  beforeEach(() => {
    vi.stubGlobal('fetch', vi.fn());
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    vi.resetAllMocks();
  });

  describe('when the machine answers', () => {
    it('returns the decoded body of a successful response', async () => {
      fetch.mockResolvedValue(jsonResponse(200, machineState));

      await expect(request('GET', '/machine')).resolves.toEqual(machineState);
    });

    // Why the base is relative is argued once, in the module's own docblock.
    it('asks for the path under the relative /api base', async () => {
      fetch.mockResolvedValue(jsonResponse(200, machineState));

      await request('GET', '/machine');

      expect(fetch).toHaveBeenCalledWith('/api/machine', expect.anything());
    });

    it('sends a body as JSON and says so in the headers', async () => {
      fetch.mockResolvedValue(jsonResponse(200, machineState));

      await request('POST', '/machine/coins', { body: { coin: '0.25' } });

      const [, init] = fetch.mock.calls[0];
      expect(init.method).toBe('POST');
      expect(init.headers['Content-Type']).toBe('application/json');
      expect(JSON.parse(init.body)).toEqual({ coin: '0.25' });
    });

    it('sends no body at all when there is nothing to send', async () => {
      fetch.mockResolvedValue(jsonResponse(200, machineState));

      await request('POST', '/machine/coins/return');

      const [, init] = fetch.mock.calls[0];
      expect(init.body).toBeUndefined();
    });

    /**
     * Money crosses this boundary as a decimal string and is not touched. The
     * assertion is on the string, not on a number parsed from it, because
     * converting in the test would legitimise converting in the code.
     */
    it('hands amounts back as the strings they arrived as', async () => {
      fetch.mockResolvedValue(jsonResponse(200, machineState));

      const body = await request('GET', '/machine');

      expect(body.machine.changeReserve.amount).toBe('8.00');
      expect(body.machine.products[0].price).toBe('0.65');
    });
  });

  describe('when the caller withdraws the question', () => {
    it('hands the signal straight to fetch, so the request can actually be dropped', async () => {
      fetch.mockResolvedValue(jsonResponse(200, machineState));
      const { signal } = new AbortController();

      await request('GET', '/machine', { signal });

      const [, init] = fetch.mock.calls[0];
      expect(init.signal).toBe(signal);
    });

    /**
     * An abort is not a failure: nobody was unreachable, we changed our mind.
     * Wrapping it in TransportFailure would tell the caller the machine could
     * not be reached, which is both untrue and the sort of untruth that ends up
     * on screen.
     */
    it('lets an abort through as itself rather than dressing it as a transport failure', async () => {
      const controller = new AbortController();
      const aborted = new DOMException('This operation was aborted', 'AbortError');
      controller.abort();
      fetch.mockRejectedValue(aborted);

      const thrown = await request('GET', '/machine', { signal: controller.signal }).catch(
        (error) => error,
      );

      expect(thrown).toBe(aborted);
      expect(thrown).not.toBeInstanceOf(TransportFailure);
    });
  });

  describe('when the machine refuses', () => {
    it('throws the problem the API described, with its code and extensions', async () => {
      fetch.mockResolvedValue(jsonResponse(409, insufficientFunds, 'application/problem+json'));

      const thrown = await request('POST', '/machine/purchases', { body: { selector: 'JUICE' } }).catch(
        (error) => error,
      );

      expect(thrown).toBeInstanceOf(ApiProblem);
      expect(thrown.code).toBe('insufficient_funds');
      expect(thrown.status).toBe(409);
      expect(thrown.extensions).toEqual({ missingAmount: '0.75' });
    });
  });

  describe('when there is no contractual answer at all', () => {
    it('throws a transport failure when the request never completes', async () => {
      fetch.mockRejectedValue(new TypeError('Failed to fetch'));

      await expect(request('GET', '/machine')).rejects.toBeInstanceOf(TransportFailure);
    });

    /**
     * A gateway that returns its own HTML error page is not the machine saying
     * no — it is the machine not being reached. Telling the two apart is the
     * whole reason this module throws two different things: one becomes a
     * message about the purchase, the other a message about the connection.
     */
    it('throws a transport failure when an error response is not a problem document', async () => {
      fetch.mockResolvedValue(
        new Response('<html>502 Bad Gateway</html>', {
          status: 502,
          headers: { 'content-type': 'text/html' },
        }),
      );

      await expect(request('GET', '/machine')).rejects.toBeInstanceOf(TransportFailure);
    });

    it('throws a transport failure when a successful response is not JSON', async () => {
      fetch.mockResolvedValue(
        new Response('not json', { status: 200, headers: { 'content-type': 'text/plain' } }),
      );

      await expect(request('GET', '/machine')).rejects.toBeInstanceOf(TransportFailure);
    });

    it('keeps the original failure as the cause, so it is not lost', async () => {
      const networkError = new TypeError('Failed to fetch');
      fetch.mockRejectedValue(networkError);

      const thrown = await request('GET', '/machine').catch((error) => error);

      expect(thrown.cause).toBe(networkError);
    });
  });
});
