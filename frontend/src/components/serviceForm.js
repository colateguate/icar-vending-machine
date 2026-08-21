/**
 * Turning what the machine reports into what the form edits, and nothing else.
 * Kept apart from the component because it is the one piece of this drawer with
 * no lifecycle: given a machine, it always produces the same starting form.
 */
const countOf = (coins, denomination) =>
  coins.find((coin) => coin.denomination === denomination)?.count ?? 0;

/**
 * A coin bag omits the denominations it holds none of, and the denomination
 * that ran out is exactly the one someone opened this drawer to refill. So the
 * rows come from what the machine *accepts* and the counts are looked up in
 * what it *holds*, defaulting to none.
 */
export function seedForm(products, changeReserve, acceptedCoins) {
  return {
    products: products.map((product) => ({ ...product, count: String(product.count) })),
    coins: acceptedCoins.map(({ denomination, dispensableAsChange }) => ({
      denomination,
      dispensableAsChange,
      count: String(countOf(changeReserve.coins, denomination)),
    })),
  };
}
