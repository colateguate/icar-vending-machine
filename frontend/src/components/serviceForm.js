/**
 * Turning what the machine reports into what the form edits, and nothing else.
 * Kept apart from the component because it is the one piece of this drawer with
 * no lifecycle: given a machine, it always produces the same starting form.
 */
const countOf = (coins, denomination) =>
  coins.find((coin) => coin.denomination === denomination)?.count ?? 0;

/**
 * A coin bag omits the denominations it holds none of, and the denomination that
 * ran out is exactly the one someone opened this drawer to refill. A machine
 * likewise omits the coins it has stopped taking from `acceptedCoins`, and one
 * of those is exactly the one someone opened this drawer to switch back on.
 *
 * So the rows come from what the acceptor can *read* — every denomination this
 * model of machine knows — while the counts are looked up in what it *holds*,
 * defaulting to none, and the switches in what it *takes*. Three questions about
 * one denomination, answered from three places, on one row.
 */
export function seedForm(products, changeReserve, supportedCoins) {
  return {
    // Every row carries an `id`, and it is what the form edits and removes by.
    // A position works right up until a row can disappear, and then it stops
    // working quietly: the fields stay where they are and the values slide up
    // one. For a product already on the shelf the selector is that identity —
    // it is what the machine calls it.
    products: products.map((product) => ({
      ...product,
      id: product.selector,
      price: String(product.price),
      count: String(product.count),
    })),
    coins: supportedCoins.map(({ denomination, dispensableAsChange, enabled }) => ({
      id: denomination,
      denomination,
      dispensableAsChange,
      // The one rename in this file, and it happens here so it happens once:
      // the state calls the flag `enabled`, the form calls it `accepted`,
      // because what the form produces is the `acceptedCoins` the request
      // carries. Reading a row and reading the payload should not need two
      // words for one fact.
      accepted: enabled,
      count: String(countOf(changeReserve.coins, denomination)),
    })),
  };
}
