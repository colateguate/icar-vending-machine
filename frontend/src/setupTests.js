import { cleanup } from '@testing-library/react';
import { afterEach } from 'vitest';

// Adds the DOM matchers (toBeInTheDocument, toBeDisabled, toHaveTextContent…)
// to Vitest's expect, so an assertion can name what the user would notice
// rather than what the object happens to be.
import '@testing-library/jest-dom/vitest';

// Testing Library unmounts after each test by itself ONLY when the test
// framework exposes its hooks as globals. This project imports describe/it/
// expect explicitly instead, which is the clearer half of that trade and costs
// exactly this: without the line below, nothing unmounts, every render piles
// another copy into document.body, and the second test that asks for a landmark
// or a heading fails with "found multiple elements" — a failure that reads like
// a bug in the component and is not. Do not remove it to "simplify" the setup.
afterEach(cleanup);
