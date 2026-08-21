import MachinePage from './pages/MachinePage';

/**
 * Layout and composition, and deliberately nothing else. Remote state belongs
 * to hooks/useMachine — an App that starts holding what came back from the API
 * is the first step towards a component tree where everyone fetches a little.
 */
export default function App() {
  return <MachinePage />;
}
