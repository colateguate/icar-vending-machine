import { ApiProblem, isProblemDocument } from './problemDetails';

/**
 * The only module in the project that calls `fetch`.
 *
 * The base is relative by default, and that is a decision rather than a
 * default: the panel never learns where the API lives. In development the Vite
 * dev server answers for `/api`, in production the reverse proxy does, and the
 * same built bundle works behind either. An absolute URL would have to be baked
 * in at build time — VITE_* variables are frozen into the bundle — which means
 * one artifact per environment. The override exists for the deployment that
 * genuinely serves the API from somewhere else.
 */
const base = import.meta.env.VITE_API_URL ?? '/api';

/**
 * We never got an answer in the contract's terms: the request did not complete,
 * or something answered that was not this API. Kept apart from ApiProblem
 * because the two mean different things to whoever is standing at the machine —
 * one is "it said no", the other is "it did not say anything". A panel that
 * blurred them would tell someone their coin was refused when the network was
 * simply down.
 */
export class TransportFailure extends Error {
  constructor(message, options) {
    super(message, options);

    this.name = 'TransportFailure';
  }
}

async function decode(response) {
  try {
    return await response.json();
  } catch {
    return undefined;
  }
}

/**
 * What varies between calls beyond the verb and the path goes in one bag, so a
 * caller that wants only a signal does not have to name an absent body to reach
 * it. `request('GET', '/machine', undefined, signal)` was the alternative, and
 * the `undefined` in it means nothing except "count the arguments".
 */
export async function request(method, path, { body, signal } = {}) {
  const url = `${base}${path}`;
  const hasBody = body !== undefined;

  let response;

  try {
    response = await fetch(url, {
      method,
      headers: hasBody ? { 'Content-Type': 'application/json' } : {},
      body: hasBody ? JSON.stringify(body) : undefined,
      signal,
    });
  } catch (cause) {
    // An abort is not a failure. Nobody was unreachable; we withdrew the
    // question. Wrapping it would hand the caller a TransportFailure saying the
    // machine could not be reached — untrue, and the kind of untruth that ends
    // up on a screen in front of someone.
    if (signal?.aborted) {
      throw cause;
    }

    throw new TransportFailure(`The machine could not be reached (${method} ${url}).`, { cause });
  }

  const payload = await decode(response);

  if (response.ok) {
    if (payload === undefined) {
      throw new TransportFailure(`The machine answered ${method} ${url} with something that is not JSON.`);
    }

    return payload;
  }

  if (isProblemDocument(payload)) {
    throw ApiProblem.from(payload);
  }

  throw new TransportFailure(
    `The machine answered ${response.status} to ${method} ${url} without a problem document.`,
  );
}
