import { describe, expect, it } from 'vitest';

import { satisfies, UNREADABLE } from './node-version';

/**
 * The only logic in this package that decides something about a version, and
 * the second attempt at it. The first ranked ">=22.22.2" correctly and turned
 * ">=22.12" into NaN, which compares false against everything and made the
 * warning vanish on exactly the format the manifest carried at the time.
 *
 * So the cases below are not decoration: each one is a way this has failed or
 * could fail, written down where it fails loudly.
 */
const JSDOM = '^22.22.2 || ^24.15.0 || >=26.0.0';

describe('satisfies', () => {
  it.each([
    ['22.22.2', true, 'the floor itself'],
    ['22.30.1', true, 'above the floor, same major'],
    ['24.15.0', true, 'the second alternative'],
    ['26.4.0', true, 'the open-ended one'],
    ['99.0.0', true, 'anything above the open-ended one'],
  ])('accepts %s — %s', (version, expected) => {
    expect(satisfies(version, JSDOM)).toBe(expected);
  });

  /**
   * These four are the whole reason this file exists. Every one of them is
   * above 22.22.2, so the `>=22.22.2` the manifest used to declare called them
   * all supported — and jsdom refuses all four.
   */
  it.each([
    ['22.14.0', 'below the floor, and what this machine runs today'],
    ['23.5.0', 'a major jsdom skips entirely'],
    ['24.0.0', 'the right major, before the minor jsdom asks for'],
    ['25.9.9', 'between the second alternative and the third'],
  ])('refuses %s — %s', (version) => {
    expect(satisfies(version, JSDOM)).toBe(false);
  });

  it('keeps a caret inside its major', () => {
    expect(satisfies('23.0.0', '^22.22.2')).toBe(false);
    expect(satisfies('23.0.0', '>=22.22.2')).toBe(true);
  });

  /**
   * The NaN trap, pinned. A two-segment version is padded rather than parsed
   * into something that compares false against everything.
   */
  it('reads a range that omits the patch', () => {
    expect(satisfies('22.11.0', '>=22.12')).toBe(false);
    expect(satisfies('22.12.0', '>=22.12')).toBe(true);
  });

  /**
   * The property that makes this worth having at all: an operator it was never
   * taught is reported as unreadable, never as satisfied. A guard that approves
   * what it cannot read is the failure this file replaces.
   */
  it.each(['~22.22.2', '22.x', '>22.22.2', '^22.22.2 || ~24.15.0'])(
    'refuses to guess at %s',
    (range) => {
      expect(satisfies('22.22.2', range)).toBe(UNREADABLE);
    },
  );
});
