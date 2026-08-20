/**
 * The single screen of the panel.
 *
 * Today it is scaffolding: ticket 16 gives it a way to reach the API, ticket 17
 * fills it with the coin buttons and the product grid, and ticket 17b adds the
 * service drawer. What is already true is its shape — a page composes and renders, and
 * learns the machine's state from a hook rather than from the network.
 * `CLAUDE.md` § "Frontend architecture" has the table this obeys.
 */
export default function MachinePage() {
  return (
    <main>
      <h1>Vending machine</h1>
      <p>
        Scaffolding. The controls arrive with the panel; nothing here reaches the API yet.
      </p>
    </main>
  );
}
