import { describe, expect, it } from 'vitest';

import { ApiProblem, isProblemDocument } from './problemDetails';

/**
 * Fixtures are the documents the API actually answers with. They were captured
 * from the running backend, and since ticket 14g the published examples in
 * docs/openapi.yaml are executed against real responses, so the spec and these
 * agree by construction rather than by somebody remembering to sync them.
 */
const insufficientFunds = {
  type: '/problems/insufficient-funds',
  title: 'Insufficient funds',
  status: 409,
  detail: 'Another 0.75 is needed before this product can be dispensed.',
  code: 'insufficient_funds',
  missingAmount: '0.75',
};

const unknownProduct = {
  type: '/problems/unknown-product',
  title: 'Unknown product',
  status: 404,
  detail: 'This machine does not stock any product under the selector "NOPE".',
  code: 'unknown_product',
};

describe('isProblemDocument', () => {
  it('accepts a document carrying the five members the contract requires', () => {
    expect(isProblemDocument(unknownProduct)).toBe(true);
  });

  it.each([
    ['null', null],
    ['a string', 'insufficient_funds'],
    ['an array', [unknownProduct]],
    ['an object with no code', { type: '/x', title: 'X', status: 409, detail: 'x' }],
    ['an object whose status is not a number', { ...unknownProduct, status: '404' }],
    ['a successful response body', { machine: { products: [] } }],
  ])('refuses %s', (_label, value) => {
    expect(isProblemDocument(value)).toBe(false);
  });
});

describe('ApiProblem', () => {
  it('keeps the five required members where a caller can reach them', () => {
    const problem = ApiProblem.from(unknownProduct);

    expect(problem).toMatchObject({
      type: '/problems/unknown-product',
      title: 'Unknown product',
      status: 404,
      detail: 'This machine does not stock any product under the selector "NOPE".',
      code: 'unknown_product',
    });
  });

  it('is an Error, so it can be thrown and caught like one', () => {
    const problem = ApiProblem.from(unknownProduct);

    expect(problem).toBeInstanceOf(Error);
    expect(problem.name).toBe('ApiProblem');
  });

  /**
   * The extensions are where the numbers live. A panel that had to read "0.75"
   * out of the English sentence would break the first time the sentence is
   * reworded, which is exactly what ADR-0012 put them in the document to avoid.
   */
  it('exposes the extensions that only some problems carry', () => {
    const problem = ApiProblem.from(insufficientFunds);

    expect(problem.extensions).toEqual({ missingAmount: '0.75' });
  });

  it('gives an empty extensions object when the problem carries none', () => {
    expect(ApiProblem.from(unknownProduct).extensions).toEqual({});
  });

  /**
   * The amount arrives as a decimal string and stays one. Reading it as a
   * number here would be the same mistake the backend refuses to make, and it
   * would be invisible until a total came out as 0.30000000000000004.
   */
  it('leaves money in the extensions as the string it arrived as', () => {
    const problem = ApiProblem.from(insufficientFunds);

    expect(problem.extensions.missingAmount).toBe('0.75');
    expect(typeof problem.extensions.missingAmount).toBe('string');
  });

  it('uses the detail as the Error message, so an unhandled one still says something', () => {
    expect(ApiProblem.from(unknownProduct).message).toBe(unknownProduct.detail);
  });
});
