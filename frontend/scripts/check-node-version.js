// Warns when the running Node does not satisfy the range package.json declares.
//
// It lives in a file rather than inside `node -e` in the Makefile because a
// guard you cannot read is a guard nobody checks, and this one had already
// failed open once: written as a one-liner it ranked ">=22.22.2" correctly and
// turned ">=22.12" into NaN, which compares false against everything and made
// the warning quietly disappear.
//
// Moving it here also exposed something the one-liner had been hiding. The
// inline version used `require`, and it worked: `node -e` does not read the
// `"type": "module"` this package declares. The same code in a `.js` file does,
// and refused to run. A guard that only works in the shape nobody can read is
// worth knowing about.
//
// The comparison itself lives in node-version.js, which has tests. This file is
// the shell around it: read the manifest, ask, and say something useful. That
// split is what ticket 25 bought — the manifest now declares a disjunction of
// ranges rather than a single floor, and version logic without tests is how
// this failed the first time.
//
// It never exits non-zero. Refusing to run a suite that passes would be a
// different decision with different consequences, and `npm ci` already declines
// on its own terms if anyone ever wants that: `engine-strict=true` in an
// .npmrc. Ticket 25 measured what that would do here and did not take it.

import { readFileSync } from 'node:fs';

import { satisfies, UNREADABLE } from './node-version.js';

const manifest = new URL('../package.json', import.meta.url);
const { engines } = JSON.parse(readFileSync(manifest, 'utf8'));

const declared = engines?.node;
const running = process.versions.node;
const verdict = declared ? satisfies(running, declared) : true;

if (verdict === UNREADABLE) {
  console.error(
    `WARNING  frontend/package.json declares "${declared}", which this check cannot read.`,
  );
  console.error(
    '         It understands ">=X.Y.Z", "^X.Y.Z" and "A || B" and refuses to guess at the rest,',
  );
  console.error(
    '         so this is a warning about the check rather than about Node — see scripts/node-version.js.',
  );
} else if (verdict === false) {
  console.error(`WARNING  Node ${running} does not satisfy the "${declared}" that frontend/package.json declares.`);
  console.error(
    '         jsdom is what asks for it, so a green suite here is weaker evidence than the same suite in CI.',
  );
}
