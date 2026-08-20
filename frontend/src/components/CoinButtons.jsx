/**
 * The coin slot, showing what the machine says it takes.
 *
 * This list used to be a constant written here, duplicating the enum in the
 * published contract, because the machine's state described what it *held* and
 * never what it *took*. It says both now, so the slot is data and this is no
 * longer the second place the coin set is written down.
 *
 * `dispensableAsChange` comes along on each coin and is not read here — nothing
 * about inserting a coin depends on whether it can come back out. The service
 * drawer is where that matters.
 */
export default function CoinButtons({ coins = [], onInsert, disabled = false }) {
  return (
    <section className="coin-slot" aria-labelledby="coin-slot-title">
      <h2 className="coin-slot__title" id="coin-slot-title">
        Insert a coin
      </h2>
      <ul className="coin-slot__coins">
        {coins.map(({ denomination }) => (
          <li key={denomination}>
            <button
              className="coin"
              disabled={disabled}
              onClick={() => onInsert(denomination)}
              type="button"
            >
              {denomination}
            </button>
          </li>
        ))}
      </ul>
    </section>
  );
}
