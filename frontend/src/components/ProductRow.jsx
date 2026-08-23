/**
 * One shelf of the machine, as something a technician can change.
 *
 * The selector is printed rather than edited: it is the identity of the row,
 * and a service visit states the catalogue in absolutes, so changing it would
 * not rename a product — it would discontinue one and stock another under the
 * same roof. The three things that *are* properties of the product are fields.
 *
 * It carries no stylesheet of its own: a row is part of the `service` block.
 */

/**
 * A labelled field of this row. The selector goes into the name and not onto
 * the screen — three fields per row would print it three times, next to a
 * heading that already says it — which is the same trick, and the same reason,
 * as the coin switches in the till below.
 *
 * The two spans are not decoration: an accessible name is concatenated without
 * a separator, so a trailing space inside one element is trimmed away and
 * "WATER — name" arrives as "WATER —name".
 */
function Field({ id, selector, label, type, inputMode, min, step, value, problem, onChange }) {
  const problemId = `${id}-problem`;

  return (
    <div className={`service__cell service__cell--${label}`}>
      <label htmlFor={id}>
        <span className="visually-hidden">{selector} —</span> <span>{label}</span>
      </label>
      {/*
        `aria-invalid` and a description rather than a colour: a red border says
        nothing to a screen reader and nothing to anyone who cannot tell red
        from grey. Both attributes are left off entirely when there is no
        problem — `aria-invalid="false"` on every field is noise a screen reader
        reads out.
      */}
      <input
        aria-describedby={problem ? problemId : undefined}
        aria-invalid={problem ? true : undefined}
        className={`service__field service__field--${label}`}
        id={id}
        inputMode={inputMode}
        min={min}
        onChange={(event) => onChange(event.target.value)}
        required
        step={step}
        type={type}
        value={value}
      />
      {problem && (
        <p className="service__problem" id={problemId}>
          {problem}
        </p>
      )}
    </div>
  );
}

export default function ProductRow({ product, problems = {}, onChange, onRemove }) {
  const { id, selector, name, price, count, isNew } = product;

  /*
   * What this row calls itself in the names of its own controls. A row still
   * being filled in has no selector yet, and "— selector" is not something to
   * read out to anybody: until it has a word of its own, it borrows one.
   */
  const known = selector || 'the new product';

  return (
    <li className="service__row service__row--product">
      {/*
        A product already on the shelf shows the machine's word for it; one
        being taken on is still deciding, and types it. The two never coexist:
        the badge and the field are the same thing in two states, which is why
        one replaces the other rather than sitting beside it.
      */}
      {isNew ? (
        <Field
          id={`${id}-selector`}
          label="selector"
          onChange={(value) => onChange({ selector: value })}
          problem={problems.selector}
          selector={known}
          type="text"
          value={selector}
        />
      ) : (
        <p className="service__selector">{selector}</p>
      )}

      <Field
        id={`${id}-name`}
        label="name"
        onChange={(value) => onChange({ name: value })}
        problem={problems.name}
        selector={known}
        type="text"
        value={name}
      />

      {/*
        Text, and deliberately not `type="number"`. A price is a decimal string
        from the API to the DOM and back (ADR-0004); a number input would make
        the browser the first thing to touch it — normalising the separator by
        locale, accepting `1e21` — and every value it produced would be asking
        to be read with `Number()`. `inputMode` still asks a phone for the right
        keyboard, which is the only part of `type="number"` worth having here.
      */}
      <Field
        id={`${id}-price`}
        inputMode="decimal"
        label="price"
        onChange={(value) => onChange({ price: value })}
        problem={problems.price}
        selector={known}
        type="text"
        value={price}
      />

      <Field
        /*
          The element is addressed by the word the payload uses and labelled
          with the word a person reads. They were the same for a while and the
          seam showed: a lookup table pairing "count" with "units" so the form
          could find a field it had just complained about.
        */
        id={`${id}-count`}
        label="units"
        min="0"
        onChange={(value) => onChange({ count: value })}
        problem={problems.count}
        selector={known}
        step="1"
        type="number"
        value={count}
      />

      {/*
        The button says what it removes, and the selector is in its name rather
        than on it for the same reason as the fields. "Remove" alone would be
        one identical control per shelf — a list a screen reader cannot tell
        apart, and a test cannot address. It is a word rather than an ×, because
        an icon-only control is the same defect twice: unreadable and untestable
        without reaching for a class name.
      */}
      <button className="service__remove" onClick={onRemove} type="button">
        <span>Remove</span> <span className="visually-hidden">{known}</span>
      </button>
    </li>
  );
}
