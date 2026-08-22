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
        The denomination is in the name and not on the screen: six switches need
        six different names, and printing the figure here would put it twice on
        every row, next to the count field that says it too.
      */}
      <label className="service__switch-label" htmlFor={id}>
        <span className="visually-hidden">{denomination} —</span> <span>accepted</span>
      </label>
    </>
  );
}
