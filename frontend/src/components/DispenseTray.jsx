import './DispenseTray.css';

/**
 * The cajetín at the bottom: whatever physically left the machine on the last
 * action, and nothing else. Buying and asking for the money back both put
 * something here, but not the same something, so the prop is a discriminated
 * shape and this reads `kind` rather than guessing from which field happens to
 * be present.
 *
 * Emptiness is decided by whether there are coins, never by comparing an amount
 * against "0.00". Amounts arrive as strings and leave as strings; the panel does
 * not reason about what one means.
 */
function CoinList({ coins }) {
  return (
    <ul className="tray__coins">
      {coins.map(({ denomination, count }) => (
        <li key={denomination}>{`${count} × ${denomination}`}</li>
      ))}
    </ul>
  );
}

function Purchase({ dispensed: { name, price, change } }) {
  return (
    <>
      <p className="tray__item">{`${name} — ${price}`}</p>
      {change.coins.length === 0 ? (
        <p className="tray__change">No change.</p>
      ) : (
        <>
          <p className="tray__change">{`Change ${change.amount}`}</p>
          <CoinList coins={change.coins} />
        </>
      )}
    </>
  );
}

function Refund({ returned }) {
  return (
    <>
      <p className="tray__item">{`Returned ${returned.amount}`}</p>
      <CoinList coins={returned.coins} />
    </>
  );
}

export default function DispenseTray({ contents }) {
  return (
    <div aria-label="Dispense tray" className="tray" role="status">
      {!contents && <p className="tray__empty">Nothing in the tray.</p>}
      {contents?.kind === 'purchase' && <Purchase dispensed={contents.dispensed} />}
      {contents?.kind === 'return' && <Refund returned={contents.returned} />}
    </div>
  );
}
