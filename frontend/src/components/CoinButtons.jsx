/**
 * The coin slot.
 *
 * The four denominations are a constant here rather than something read off the
 * machine's state, and that is forced rather than chosen: the state says what
 * the machine *holds*, never what it *takes*. So this list duplicates the enum
 * in the published contract and the two have to move together — the price of an
 * API that never says which coins it accepts.
 */
const ACCEPTED = ['0.05', '0.10', '0.25', '1.00'];

export default function CoinButtons({ onInsert, disabled = false }) {
  return (
    <section className="coin-slot" aria-labelledby="coin-slot-title">
      <h2 className="coin-slot__title" id="coin-slot-title">
        Insert a coin
      </h2>
      <ul className="coin-slot__coins">
        {ACCEPTED.map((coin) => (
          <li key={coin}>
            <button
              className="coin"
              disabled={disabled}
              onClick={() => onInsert(coin)}
              type="button"
            >
              {coin}
            </button>
          </li>
        ))}
      </ul>
    </section>
  );
}
