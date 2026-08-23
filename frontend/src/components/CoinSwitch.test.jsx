import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import CoinSwitch from './CoinSwitch';

/**
 * The wiring, and nothing about tills: that the switch is called after the coin
 * it governs, that it shows the state it was given, and that flipping it says
 * which way it went. What the drawer does with that is the drawer's own test.
 */
const coinSwitch = (props = {}) =>
  render(
    <CoinSwitch
      accepted
      denomination="0.50"
      id="accepts-0.50"
      onToggle={() => {}}
      {...props}
    />,
  );

describe('CoinSwitch', () => {
  it('is named after the coin it governs, and shows whether it is taken', () => {
    coinSwitch();

    expect(screen.getByRole('checkbox', { name: '0.50 — accepted' })).toBeChecked();
  });

  it('shows a denomination the machine is refusing as off', () => {
    coinSwitch({ accepted: false });

    expect(screen.getByRole('checkbox', { name: '0.50 — accepted' })).not.toBeChecked();
  });

  /**
   * It reports the state it moved to rather than that it was clicked. A caller
   * told only "something happened" has to keep its own copy of the answer to
   * work out which way, and two copies of one boolean is how they drift.
   */
  it('says which way it was flipped', async () => {
    const onToggle = vi.fn();
    const user = userEvent.setup();
    coinSwitch({ accepted: false, onToggle });

    await user.click(screen.getByRole('checkbox', { name: '0.50 — accepted' }));

    expect(onToggle).toHaveBeenCalledWith(true);
  });
});
