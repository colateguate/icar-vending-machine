import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import CountField from './CountField';

/**
 * What is tested here is the wiring: that the label reaches the input, that a
 * note becomes a description rather than part of the name, and that what was
 * typed comes back out. Which fields the drawer builds out of this, and what it
 * does with them, is the drawer's own test.
 *
 * Rendered inside a list because the field is a row and `<li>` outside `<ul>` is
 * markup no browser would be given.
 */
const field = (props = {}) =>
  render(
    <ul>
      <CountField count="7" id="coins-0.25" label="0.25 — coins" onChange={() => {}} {...props} />
    </ul>,
  );

describe('CountField', () => {
  it('is found by its label, and shows the count it was given', () => {
    field();

    expect(screen.getByRole('spinbutton', { name: '0.25 — coins' })).toHaveValue(7);
  });

  it('hands back what was typed, without deciding anything about it', async () => {
    const onChange = vi.fn();
    const user = userEvent.setup();
    field({ count: '', onChange });

    await user.type(screen.getByRole('spinbutton', { name: '0.25 — coins' }), '3');

    expect(onChange).toHaveBeenCalledWith('3');
  });

  /**
   * A note is a description, not part of the name: the field goes on being
   * called "0.25 — coins" and the warning is announced alongside it. Folded into
   * the label it would make the field's own name a sentence.
   */
  it('announces a note without letting it swallow the label', () => {
    field({ note: 'Never given back as change.' });

    const input = screen.getByRole('spinbutton', { name: '0.25 — coins' });

    expect(input).toHaveAccessibleDescription('Never given back as change.');
  });

  it('leaves no dangling description when there is nothing to add', () => {
    field();

    expect(screen.getByRole('spinbutton', { name: '0.25 — coins' })).toHaveAccessibleDescription('');
  });

  /**
   * Some rows carry a second control — the till's switch per denomination. It
   * arrives as children rather than as a prop this component understands,
   * because a row that knew what a coin switch was would be a coin row wearing
   * the name of a number field.
   */
  it('makes room for another control belonging to the same row', () => {
    field({ children: <button type="button">Something else</button> });

    const row = screen.getByRole('listitem');

    expect(row).toContainElement(screen.getByRole('button', { name: 'Something else' }));
    expect(row).toContainElement(screen.getByRole('spinbutton', { name: '0.25 — coins' }));
  });
});
