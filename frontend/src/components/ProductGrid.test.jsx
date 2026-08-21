import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import ProductGrid from './ProductGrid';

const products = [
  { selector: 'WATER', name: 'Water', price: '0.65', count: 5 },
  { selector: 'JUICE', name: 'Orange juice', price: '1.00', count: 0 },
];

describe('ProductGrid', () => {
  /**
   * The whole accessible name, not four `stringContaining` checks. A slot's name
   * is one sentence read aloud in one breath, and asserting the parts separately
   * passes happily on "WATERWater0.655 left" — which is what the browser
   * actually produced until this test said otherwise. What the parts are is
   * already covered; what this pins is that they are still separate words.
   */
  it('shows what is behind the glass as one readable label', () => {
    render(<ProductGrid products={products} onSelect={() => {}} />);

    expect(screen.getByRole('button', { name: /WATER/ })).toHaveAccessibleName(
      'WATER Water 0.65 5 left',
    );
  });

  it('says a slot is sold out in the label itself, not only by being unpressable', () => {
    render(<ProductGrid products={products} onSelect={() => {}} />);

    expect(screen.getByRole('button', { name: /JUICE/ })).toHaveAccessibleName(
      'JUICE Orange juice 1.00 Sold out',
    );
  });

  it('asks for a product by the selector printed next to the slot', async () => {
    const onSelect = vi.fn();
    const user = userEvent.setup();
    render(<ProductGrid products={products} onSelect={onSelect} />);

    await user.click(screen.getByRole('button', { name: /WATER/ }));

    expect(onSelect).toHaveBeenCalledWith('WATER');
  });

  /**
   * An empty slot is refused here rather than by the API on purpose: the panel
   * already knows the count, so pressing a sold-out slot would spend a round
   * trip to be told what the screen was showing all along. The API still
   * refuses it — this is a convenience, never the enforcement.
   */
  it('cannot be asked for a product whose slot is empty', async () => {
    const onSelect = vi.fn();
    const user = userEvent.setup();
    render(<ProductGrid products={products} onSelect={onSelect} />);

    const soldOut = screen.getByRole('button', { name: /JUICE/ });

    expect(soldOut).toBeDisabled();

    await user.click(soldOut);

    expect(onSelect).not.toHaveBeenCalled();
  });

  it('refuses every slot while an action is in flight', () => {
    render(<ProductGrid products={products} onSelect={() => {}} disabled />);

    expect(screen.getByRole('button', { name: /WATER/ })).toBeDisabled();
  });

  it('says the machine is empty rather than rendering nothing at all', () => {
    render(<ProductGrid products={[]} onSelect={() => {}} />);

    expect(screen.getByText(/empty/i)).toBeVisible();
  });
});
