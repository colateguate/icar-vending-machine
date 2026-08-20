import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import CoinButtons from './CoinButtons';

/**
 * The four denominations are written out here rather than imported from the
 * component. Asserting against the component's own constant would pass no
 * matter what that constant said; this states the contract instead, and the
 * contract is `docs/openapi.yaml` § Denomination.
 */
describe('CoinButtons', () => {
  it('offers the four coins the machine accepts, labelled as the amounts they are', () => {
    render(<CoinButtons onInsert={() => {}} />);

    for (const coin of ['0.05', '0.10', '0.25', '1.00']) {
      expect(screen.getByRole('button', { name: coin })).toBeVisible();
    }
  });

  it('hands the pressed coin back as the decimal string it is', async () => {
    const onInsert = vi.fn();
    const user = userEvent.setup();
    render(<CoinButtons onInsert={onInsert} />);

    await user.click(screen.getByRole('button', { name: '0.25' }));

    expect(onInsert).toHaveBeenCalledWith('0.25');
  });

  /**
   * The slot is the easiest place to double-click, and there is no in-flight
   * deduplication anywhere below this (ADR-0016), so refusing the second press
   * is the only thing standing between one coin and two.
   */
  it('takes no coin while an action is already in flight', async () => {
    const onInsert = vi.fn();
    const user = userEvent.setup();
    render(<CoinButtons onInsert={onInsert} disabled />);

    await user.click(screen.getByRole('button', { name: '0.25' }));

    expect(onInsert).not.toHaveBeenCalled();
  });
});
