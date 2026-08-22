import './MachineDisplay.css';

/**
 * The machine's one screen: the amount taken so far, or the current message
 * when there is one. Two displays side by side would be a dashboard pretending
 * to be a machine, and a real machine tells you either how much you have put in
 * or what went wrong, never both at once.
 *
 * Messages are chosen by the problem's `code`, which is the API's stable name
 * for a failure. `detail` is English the API may reword at any time, so a panel
 * that switched on it would break on a copy edit (ADR-0012).
 *
 * Two of these read an extension rather than a sentence. That is what the
 * extensions are for: the API puts the figure the caller needs into the
 * document instead of only into the prose.
 */
const GENERIC_FAULT = 'Out of order';

/**
 * The one thing this screen says with nothing having gone wrong. A machine that
 * takes no coin cannot be paid, so it cannot sell — and the customer deserves
 * to read that rather than infer it from a coin slot with no buttons under it.
 */
const OUT_OF_SERVICE = 'Out of service';

/**
 * A message that needs something the document is supposed to carry. If it is
 * missing, fall back rather than print "undefined" or take the panel down with
 * a TypeError. The fallback is per message because "we could not read what you
 * sent" degrades to a different sentence than "the machine is broken".
 */
const showing =
  (extension, write, fallback = GENERIC_FAULT) =>
  (error) => {
    const value = error.extensions?.[extension];

    return value ? write(value) : fallback;
  };

/**
 * Every code the API's `ErrorCatalog` can answer with, in the order that table
 * declares them, and the sentence the screen shows for each.
 *
 * Four of the twelve this panel cannot provoke, and they are marked below
 * rather than deleted. The distinction is worth being explicit about, because
 * an entry nobody can reach is otherwise indistinguishable from an entry
 * nobody has tested. They stay because this map renders a *published
 * contract*, not the panel's current call sites: reachability is a property of
 * today's screen and changes the moment a field is added, while the contract
 * is what the API promises to anyone. The cost is one line each; the
 * alternative is that a documented failure arrives one day and the machine
 * says "Out of order" about a request it understood perfectly well.
 */
const MESSAGES = {
  // Not reachable, and it stays that way now the till rows come from
  // `supportedCoins` instead: both lists are the machine's own answer about its
  // own acceptor, so every denomination this panel names — at the slot, in the
  // till, or in the set it asks the machine to take — came from the machine.
  // And so did every price.
  unsupported_coin: () => 'Coin rejected',
  // Reachable, and for the same reason `unknown_product` is: the buttons show
  // the coins the machine took when the panel loaded, and a service visit can
  // switch a denomination off underneath them. The wording says the machine
  // stopped taking it rather than that the coin is bad, which is the whole
  // difference between this code and the one above it.
  coin_not_accepted: () => 'Coin no longer taken',
  invalid_money_amount: () => 'Coin rejected',
  // Reachable. The catalogue on screen is the one the machine published when
  // the panel loaded, and a service visit — another tab, or the technician
  // standing at the same machine — can replace it. Nothing refetches, so a
  // button here can name a product the machine has stopped stocking.
  unknown_product: () => 'Unknown selection',
  // Not reachable: a selector is only ever echoed back from what the machine
  // published. The service form edits counts and nothing else, so no selector
  // is ever typed into this panel.
  invalid_product_selector: () => 'Unknown selection',
  product_out_of_stock: () => 'Sold out',
  insufficient_funds: showing('missingAmount', (amount) => `Insert ${amount} more`),
  exact_change_required: showing(
    'changeDue',
    (amount) => `No change for ${amount} — use exact change`,
  ),
  concurrent_modification: () => 'Busy, try again',
  // `field` exists so a client can point at the box that is wrong instead of
  // relaying an English sentence about it. The service form is where this one
  // comes from, and where naming the field is the difference between a usable
  // message and a shrug. Reachable, and cheaply: the count boxes are the only
  // values a person types here, and `type="number"` accepts `1e21` — which
  // survives `min`, `step` and `required`, and which the API refuses.
  invalid_request_payload: showing(
    'field',
    (field) => `Invalid: ${field}`,
    'Request not understood',
  ),
  // Not reachable: every body this panel sends is built by `JSON.stringify`.
  malformed_json: () => 'Request not understood',
  // Not the same sentence as a machine somebody switched off, and the
  // difference is whose problem it is. This one is a machine nobody has
  // provisioned — our fault, answered with a 503 — while a machine out of
  // service is a technician's decision, correctly carried out. They stopped
  // sharing a sentence the day the second one became reachable.
  machine_not_provisioned: () => 'Not ready yet',
};

/**
 * The error is read for what it carries, not for what class it is, because
 * `components/` may not import from `services/`. The question turns out to be
 * the same one either way: a problem document has a `code`, and a transport
 * failure has none because there was never an answer to read one out of.
 */
function messageFor(error) {
  if (!error) {
    return null;
  }

  if (!error.code) {
    return 'Machine unreachable';
  }

  const write = MESSAGES[error.code];

  return write ? write(error) : GENERIC_FAULT;
}

/**
 * Three things can be on this screen and only one of them at a time, so the
 * order they win in is the decision worth stating.
 *
 * A refusal goes first because it answers the button the customer just pressed;
 * a standing notice shown over it would make that press look like it did
 * nothing at all. Out of service comes next: nothing has gone wrong and nobody
 * asked, but it is true while the customer stands there. The amount is last,
 * and it is what the screen says when there is nothing to say.
 */
export default function MachineDisplay({ amount, error = null, outOfService = false }) {
  const message = messageFor(error) ?? (outOfService ? OUT_OF_SERVICE : null);

  return (
    <p aria-label="Display" className="display" role="status">
      {message ?? (
        <>
          <span className="display__label">Inserted</span>{' '}
          <span className="display__amount">{amount}</span>
        </>
      )}
    </p>
  );
}
