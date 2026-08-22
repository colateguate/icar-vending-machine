import { describe, expect, it } from 'vitest';

import { problemsWith } from './productRules';

/**
 * The rules the form checks before it sends anything, tested without rendering
 * a thing. They are a mirror of what the API already refuses — the 422 stays
 * the authority — so what these tests are really pinning is that the mirror is
 * neither looser nor **stricter** than the original. Stricter is the dangerous
 * direction: a client that refuses what the machine accepts makes the machine
 * look broken.
 */
const row = (fields = {}) => ({
  id: 'new-1',
  selector: 'TEA',
  name: 'Iced Tea',
  price: '0.80',
  count: '4',
  isNew: true,
  ...fields,
});

describe('problemsWith', () => {
  it('says nothing about a shelf it has no quarrel with', () => {
    expect(problemsWith([row(), row({ id: 'WATER', selector: 'WATER', isNew: false })])).toEqual({});
  });

  describe('the selector', () => {
    it.each(['TEA', 'A', 'SPARKLING_WATER', 'COLA-ZERO', 'X1'])('accepts %s', (selector) => {
      expect(problemsWith([row({ selector })])).toEqual({});
    });

    /**
     * Each of these is refused by `ProductSelector` in the domain, and the
     * point of listing them here is that the panel refuses exactly the same
     * set — no more.
     */
    it.each([
      ['lowercase', 'tea'],
      ['a space in it', 'ICED TEA'],
      ['starting with a digit', '1UP'],
      ['a character the format does not allow', 'TEA!'],
      ['nothing at all', ''],
      ['longer than the format allows', 'A'.repeat(33)],
    ])('refuses one with %s', (_why, selector) => {
      expect(problemsWith([row({ selector })])['new-1'].selector).toEqual(expect.any(String));
    });

    it('accepts one exactly as long as the format allows', () => {
      expect(problemsWith([row({ selector: `A${'B'.repeat(31)}` })])).toEqual({});
    });

    /**
     * A selector already on the shelf is the machine's own word for a product
     * and is not editable, so checking it would be checking the machine rather
     * than the technician — and a machine stocked before a rule changed would
     * find its form unusable.
     */
    it('leaves the selector of a product already on the shelf alone', () => {
      expect(problemsWith([row({ id: 'tea', selector: 'tea', isNew: false })])).toEqual({});
    });
  });

  describe('the price', () => {
    it.each(['0.65', '1.00', '1', '0.8', '0', '12.34'])('accepts %s', (price) => {
      expect(problemsWith([row({ price })])).toEqual({});
    });

    /**
     * `0` and `1` are in the list above on purpose. The domain's own format
     * accepts them, and a mirror that demanded two decimals would refuse a
     * price the machine is perfectly happy with.
     */
    it.each([
      ['three decimals', '0.653'],
      ['a comma', '1,50'],
      ['words', 'free'],
      ['a negative', '-1.00'],
      ['nothing at all', ''],
      ['scientific notation', '1e3'],
    ])('refuses %s', (_why, price) => {
      expect(problemsWith([row({ price })])['new-1'].price).toEqual(expect.any(String));
    });

    it('checks the price of every row, not only the new ones', () => {
      const problems = problemsWith([row({ id: 'WATER', selector: 'WATER', price: 'free', isNew: false })]);

      expect(problems.WATER.price).toEqual(expect.any(String));
    });
  });

  /**
   * These two only became reachable when blank rows did. Every field of a row
   * seeded from the machine arrives filled, so before there was an "add
   * product" button there was no way to submit an empty one — which is why the
   * form used to lean on `required` and now does not: native validation
   * refuses the submit before this runs, with a bubble no screen reader
   * announces and no test at this level can read.
   */
  describe('the rest of the row', () => {
    it('wants a name, because it is what the customer reads', () => {
      expect(problemsWith([row({ name: '' })])['new-1'].name).toEqual(expect.any(String));
      expect(problemsWith([row({ name: '   ' })])['new-1'].name).toEqual(expect.any(String));
    });

    it.each([
      ['nothing at all', ''],
      ['a fraction of a bottle', '1.5'],
      ['a negative', '-1'],
      ['words', 'lots'],
    ])('refuses %s units', (_why, count) => {
      expect(problemsWith([row({ count })])['new-1'].count).toEqual(expect.any(String));
    });

    it('accepts an empty slot, which is a thing a shelf can be', () => {
      expect(problemsWith([row({ count: '0' })])).toEqual({});
    });
  });

  describe('two rows claiming the same selector', () => {
    /**
     * The server refuses this too, with the path of the field — but it refuses
     * the whole visit, so the technician loses the rest of what they typed.
     *
     * Only the new row is marked, and that is not a preference: a product
     * already on the shelf shows the machine's word for it as a heading rather
     * than a field, so a message about it would have nowhere to live and
     * nothing the reader could do about it. The row being taken on is the one
     * that can give.
     */
    it('marks the row being taken on, not the one already on the shelf', () => {
      const problems = problemsWith([
        row({ id: 'WATER', selector: 'WATER', isNew: false }),
        row({ id: 'new-1', selector: 'WATER' }),
      ]);

      expect(problems['new-1'].selector).toEqual(expect.any(String));
      expect(problems.WATER).toBeUndefined();
    });

    it('marks both when it is two new rows that collide', () => {
      const problems = problemsWith([
        row({ id: 'new-1', selector: 'TEA' }),
        row({ id: 'new-2', selector: 'TEA' }),
      ]);

      expect(problems['new-1'].selector).toEqual(expect.any(String));
      expect(problems['new-2'].selector).toEqual(expect.any(String));
    });

    it('does not confuse a row with itself', () => {
      expect(problemsWith([row({ id: 'WATER', selector: 'WATER', isNew: false })])).toEqual({});
    });
  });
});
