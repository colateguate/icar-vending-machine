import CoinButtons from '../components/CoinButtons';
import DispenseTray from '../components/DispenseTray';
import ExactChangeLamp from '../components/ExactChangeLamp';
import MachineDisplay from '../components/MachineDisplay';
import ProductGrid from '../components/ProductGrid';
import ReturnCoinButton from '../components/ReturnCoinButton';
import ServiceDrawer from '../components/ServiceDrawer';
import { useMachine } from '../hooks/useMachine';

import './MachinePage.css';

/**
 * The single screen of the panel: it asks the hook for the machine, composes the
 * parts and hands each one the props it uses. It decides nothing about vending.
 *
 * The markup is the anatomy of a real cabinet — body, window, control column,
 * tray, and the service door down the side — because ticket 17c dresses it and
 * should be able to do so with a stylesheet and nothing else. Structure now is
 * what buys a skin later that cannot break behaviour.
 */
export default function MachinePage() {
  const { machine, error, tray, loading, busy, insertCoin, purchase, returnCoins, service } =
    useMachine();

  // Nothing is operable until there is a machine to operate, and nothing is
  // operable while it is answering: without in-flight deduplication below this
  // page, the lock is the mitigation rather than a nicety (ADR-0016).
  const locked = loading || busy || machine === null;

  // A machine taking no coin cannot be paid, so the customer's half of it is
  // dead rather than merely quiet: left live, a product button answers "insert
  // more" at a slot that accepts nothing. Nothing is trapped by saying so — a
  // service visit returns whatever was inserted before it applies anything, so
  // a machine out of service has no money behind the glass. The technician's
  // door is deliberately not part of this: it is the way back.
  const outOfService = machine?.outOfService ?? false;

  return (
    <main className="machine">
      <h1 className="machine__brand">Vending machine</h1>

      <div className="machine__body">
        <div className="machine__window">
          <ProductGrid
            disabled={locked || outOfService}
            onSelect={purchase}
            products={machine?.products ?? []}
          />
        </div>

        <div className="machine__column">
          <MachineDisplay
            amount={machine?.insertedCoins.amount ?? '0.00'}
            error={error}
            outOfService={outOfService}
          />
          {/*
            The lamp is left out entirely rather than shown unlit, because both
            of the things it can say are wrong here. A machine taking no coin
            reports exactChangeOnly — a till it may not pay out of cannot give
            change — so lighting it would be true and would point at a cause
            that is not the cause; and unlit it would read "Change available",
            which is the opposite of true. What it has no state for is the
            question not applying, so it does not get asked.
          */}
          {!outOfService && <ExactChangeLamp lit={machine?.exactChangeOnly ?? false} />}
          <CoinButtons
            coins={machine?.acceptedCoins ?? []}
            disabled={locked}
            onInsert={insertCoin}
          />
          <ReturnCoinButton disabled={locked || outOfService} onReturn={returnCoins} />
        </div>
      </div>

      <div className="machine__tray">
        <DispenseTray contents={tray} />
      </div>

      {/*
        The two coin lists go to two different halves of the screen, and this is
        the only place that could cross them. The buttons show what the machine
        takes — offering a customer a coin the slot would refuse is a lie the
        panel must not tell. The drawer shows everything the acceptor can read,
        because a form seeded from what the machine takes could never switch a
        denomination back on.
      */}
      <ServiceDrawer
        changeReserve={machine?.changeReserve ?? { coins: [], amount: '0.00' }}
        disabled={locked}
        onService={service}
        products={machine?.products ?? []}
        supportedCoins={machine?.supportedCoins ?? []}
      />
    </main>
  );
}
