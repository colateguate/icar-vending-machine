/**
 * Whether the machine takes a denomination at all — the technician's half of the
 * coin question, standing next to the count that says how many are in the till.
 * The two are independent on purpose: money already inside a machine that has
 * stopped taking that coin is still money, and the form has to be able to say so.
 *
 * A checkbox rather than `role="switch"`: both are announced usefully, and this
 * one is a field of a form that gets submitted rather than a control that acts
 * the moment it is flipped, which is exactly what a checkbox means.
 *
 * It carries no stylesheet — the row it belongs to is part of the `service`
 * block, and splitting a block across files means reading both to know what a
 * row looks like.
 */
export default function CoinSwitch({ id, denomination, accepted, onToggle }) {
  return (
    <>
      <input
        checked={accepted}
        className="service__switch"
        id={id}
        onChange={(event) => onToggle(event.target.checked)}
        type="checkbox"
      />
      {/*
        The whole label is off-screen now: the denomination always was — six
        switches need six different names, and the count field beside each one
        already prints the figure — and "accepted" moved out of the rows and
        into the column heading the till panel draws once, when reading the
        same word six times down the drawer turned out to be what it looks
        like: noise. A screen reader still hears the full "0.50 — accepted",
        which is also what keeps the browser-level CDP sentinel watching this
        name honest.
      */}
      <label className="service__switch-label visually-hidden" htmlFor={id}>
        {denomination} — accepted
      </label>
    </>
  );
}
