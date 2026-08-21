import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import MachineDisplay from './MachineDisplay';

/**
 * The errors here are plain objects, not `ApiProblem` instances, and that is
 * the point rather than a shortcut. This component may not import from
 * `services/` — the layer table in `CLAUDE.md` forbids it — so it branches on
 * what an error *carries*, and the test hands it exactly that and nothing
 * more. If these assertions pass with a bare object, the component provably
 * never reached for the class.
 */
const refusal = (code, extensions = {}) => ({ code, extensions });

describe('MachineDisplay', () => {
  it('shows the inserted amount as the string it arrived as', () => {
    render(<MachineDisplay amount="0.65" />);

    expect(screen.getByRole('status', { name: 'Display' })).toHaveTextContent('0.65');
  });

  /**
   * The map renders the API's error catalogue, so the whole of it is pinned
   * here rather than the handful this panel happens to provoke. From outside,
   * an entry nobody tested and an entry nobody can reach look identical; the
   * component says in comments which of them is which, and this says what each
   * one puts on the screen.
   *
   * These are the eight codes whose sentence is fixed. The other three read an
   * extension and have their own tests below, both branches each — eleven, the
   * length of the catalogue. `product_out_of_stock` appears again further down
   * for a different question: that one screen shows the message or the amount
   * and never both.
   */
  it.each([
    ['unsupported_coin', 'Coin rejected'],
    ['coin_not_accepted', 'Coin no longer taken'],
    ['invalid_money_amount', 'Coin rejected'],
    ['unknown_product', 'Unknown selection'],
    ['invalid_product_selector', 'Unknown selection'],
    ['product_out_of_stock', 'Sold out'],
    ['concurrent_modification', 'Busy, try again'],
    ['malformed_json', 'Request not understood'],
    ['machine_not_provisioned', 'Out of service'],
  ])('answers %s with "%s"', (code, sentence) => {
    render(<MachineDisplay amount="0.65" error={refusal(code)} />);

    expect(screen.getByRole('status', { name: 'Display' })).toHaveTextContent(sentence);
  });

  /**
   * `missingAmount` and `changeDue` are extensions the API puts in the document
   * precisely so a client does not have to find the number inside an English
   * sentence. Reading them is what proves `detail` is never parsed.
   */
  it('says how much more is needed, taking the figure from the extension', () => {
    render(<MachineDisplay amount="0.60" error={refusal('insufficient_funds', { missingAmount: '0.40' })} />);

    expect(screen.getByText('Insert 0.40 more')).toBeVisible();
  });

  it('names the change it could not compose when it refuses the sale', () => {
    render(<MachineDisplay amount="1.00" error={refusal('exact_change_required', { changeDue: '0.35' })} />);

    expect(screen.getByText('No change for 0.35 — use exact change')).toBeVisible();
  });

  it('shows the message instead of the amount, because a machine has one screen', () => {
    render(<MachineDisplay amount="0.65" error={refusal('product_out_of_stock')} />);

    const display = screen.getByRole('status', { name: 'Display' });

    expect(display).toHaveTextContent('Sold out');
    expect(display).not.toHaveTextContent('0.65');
  });

  /**
   * The contract promises `missingAmount` with this code, and the backend's own
   * gate provokes every published example, so the promise is kept today. It is
   * still a promise made by something on the other side of a network: if it is
   * ever broken, the panel should degrade to the same generic fault it shows for
   * a code it does not know, not take the whole screen down with a TypeError.
   */
  it('degrades instead of crashing when a promised extension is not there', () => {
    render(<MachineDisplay amount="0.60" error={{ code: 'insufficient_funds' }} />);

    expect(screen.getByText('Out of order')).toBeVisible();
  });

  /**
   * The service form's own failure. `field` says which box is wrong, so the
   * screen can point at it instead of relaying an English sentence about it.
   */
  it('names the field the machine would not accept', () => {
    render(
      <MachineDisplay
        amount="0.00"
        error={refusal('invalid_request_payload', { field: 'products[0].count' })}
      />,
    );

    expect(screen.getByText('Invalid: products[0].count')).toBeVisible();
  });

  /**
   * Without the extension there is nothing to point at, and the honest fallback
   * is still about the request rather than the generic "out of order" an
   * unknown code gets — the machine understood us fine, it just said no.
   */
  it('still blames the request when the document does not say which field', () => {
    render(<MachineDisplay amount="0.00" error={refusal('invalid_request_payload')} />);

    expect(screen.getByText('Request not understood')).toBeVisible();
  });

  it('falls back to a generic fault for a code it has never heard of', () => {
    render(<MachineDisplay amount="0.00" error={refusal('a_code_from_the_future')} />);

    expect(screen.getByText('Out of order')).toBeVisible();
  });

  /**
   * "It said no" and "it did not answer" are different things to whoever is
   * standing at the machine, and httpClient throws two different errors so the
   * panel can keep them apart. A transport failure carries no `code`, because
   * there was no document to read one out of.
   */
  it('tells an unreachable machine apart from a machine that refused', () => {
    render(<MachineDisplay amount="0.00" error={new Error('the machine could not be reached')} />);

    expect(screen.getByText('Machine unreachable')).toBeVisible();
  });
});
