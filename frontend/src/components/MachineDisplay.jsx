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

const MESSAGES = {
  unsupported_coin: () => 'Coin rejected',
  invalid_money_amount: () => 'Coin rejected',
  unknown_product: () => 'Unknown selection',
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
  // message and a shrug.
  invalid_request_payload: showing(
    'field',
    (field) => `Invalid: ${field}`,
    'Request not understood',
  ),
  malformed_json: () => 'Request not understood',
  machine_not_provisioned: () => 'Out of service',
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

export default function MachineDisplay({ amount, error = null }) {
  const message = messageFor(error);

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
