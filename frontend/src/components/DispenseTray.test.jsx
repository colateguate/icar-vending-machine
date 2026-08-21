import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import DispenseTray from './DispenseTray';

/**
 * Buying and asking for the money back both leave something in the tray, but
 * not the same something, so the prop is a discriminated shape and the
 * component reads `kind` instead of guessing from which field happens to be
 * present.
 */
const aPurchase = (change) => ({
  kind: 'purchase',
  dispensed: { selector: 'WATER', name: 'Water', price: '0.65', change },
});

describe('DispenseTray', () => {
  it('is empty until something falls into it', () => {
    render(<DispenseTray contents={null} />);

    expect(screen.getByText(/nothing in the tray/i)).toBeVisible();
  });

  it('shows the product that was dispensed and the change that came with it', () => {
    render(
      <DispenseTray
        contents={aPurchase({
          coins: [
            { denomination: '0.10', count: 1 },
            { denomination: '0.25', count: 1 },
          ],
          amount: '0.35',
        })}
      />,
    );

    const tray = screen.getByRole('status', { name: 'Dispense tray' });

    expect(tray).toHaveTextContent('Water');
    expect(tray).toHaveTextContent('0.35');
    expect(screen.getByText('1 × 0.25')).toBeVisible();
    expect(screen.getByText('1 × 0.10')).toBeVisible();
  });

  /**
   * Emptiness is decided by whether there are coins, not by comparing an amount
   * against "0.00". The panel never reasons about what an amount means — it
   * only ever passes the string through.
   */
  it('says there was no change rather than showing an empty list', () => {
    render(<DispenseTray contents={aPurchase({ coins: [], amount: '0.00' })} />);

    expect(screen.getByText(/no change/i)).toBeVisible();
  });

  it('shows the coins that came back when the refund button was pressed', () => {
    render(
      <DispenseTray
        contents={{
          kind: 'return',
          returned: { coins: [{ denomination: '0.25', count: 2 }], amount: '0.50' },
        }}
      />,
    );

    const tray = screen.getByRole('status', { name: 'Dispense tray' });

    expect(tray).toHaveTextContent('0.50');
    expect(screen.getByText('2 × 0.25')).toBeVisible();
    expect(tray).not.toHaveTextContent('Water');
  });
});
