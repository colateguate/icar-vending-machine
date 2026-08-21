// Warns when the running Node is older than the floor package.json declares.
//
// It lives in a file rather than inside `node -e` in the Makefile because a
// guard you cannot read is a guard nobody checks, and this one had already
// failed open once: written as a one-liner it ranked ">=22.22.2" correctly and
// turned ">=22.12" into NaN, which compares false against everything and made
// the warning quietly disappear. A short version string is not hypothetical —
// the floor in package.json read exactly that until ticket 18.
//
// Moving it here also exposed something the one-liner had been hiding. The
// inline version used `require`, and it worked: `node -e` does not read the
// `"type": "module"` this package declares. The same code in a `.js` file does,
// and refused to run. A guard that only works in the shape nobody can read is
// worth knowing about.
//
// It never exits non-zero. Refusing to run a suite that passes is a different
// decision with different consequences, and it has its own ticket.

import { readFileSync } from 'node:fs';

const manifest = new URL('../package.json', import.meta.url);
const { engines } = JSON.parse(readFileSync(manifest, 'utf8'));

// Every number in the range, padded to major.minor.patch. ">=22.22.2" gives
// [22, 22, 2]; ">=22.12" gives [22, 12, 0] rather than something that ranks
// as NaN and silently compares false against everything.
const parts = (spec) => {
  const found = (spec.match(/[0-9]+/g) ?? []).map(Number);
  return [found[0] ?? 0, found[1] ?? 0, found[2] ?? 0];
};

const rank = ([major, minor, patch]) => major * 1e6 + minor * 1e3 + patch;

const floor = parts(engines.node);
const running = parts(process.versions.node);

if (rank(running) < rank(floor)) {
  console.error(
    `WARNING  Node ${process.versions.node} is below the ${floor.join('.')} that frontend/package.json declares.`,
  );
  console.error(
    '         jsdom refuses this version, so a green suite here is weaker evidence than the same suite in CI.',
  );
}
