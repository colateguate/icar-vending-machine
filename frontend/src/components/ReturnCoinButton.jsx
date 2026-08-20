/**
 * The single refund path, labelled in the brief's own words. The vocabulary the
 * challenge uses is the vocabulary the domain and the CLI use, and the button a
 * customer presses is the last place worth inventing a synonym.
 */
export default function ReturnCoinButton({ onReturn, disabled = false }) {
  return (
    <button className="return-coin" disabled={disabled} onClick={onReturn} type="button">
      RETURN-COIN
    </button>
  );
}
