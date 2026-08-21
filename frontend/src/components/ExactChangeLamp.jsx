import './ExactChangeLamp.css';

/**
 * The lamp a physical machine lights when it can no longer give change for
 * everything it sells — the domain's most interesting edge case, wired to a
 * single boolean the API computes.
 *
 * It says its state in words in both directions. A lamp that only glows is
 * invisible to anyone who cannot see it glow, and it is also untestable without
 * reaching for a class name: the same defect showing up twice.
 */
export default function ExactChangeLamp({ lit }) {
  return (
    <p className={lit ? 'lamp lamp--lit' : 'lamp'}>
      <span aria-hidden="true" className="lamp__bulb" />
      {lit ? 'Exact change only' : 'Change available'}
    </p>
  );
}
