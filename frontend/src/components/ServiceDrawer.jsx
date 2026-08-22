import { useCallback, useEffect, useRef, useState } from 'react';

import ShelfPanel from './ShelfPanel';
import TillPanel from './TillPanel';
import { problemsWith } from './productRules';
import { blankProduct, seedForm, toServicePayload } from './serviceForm';

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

/** The two halves of a service visit. Their order is the order of the tabs. */
const TABS = ['Products', 'Coins'];

export default function ServiceDrawer({
  products,
  changeReserve,
  supportedCoins,
  onService,
  disabled = false,
}) {
  const [isOpen, setIsOpen] = useState(false);
  const [form, setForm] = useState(EMPTY);
  const [tab, setTab] = useState(TABS[0]);
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
    setTab(TABS[0]);
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

  /**
   * The id of a field to focus once it exists. A refusal can point at a field
   * on a tab that is not showing, and an element that is not mounted cannot be
   * focused — so the submit switches the tab and leaves the id here, and this
   * effect runs after the panel has rendered, when the field is real.
   */
  const focusAfterRender = useRef(null);

  useEffect(() => {
    if (focusAfterRender.current) {
      document.getElementById(focusAfterRender.current)?.focus();
      focusAfterRender.current = null;
    }
  });

  /**
   * The keyboard half of the tab strip: arrows move and select in one motion,
   * which is the pattern every native tab strip follows. Focus follows
   * selection so a screen reader announces the tab it lands on.
   */
  const onTabArrow = (event) => {
    if (event.key !== 'ArrowRight' && event.key !== 'ArrowLeft') {
      return;
    }

    const at = TABS.indexOf(tab);
    const to = TABS[(at + (event.key === 'ArrowRight' ? 1 : TABS.length - 1)) % TABS.length];

    setTab(to);
    document.getElementById(`service-tab-${to}`)?.focus();
  };

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
       * field that stopped it, so pressing Apply never looks like it did
       * nothing. Every rule is about a product, so the field is always on the
       * Products tab — which may not be the one on screen, and that is the trap
       * the tabs introduced: the element does not exist until its panel is
       * shown. Switching first and focusing by id in the ref below is what
       * makes both orders work; the id lookup itself is the same seam the
       * labels already need.
       */
      const [firstBadRow] = form.products.filter((row) => found[row.id]);
      const field = FIELDS.find((name) => found[firstBadRow.id][name]);

      setTab('Products');
      focusAfterRender.current = `${firstBadRow.id}-${field}`;

      return;
    }

    onService(...toServicePayload(form));
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
            {/*
              What the machine sells and what it takes are two jobs that share
              nothing but the visit, and stacked in one column they were nearly
              two screens of scrolling with no sign of which one you were in.
              Only the selected panel is mounted; the *form* holds the state, so
              nothing typed into the hidden half is lost, and one Apply still
              sends both — SERVICE states the whole machine, and an "apply the
              coins only" would have to send the products anyway.
            */}
            <div aria-label="What to manage" className="service__tabs" role="tablist">
              {TABS.map((name) => (
                <button
                  aria-controls={`service-panel-${name}`}
                  aria-selected={tab === name}
                  className="service__tab"
                  id={`service-tab-${name}`}
                  key={name}
                  onClick={() => setTab(name)}
                  onKeyDown={onTabArrow}
                  role="tab"
                  tabIndex={tab === name ? 0 : -1}
                  type="button"
                >
                  {name}
                </button>
              ))}
            </div>

            <div
              aria-labelledby={`service-tab-${tab}`}
              id={`service-panel-${tab}`}
              role="tabpanel"
            >
              {tab === 'Products' ? (
                <ShelfPanel
                  disabled={disabled}
                  onAdd={addProduct}
                  onChange={(id, change) => update('products', id, change)}
                  onRemove={removeProduct}
                  problems={problems}
                  products={form.products}
                />
              ) : (
                <TillPanel
                  coins={form.coins}
                  disabled={disabled}
                  onChange={(id, change) => update('coins', id, change)}
                />
              )}
            </div>

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
