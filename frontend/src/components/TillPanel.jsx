import CoinSwitch from './CoinSwitch';
import CountField from './CountField';

/**
 * The Coins half of the service form: one row per denomination the acceptor can
 * read, with the switch that says whether this machine takes it and the count
 * of how many are in the till.
 *
 * What is worth saying about a row beyond its number, and only where it is not
 * obvious. A denomination the machine has stopped taking is the important one:
 * the coins are still in there, they count towards nothing, and the person
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

export default function TillPanel({ coins, disabled, onChange }) {
  return (
    <fieldset className="service__group" disabled={disabled}>
      <legend className="visually-hidden">Coins</legend>
      {/*
        Said once, over the column, instead of once per row: six checkboxes each
        labelled "accepted" was the same word six times down the drawer. The
        headings are aria-hidden because they are redundant to a screen reader —
        every switch still carries "<coin> — accepted" in its own name, and a
        heading read out between rows would be noise on top of names that
        already say it.
      */}
      <p aria-hidden="true" className="service__till-head">
        <span>Taken</span>
        <span>In till</span>
      </p>
      <ul className="service__rows">
        {coins.map((coin) => (
          <CountField
            count={coin.count}
            id={`coins-${coin.denomination}`}
            key={coin.denomination}
            label={`${coin.denomination} — coins`}
            note={noteFor(coin)}
            onChange={(value) => onChange(coin.id, { count: value })}
          >
            <CoinSwitch
              accepted={coin.accepted}
              denomination={coin.denomination}
              id={`accepts-${coin.denomination}`}
              onToggle={(accepted) => onChange(coin.id, { accepted })}
            />
          </CountField>
        ))}
      </ul>
    </fieldset>
  );
}
