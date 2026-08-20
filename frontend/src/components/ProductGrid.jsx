/**
 * What is behind the glass. Every slot is one button, labelled with everything
 * a customer reads before pressing it, which is also everything a test needs to
 * find it by name.
 *
 * An empty slot is refused here as a convenience, never as the enforcement: the
 * API refuses it too, and it is the API that decides. The panel only declines to
 * spend a round trip being told what the screen was already showing.
 */
export default function ProductGrid({ products, onSelect, disabled = false }) {
  return (
    <section className="shelf" aria-labelledby="shelf-title">
      <h2 className="shelf__title" id="shelf-title">
        Products
      </h2>
      {products.length === 0 ? (
        <p className="shelf__empty">The machine is empty.</p>
      ) : (
        <ul className="shelf__slots">
          {products.map(({ selector, name, price, count }) => (
            <li className="slot" key={selector}>
              <button
                className="slot__button"
                disabled={disabled || count === 0}
                onClick={() => onSelect(selector)}
                type="button"
              >
                {/*
                  The explicit spaces are load-bearing. JSX drops the whitespace
                  between elements on separate lines, and the accessible name is
                  the concatenation of what is left — so without them a screen
                  reader is handed "WATERWater0.655 left" as a single word.
                */}
                <span className="slot__selector">{selector}</span>{' '}
                <span className="slot__name">{name}</span>{' '}
                <span className="slot__price">{price}</span>{' '}
                <span className="slot__count">{count === 0 ? 'Sold out' : `${count} left`}</span>
              </button>
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}
