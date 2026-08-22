/**
 * One editable row of the form. Both halves of the drawer are the same shape —
 * a labelled whole number, sometimes with something to say about it — so they
 * are one component rather than two nearly identical blocks of markup.
 *
 * `note` is attached with `aria-describedby` rather than folded into the label,
 * so the field keeps being called what it is and the warning is still announced.
 *
 * Some rows carry a second control — the till's switch per denomination. It
 * arrives as children rather than as a prop this component understands, because
 * a row that knew what a coin switch was would be a coin row wearing the name of
 * a number field. The row says where such a control goes; what it is stays the
 * caller's business.
 */
export default function CountField({ id, label, count, note, onChange, children }) {
  return (
    <li className={children ? 'service__row service__row--controlled' : 'service__row'}>
      {children}
      <label htmlFor={id}>{label}</label>
      <input
        aria-describedby={note ? `${id}-note` : undefined}
        id={id}
        min="0"
        onChange={(event) => onChange(event.target.value)}
        required
        step="1"
        type="number"
        value={count}
      />
      {note && (
        <p className="service__note" id={`${id}-note`}>
          {note}
        </p>
      )}
    </li>
  );
}
