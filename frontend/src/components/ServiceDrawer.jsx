import { useCallback, useEffect, useRef, useState } from 'react';

import CoinSwitch from './CoinSwitch';
import CountField from './CountField';
import ProductRow from './ProductRow';
import { problemsWith } from './productRules';
import { blankProduct, seedForm } from './serviceForm';

// The whole `service` block, rows included. CountField renders elements of this
// block rather than a block of its own, so it imports no stylesheet.
import './ServiceDrawer.css';

/**
 * The door the person with the key opens, and the form behind it.
 *
 * Open and closed is local UI state, so it lives here rather than in the hook:
 * the machine has no opinion about whether a drawer is showing. The button and
 * the panel are one component because they are one physical thing — a door and
 * its handle.
 *
 * The form is seeded when the drawer opens rather than in an effect, so nothing
 * reseeds under someone's fingers while they are typing into it.
 *
 * It is a **non-modal** dialog: the rest of the panel stays reachable, so focus
 * moves in on open and back on close but is deliberately not trapped. Trapping
 * focus in a region the user can still see past is how a drawer becomes a
 * prison.
 */
const EMPTY = { products: [], coins: [] };

/** A shelf row's fields, in the order they are read on screen. */
const FIELDS = ['selector', 'name', 'price', 'count'];

/** One object, so an untouched form does not hand its rows a new one a render. */
const NOTHING_WRONG = {};

/**
 * What is worth saying about a till row beyond its number, and only where it is
 * not obvious. A denomination the machine has stopped taking is the important
 * one: the coins are still in there, they count towards nothing, and the person
 * holding the drawer open is the one who has to decide what to do about them.
 */
const noteFor = ({ accepted, dispensableAsChange }) => {
  if (!accepted) {
    return 'Not taken at the slot. Coins left inside stay inside — they are never given back as change.';
  }

  if (!dispensableAsChange) {
    return 'Never given back as change, so loading it will not turn the lamp off.';
  }

  return undefined;
};

export default function ServiceDrawer({
  products,
  changeReserve,
  supportedCoins,
  onService,
  disabled = false,
}) {
  const [isOpen, setIsOpen] = useState(false);
  const [form, setForm] = useState(EMPTY);
  const triggerRef = useRef(null);

  /**
   * Whether this form has been sent back once. Before that it says nothing — a
   * form that objects to "W" on the way to "WATER" teaches people to ignore it
   * — and after it, it keeps checking as things are corrected, so a fixed field
   * stops complaining without waiting for another press.
   */
  const [hasBeenRefused, setHasBeenRefused] = useState(false);

  /**
   * Derived, never stored. Keeping a copy would mean two answers to "what is
   * wrong with this shelf" — the list of rows and the list of complaints about
   * it — kept in step by hand, and they come apart precisely when a batched
   * update lands between the two writes: a message left on a field that was
   * just corrected, or gone from one that was not.
   */
  const problems = hasBeenRefused ? problemsWith(form.products) : NOTHING_WRONG;

  /** New rows have no selector to be identified by, so they are numbered. */
  const rowsTakenOn = useRef(0);

  const open = () => {
    setForm(seedForm(products, changeReserve, supportedCoins));
    setHasBeenRefused(false);
    setIsOpen(true);
  };

  const close = useCallback(() => {
    setIsOpen(false);
    triggerRef.current?.focus();
  }, []);

  /**
   * Escape is listened for on the document rather than on the panel, and the
   * accessibility linter is what pointed that out: a `role="dialog"` div is not
   * an interactive element, so hanging a key handler on it is the wrong shape.
   *
   * The rewrite turned out to be better than what it replaced. This dialog is
   * non-modal, so the rest of the panel is still reachable by Tab — and a
   * handler bound to the panel would have stopped working the moment focus
   * wandered out of it, which is precisely when someone reaches for Escape.
   */
  useEffect(() => {
    if (!isOpen) {
      return undefined;
    }

    const dismiss = (event) => {
      if (event.key === 'Escape') {
        close();
      }
    };

    document.addEventListener('keydown', dismiss);

    return () => document.removeEventListener('keydown', dismiss);
  }, [close, isOpen]);

  /**
   * A callback ref rather than an effect, so the panel takes focus exactly once
   * — when it mounts. It has to be stable: a fresh function each render would
   * make React detach and reattach the ref, stealing the focus back from
   * whatever field was being typed into.
   */
  const takeFocus = useCallback((panel) => {
    panel?.focus();
  }, []);

  const setProducts = (next) => {
    setForm((current) => ({ ...current, products: next(current.products) }));
  };

  /**
   * Rows are addressed by their own identity rather than by where they sit,
   * because a shelf can lose one. Keyed by position, removing a row leaves the
   * fields in place and slides every value below it up by one — the kind of bug
   * that looks like the form ignoring what was typed.
   */
  const update = (kind, id, change) => {
    setForm((current) => ({
      ...current,
      [kind]: current[kind].map((row) => (row.id === id ? { ...row, ...change } : row)),
    }));
  };

  const addProduct = () => {
    rowsTakenOn.current += 1;

    setProducts((rows) => [...rows, blankProduct(`new-${rowsTakenOn.current}`)]);
  };

  // There is no endpoint for this and there does not need to be one: a service
  // visit states the catalogue, so a product left out of the body is a product
  // the machine stops stocking.
  const removeProduct = (id) => {
    setProducts((rows) => rows.filter((row) => row.id !== id));
  };

  const submit = (event) => {
    event.preventDefault();

    const found = problemsWith(form.products);
    const refused = Object.keys(found).length > 0;

    setHasBeenRefused(refused);

    if (refused) {
      /*
       * The visit is not attempted at all, so nothing typed into the other rows
       * is lost to a refusal of the whole body — and the cursor lands on the
       * field that stopped it, in the order they appear on screen, so pressing
       * Apply never looks like it did nothing. The element is looked up rather
       * than kept in a ref because the fields belong to the rows: one ref per
       * field, threaded through a component whose job is a row, would be more
       * machinery than the one question being asked of the DOM.
       */
      const [firstBadRow] = form.products.filter((row) => found[row.id]);
      const field = FIELDS.find((name) => found[firstBadRow.id][name]);

      document.getElementById(`${firstBadRow.id}-${field}`)?.focus();

      return;
    }

    onService(
      form.products.map(({ selector, name, price, count }) => ({
        selector,
        name,
        price,
        count: Number(count),
      })),
      // `dispensableAsChange` and `accepted` came in on every coin and go back
      // out on none: the request body declares additionalProperties false, so a
      // stray field is a refusal rather than something the API quietly ignores.
      form.coins.map(({ denomination, count }) => ({ denomination, count: Number(count) })),
      // Which coins the machine takes travels as its own list rather than as a
      // flag on the rows above, because the two are independent: a denomination
      // can be in the till and not in the acceptor, which is money the machine
      // holds and will never hand back. Always stated, never omitted — an absent
      // list means "leave the acceptor alone" and an empty one means "take
      // nothing", and this form always knows which it means.
      form.coins.filter(({ accepted }) => accepted).map(({ denomination }) => denomination),
    );
  };

  return (
    <div className="service">
      {/*
        The handle is not disabled while an action is in flight, and neither is
        Close. `disabled` exists to stop a second request going out before the
        first is answered, and opening or shutting a drawer sends nothing — only
        the fields and Apply can do that. Locking the door because the machine is
        busy would be consistency for its own sake.
      */}
      <button
        aria-controls="service-drawer"
        aria-expanded={isOpen}
        className="service__handle"
        onClick={open}
        ref={triggerRef}
        type="button"
      >
        Service
      </button>

      {isOpen && (
        <div
          aria-label="Service"
          className="service__drawer"
          id="service-drawer"
          ref={takeFocus}
          role="dialog"
          tabIndex={-1}
        >
          {/*
            `noValidate` hands the checking to this form rather than to the
            browser, and it is a choice with a reason: a blank row starts life
            with empty fields, and native validation would refuse the submit
            before this component ever ran — silently, with a bubble that no
            screen reader announces the way a described field is announced, and
            that no test at this level can read. One gate, ours, so every
            refusal is said the same way in the same place.
          */}
          <form className="service__form" noValidate onSubmit={submit}>
            <fieldset className="service__group" disabled={disabled}>
              <legend>Slots</legend>
              <ul className="service__rows">
                {form.products.map((product) => (
                  <ProductRow
                    key={product.id}
                    onChange={(change) => update('products', product.id, change)}
                    onRemove={() => removeProduct(product.id)}
                    problems={problems[product.id]}
                    product={product}
                  />
                ))}
              </ul>

              {/*
                Inside the fieldset, so it locks with everything else while the
                machine is answering: a row added to a form that is mid-flight
                would be a row the answer knows nothing about.
              */}
              <button className="service__add" onClick={addProduct} type="button">
                Add product
              </button>
            </fieldset>

            <fieldset className="service__group" disabled={disabled}>
              <legend>Till</legend>
              <ul className="service__rows">
                {form.coins.map((coin) => (
                  <CountField
                    count={coin.count}
                    id={`coins-${coin.denomination}`}
                    key={coin.denomination}
                    label={`${coin.denomination} — coins`}
                    note={noteFor(coin)}
                    onChange={(value) => update('coins', coin.id, { count: value })}
                  >
                    <CoinSwitch
                      accepted={coin.accepted}
                      denomination={coin.denomination}
                      id={`accepts-${coin.denomination}`}
                      onToggle={(accepted) => update('coins', coin.id, { accepted })}
                    />
                  </CountField>
                ))}
              </ul>
            </fieldset>

            <div className="service__actions">
              <button className="service__apply" disabled={disabled} type="submit">
                Apply
              </button>
              <button className="service__close" onClick={close} type="button">
                Close
              </button>
            </div>
          </form>
        </div>
      )}
    </div>
  );
}
