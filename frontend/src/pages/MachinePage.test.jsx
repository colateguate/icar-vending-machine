import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import MachinePage from './MachinePage';

/**
 * The scaffolding's only test, and it is deliberately about structure rather
 * than about the placeholder copy it happens to render today: the heading and
 * the main landmark are what ticket 17 builds inside, so both assertions
 * survive the panel arriving. A test that pinned the words "coming soon" would
 * be deleted by the next ticket, which is the sign it was never worth writing.
 *
 * Queried by role and accessible name because that is how the panel will be
 * queried from here on. If a control cannot be found this way, the markup is
 * what needs fixing.
 */
describe('MachinePage', () => {
  it('names the machine it operates', () => {
    render(<MachinePage />);

    expect(screen.getByRole('heading', { level: 1, name: 'Vending machine' })).toBeVisible();
  });

  it('puts the panel in a main landmark', () => {
    render(<MachinePage />);

    expect(screen.getByRole('main')).toBeVisible();
  });
});
