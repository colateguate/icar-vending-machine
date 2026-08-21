import { useCallback, useEffect, useRef, useState } from 'react';

import CountField from './CountField';
import { seedForm } from './serviceForm';

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

export default function ServiceDrawer({
  products,
  changeReserve,
  acceptedCoins,
  onService,
  disabled = false,
}) {
  const [isOpen, setIsOpen] = useState(false);
  const [form, setForm] = useState(EMPTY);
  const triggerRef = useRef(null);

  const open = () => {
    setForm(seedForm(products, changeReserve, acceptedCoins));
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

  const setCount = (kind, index, count) => {
    setForm((current) => ({
      ...current,
      [kind]: current[kind].map((row, at) => (at === index ? { ...row, count } : row)),
    }));
  };

  const submit = (event) => {
    event.preventDefault();

    onService(
      form.products.map(({ selector, name, price, count }) => ({
        selector,
        name,
        price,
        count: Number(count),
      })),
      // `dispensableAsChange` came in on every coin and goes back out on none:
      // the request body declares additionalProperties false, so a stray field
      // is a refusal rather than something the API quietly ignores.
      form.coins.map(({ denomination, count }) => ({ denomination, count: Number(count) })),
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
          <form className="service__form" onSubmit={submit}>
            <fieldset className="service__group" disabled={disabled}>
              <legend>Slots</legend>
              <ul className="service__rows">
                {form.products.map(({ selector, name, price, count }, index) => (
                  <CountField
                    count={count}
                    id={`stock-${selector}`}
                    key={selector}
                    label={`${selector} · ${name} · ${price} — units`}
                    onChange={(value) => setCount('products', index, value)}
                  />
                ))}
              </ul>
            </fieldset>

            <fieldset className="service__group" disabled={disabled}>
              <legend>Till</legend>
              <ul className="service__rows">
                {form.coins.map(({ denomination, dispensableAsChange, count }, index) => (
                  <CountField
                    count={count}
                    id={`coins-${denomination}`}
                    key={denomination}
                    label={`${denomination} — coins`}
                    note={
                      dispensableAsChange
                        ? undefined
                        : 'Never given back as change, so loading it will not turn the lamp off.'
                    }
                    onChange={(value) => setCount('coins', index, value)}
                  />
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
