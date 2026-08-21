import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import ReturnCoinButton from './ReturnCoinButton';

/**
 * Labelled RETURN-COIN, in the brief's own words. The vocabulary the challenge
 * uses is the vocabulary the domain uses and the CLI accepts, and the button a
 * customer presses is the last place worth inventing a synonym.
 */
describe('ReturnCoinButton', () => {
  it('is the single refund path, and says so in the domain\'s own words', () => {
    render(<ReturnCoinButton onReturn={() => {}} />);

    expect(screen.getByRole('button', { name: 'RETURN-COIN' })).toBeVisible();
  });

  it('asks for the coins back when pressed', async () => {
    const onReturn = vi.fn();
    const user = userEvent.setup();
    render(<ReturnCoinButton onReturn={onReturn} />);

    await user.click(screen.getByRole('button', { name: 'RETURN-COIN' }));

    expect(onReturn).toHaveBeenCalledTimes(1);
  });

  it('cannot be pressed while an action is in flight', async () => {
    const onReturn = vi.fn();
    const user = userEvent.setup();
    render(<ReturnCoinButton onReturn={onReturn} disabled />);

    await user.click(screen.getByRole('button', { name: 'RETURN-COIN' }));

    expect(onReturn).not.toHaveBeenCalled();
  });
});
