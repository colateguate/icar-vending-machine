import js from '@eslint/js';
import jsxA11y from 'eslint-plugin-jsx-a11y';
import react from 'eslint-plugin-react';
import reactHooks from 'eslint-plugin-react-hooks';
import globals from 'globals';

// ESLint 9, not 10. Ten is out, but eslint-plugin-react (7.37) and
// eslint-plugin-jsx-a11y (6.10) both cap their peer range at ^9 — only
// react-hooks has moved. Two of the three plugins would be unsupported, so the
// version floor here is set by the slowest plugin rather than by the newest
// release, and it moves when they do.
//
// jsx-a11y earns its place twice over: an accessible control is also a
// queryable one. Testing Library finds elements by role and accessible name, so
// a rule that refuses an unnamed button is the same rule that keeps the test
// suite writable. It is a lint rule doing test-quality work.
export default [
  { ignores: ['dist/', 'coverage/'] },

  js.configs.recommended,
  react.configs.flat.recommended,
  react.configs.flat['jsx-runtime'],
  reactHooks.configs.flat['recommended-latest'],
  jsxA11y.flatConfigs.recommended,

  {
    files: ['**/*.{js,jsx}'],
    languageOptions: {
      ecmaVersion: 'latest',
      sourceType: 'module',
      globals: globals.browser,
      parserOptions: { ecmaFeatures: { jsx: true } },
    },
    settings: { react: { version: 'detect' } },
  },

  {
    files: ['**/*.jsx'],
    rules: {
      // React 19 removed propTypes: the runtime ignores them silently, so this
      // rule would demand boilerplate that nothing executes. React's own advice
      // is TypeScript instead, and this project decided against that (README,
      // "Not built"). What keeps props honest here is the component test plus
      // the fact that services/ owns the only shape arriving from outside.
      // Defaults belong in ES6 parameters — defaultProps went the same way.
      'react/prop-types': 'off',
    },
  },

  // Config files and build-time scripts run in Node, not in the browser. The
  // distinction matters twice over here: `scripts/` is also the only place in
  // this package where a `.js` file is executed by Node directly, so it is the
  // only place where the `"type": "module"` in package.json has teeth.
  {
    files: ['vite.config.js', 'eslint.config.js', 'scripts/**/*.js'],
    languageOptions: { globals: globals.node },
  },
];
