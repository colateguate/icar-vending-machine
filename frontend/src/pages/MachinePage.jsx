import CoinButtons from '../components/CoinButtons';
import DispenseTray from '../components/DispenseTray';
import ExactChangeLamp from '../components/ExactChangeLamp';
import MachineDisplay from '../components/MachineDisplay';
import ProductGrid from '../components/ProductGrid';
import ReturnCoinButton from '../components/ReturnCoinButton';
import { useMachine } from '../hooks/useMachine';

/**
 * The single screen of the panel: it asks the hook for the machine, composes the
 * parts and hands each one the props it uses. It decides nothing about vending.
 *
 * The markup is the anatomy of a real cabinet — body, window, control column,
 * tray — because ticket 17c dresses it and should be able to do so with a
 * stylesheet and nothing else. Structure now is what buys a skin later that
 * cannot break behaviour.
 */
export default function MachinePage() {
  const { machine, error, tray, loading, busy, insertCoin, purchase, returnCoins } = useMachine();

  // Nothing is operable until there is a machine to operate, and nothing is
  // operable while it is answering: without in-flight deduplication below this
  // page, the lock is the mitigation rather than a nicety (ADR-0016).
  const locked = loading || busy || machine === null;

  return (
    <main className="machine">
      <h1 className="machine__brand">Vending machine</h1>

      <div className="machine__body">
        <div className="machine__window">
          <ProductGrid
            disabled={locked}
            onSelect={purchase}
            products={machine?.products ?? []}
          />
        </div>

        <div className="machine__column">
          <MachineDisplay amount={machine?.insertedCoins.amount ?? '0.00'} error={error} />
          <ExactChangeLamp lit={machine?.exactChangeOnly ?? false} />
          <CoinButtons disabled={locked} onInsert={insertCoin} />
          <ReturnCoinButton disabled={locked} onReturn={returnCoins} />
        </div>
      </div>

      <div className="machine__tray">
        <DispenseTray contents={tray} />
      </div>
    </main>
  );
}
