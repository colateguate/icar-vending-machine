// Does a running Node satisfy the range package.json declares?
//
// Deliberately not a semver implementation. It reads the two operators this
// repository's `engines.node` actually uses and **refuses to guess** at
// anything else, which is the property that matters: the guard this feeds had
// already failed open once, and a version check that silently approves is
// worse than no version check, because the silence is read as approval.
//
//   ">=X.Y.Z"   that version or anything above it
//   "^X.Y.Z"    that version or above, without leaving the major
//   "A || B"    either of them
//
// Prereleases are ranked as their release: `22.22.2-rc.1` counts as 22.22.2,
// where semver would place it below. Pinned by a test rather than left to be
// found, and deliberately not fixed — nobody develops against a Node release
// candidate here, and the arm of semver that handles them is the arm this file
// exists to avoid reimplementing.
//
// The `^` is the one that earns this file. jsdom asks for
// `^22.22.2 || ^24.15.0 || >=26.0.0`, and the difference between that and the
// `>=22.22.2` the manifest used to declare is not pedantry: `>=` accepts Node
// 23.x and 24.0 through 24.14, every one of which jsdom refuses.

export const UNREADABLE = Symbol('unreadable range');

// Every number in a version or clause, padded to major.minor.patch. "22.12"
// gives [22, 12, 0] rather than something that ranks as NaN and compares false
// against everything — the exact shape that made the warning disappear once.
const parts = (text) => {
  const found = (text.match(/[0-9]+/g) ?? []).map(Number);

  return [found[0] ?? 0, found[1] ?? 0, found[2] ?? 0];
};

const rank = ([major, minor, patch]) => major * 1e6 + minor * 1e3 + patch;

const parseClause = (text) => {
  const written = text.trim().match(/^(>=|\^)\s*([0-9]+(?:\.[0-9]+){0,2})$/);

  if (!written) {
    return UNREADABLE;
  }

  const [, operator, version] = written;
  const floor = parts(version);

  return (running) =>
    rank(running) >= rank(floor) && (operator === '>=' || running[0] === floor[0]);
};

/**
 * True, false, or UNREADABLE — three answers rather than two, because "I do not
 * understand this range" is not the same as "this version is fine" and must
 * never be reported as it.
 */
export function satisfies(version, range) {
  const clauses = range.split('||').map(parseClause);

  if (clauses.includes(UNREADABLE)) {
    return UNREADABLE;
  }

  const running = parts(version);

  return clauses.some((accepts) => accepts(running));
}
