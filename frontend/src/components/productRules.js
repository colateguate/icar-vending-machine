/**
 * What the form checks before it asks the machine anything.
 *
 * Every rule here is a **mirror** of one the API already enforces, and the 422
 * remains the authority: this is not the panel deciding what a product may be,
 * it is the panel repeating a constraint the published contract states, so a
 * typo is answered beside the box instead of by a round trip that refuses the
 * whole visit and loses everything else the technician typed.
 *
 * That the rules live in two places is the real cost, and it is worth naming:
 * they can drift. The direction that hurts is **stricter here than there** — a
 * panel that refuses what the machine accepts makes the machine look broken,
 * and nobody would think to look here for the reason. Which is why both
 * patterns below are copied from their source rather than written afresh, and
 * why the tests spell out the values that must keep passing.
 */

/** `ProductSelector::FORMAT` in the domain. */
const SELECTOR = /^[A-Z][A-Z0-9_-]{0,31}$/;

/** `Money::DECIMAL_FORMAT`. Note what it allows: "1" and "0" are amounts. */
const PRICE = /^\d+(\.\d{1,2})?$/;

/** Whole units, and the machine cannot hold minus one bottle. */
const COUNT = /^\d+$/;

const SELECTOR_PROBLEM =
  'Use capital letters, digits, _ or -, starting with a letter. No spaces.';
const DUPLICATE_PROBLEM = 'Another row on this shelf claims this selector too.';
const PRICE_PROBLEM = 'Use digits with at most two decimals, like 0.80.';
const NAME_PROBLEM = 'Give the product a name — it is what the customer reads.';
const COUNT_PROBLEM = 'Use a whole number of units, 0 or more.';

const claimedTwice = (products) => {
  const seen = new Map();

  for (const { selector } of products) {
    seen.set(selector, (seen.get(selector) ?? 0) + 1);
  }

  return new Set([...seen].filter(([, times]) => times > 1).map(([selector]) => selector));
};

/**
 * Everything wrong with the shelf as it stands, as `{ [row id]: { field:
 * message } }`. An empty object means there is nothing to say — which is what
 * the caller checks before sending.
 *
 * The selector of a product already on the shelf is not examined. It is the
 * machine's own word for it and this form cannot edit it, so judging it would
 * be judging the machine: a product stocked before the format was what it is
 * today would make its own row unusable, and nobody could take it off the shelf
 * because the form would refuse to send anything.
 *
 * @param {Array<{id: string, selector: string, name: string, price: string, count: string, isNew: boolean}>} products
 */
export function problemsWith(products) {
  const duplicated = claimedTwice(products);
  const problems = {};

  for (const { id, selector, name, price, count, isNew } of products) {
    const row = {};

    /*
     * Only a row being taken on is judged, and that is not only because its
     * selector is the only editable one — it is the only one with anywhere to
     * put the answer. A product already on the shelf shows the machine's word
     * for it as a heading, not a field, so a message about it would have to be
     * pinned somewhere the reader cannot act on. When a new row claims a
     * selector that is already taken, the new row is the one that can give.
     */
    if (isNew) {
      if (!SELECTOR.test(selector)) {
        row.selector = SELECTOR_PROBLEM;
      } else if (duplicated.has(selector)) {
        row.selector = DUPLICATE_PROBLEM;
      }
    }

    if (name.trim() === '') {
      row.name = NAME_PROBLEM;
    }

    if (!PRICE.test(price)) {
      row.price = PRICE_PROBLEM;
    }

    if (!COUNT.test(count)) {
      row.count = COUNT_PROBLEM;
    }

    if (Object.keys(row).length > 0) {
      problems[id] = row;
    }
  }

  return problems;
}
