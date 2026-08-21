import react from '@vitejs/plugin-react';
import { defineConfig } from 'vitest/config';

// In production the panel and the API share an origin: ticket 13b puts nginx in
// front of both, so CORS never enters the picture. Development is the case that
// would otherwise differ, and the dev server forwards /api to the backend rather
// than teaching the API to trust a second origin. One less thing that is true in
// development and false in production.
const backend = process.env.VITE_DEV_API_TARGET ?? 'http://localhost:8000';

export default defineConfig({
  plugins: [react()],
  server: {
    proxy: {
      '/api': { target: backend, changeOrigin: true },
    },
  },
  test: {
    /*
     * Vitest owns `src/`, Playwright owns `e2e/`, and saying so is not tidiness:
     * the default glob reaches every `*.spec.js` in the package, so the browser
     * specs were picked up by `npm test` and failed on the first `beforeEach`
     * Playwright had not set up. The two runners are told apart by folder and
     * by suffix — `.test.jsx` here, `.spec.js` there — and neither is left to
     * a default.
     */
    include: ['{src,scripts}/**/*.test.{js,jsx}'],
    environment: 'jsdom',
    setupFiles: ['./src/setupTests.js'],
    // Components are queried by role, never by class, so the stylesheet has
    // nothing to say to a test and parsing it is time spent for nothing.
    css: false,
  },
});
