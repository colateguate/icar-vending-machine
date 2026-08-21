import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import CoinButtons from './CoinButtons';

/**
 * The denominations are written out in the assertions rather than read back from
 * the fixture. Deriving the expectation from the data under test would assert
 * that the fixture equals itself; this states what the machine takes, which is
 * `docs/openapi.yaml` § Denomination.
 */
const accepted = [
  { denomination: '0.05', dispensableAsChange: true },
  { denomination: '0.10', dispensableAsChange: true },
  { denomination: '0.25', dispensableAsChange: true },
  { denomination: '1.00', dispensableAsChange: false },
];

describe('CoinButtons', () => {
  it('offers the four coins the machine accepts, labelled as the amounts they are', () => {
    render(<CoinButtons coins={accepted} onInsert={() => {}} />);

    for (const coin of ['0.05', '0.10', '0.25', '1.00']) {
      expect(screen.getByRole('button', { name: coin })).toBeVisible();
    }
  });

  /**
   * The slot is driven by what the machine said, not by a list this component
   * carries. A machine that took two coins would show two — and this is the only
   * test that can tell a data-driven slot from a hardcoded one, because with the
   * real catalogue both look identical.
   */
  it('offers exactly what the machine says it takes, and nothing more', () => {
    render(
      <CoinButtons
        coins={[
          { denomination: '0.10', dispensableAsChange: true },
          { denomination: '0.25', dispensableAsChange: true },
        ]}
        onInsert={() => {}}
      />,
    );

    expect(screen.getAllByRole('button')).toHaveLength(2);
    expect(screen.queryByRole('button', { name: '1.00' })).toBeNull();
  });

  /**
   * The panel renders before the machine has said anything, so an empty slot is
   * a real state rather than a broken one — which is why the default is `[]` and
   * not an exception. The page passes `?? []` today; this makes the component
   * survive on its own the day something forgets to.
   */
  it('shows an empty slot rather than falling over when told nothing', () => {
    render(<CoinButtons onInsert={() => {}} />);

    expect(screen.getByRole('heading', { name: 'Insert a coin' })).toBeVisible();
    expect(screen.queryAllByRole('button')).toHaveLength(0);
  });

  it('hands the pressed coin back as the decimal string it is', async () => {
    const onInsert = vi.fn();
    const user = userEvent.setup();
    render(<CoinButtons coins={accepted} onInsert={onInsert} />);

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
    render(<CoinButtons coins={accepted} disabled onInsert={onInsert} />);

    await user.click(screen.getByRole('button', { name: '0.25' }));

    expect(onInsert).not.toHaveBeenCalled();
  });
});
