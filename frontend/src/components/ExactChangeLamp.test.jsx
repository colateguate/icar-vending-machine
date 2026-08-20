import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import ExactChangeLamp from './ExactChangeLamp';

/**
 * The lamp says its state in words in both directions. A lamp that only lights
 * up is invisible to anyone who cannot see it light up, and it is also
 * untestable without reaching for a class name — the same defect twice.
 */
describe('ExactChangeLamp', () => {
  it('lights when the machine can no longer give change for everything it sells', () => {
    render(<ExactChangeLamp lit />);

    expect(screen.getByText('Exact change only')).toBeVisible();
  });

  it('says change is available rather than saying nothing at all', () => {
    render(<ExactChangeLamp lit={false} />);

    expect(screen.getByText('Change available')).toBeVisible();
  });
});
