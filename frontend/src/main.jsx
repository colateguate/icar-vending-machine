import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';

import App from './App';

// The palette, the scale and the reset. Every block's own stylesheet is
// imported by the component that renders it, so a component and its skin are
// added, moved and deleted together; this one is global by definition and is
// the only stylesheet without a component to belong to.
import './index.css';

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <App />
  </StrictMode>,
);
