# Montar el esqueleto del frontend React + Vite (JavaScript) con Vitest

## Contexto
Frontend deliberadamente fino: React + JavaScript (sin TypeScript — decisión de no crear deuda en la parte no evaluada), Vite como bundler, Vitest + Testing Library para tests de componente. Cero lógica de negocio: todo cálculo vive en la API. La estructura queda preparada para añadir E2E Playwright más adelante sin reorganizar.

Las capas y su dirección de dependencia están decididas en `CLAUDE.md` § "Frontend architecture — the layer rule" y razonadas en [ADR-0016](../../../docs/adr/0016-frontend-layers-and-no-data-library.md): este ticket las materializa, no las re-litiga. La regla que gobierna todo lo demás es una sola: **`components/` no conoce `services/`**.

## Criterios de aceptación
- [ ] `frontend/package.json` con react, vite, vitest, jsdom, @testing-library/react, @testing-library/user-event, eslint. Versiones comprobadas a agosto de 2026: React 19.2.x, Vite 8.x (exige Node 20.19+ / 22.12+), Node 22 LTS — el mismo `node:22-alpine` que pedirá el ticket 13b
- [ ] `npm run dev`, `npm run build`, `npm test` y `npm run lint` funcionan
- [ ] Estructura: `src/pages/`, `src/components/`, `src/hooks/`, `src/services/`, `src/App.jsx`, `src/main.jsx` — y carpeta `e2e/` reservada (`.gitkeep` + nota) para Playwright futuro
- [ ] **Sin librería de datos**: ni TanStack Query, ni SWR, ni axios. `fetch` nativo detrás de `services/` (ADR-0016)
- [ ] Un componente placeholder renderizado con su test de humo verde, consultado **por rol y nombre accesible**, no por `data-testid`
- [ ] Proxy de dev de Vite hacia el backend local (evita CORS en desarrollo), **comprobado con una petición real a través del dev server**, no solo escrito en la config
- [ ] ESLint 9 en **flat config** (`eslint.config.js`) con `eslint-plugin-react`, `eslint-plugin-react-hooks` y `eslint-plugin-jsx-a11y`, sin errores. El de accesibilidad no es decoración: RTL consulta por rol, así que el markup accesible y el testeable son el mismo markup

## Capa
frontend

## Archivos probablemente afectados
- `frontend/package.json`, `frontend/vite.config.js`, `frontend/eslint.config.js`, `frontend/index.html` (a crear)
- `frontend/src/App.jsx`, `frontend/src/main.jsx` (a crear)
- `frontend/src/pages/`, `frontend/src/components/`, `frontend/src/hooks/`, `frontend/src/services/` (estructura)
- `frontend/e2e/.gitkeep` (a crear — reserva para Playwright)
- `.gitignore` — ya cubre `frontend/node_modules/`, `frontend/dist/` y `frontend/coverage/`; comprobar que también ignora `frontend/.env*` antes de que exista un `.env` que ignorar

## Enfoque sugerido
1. `npm create vite@latest` con plantilla react (JS).
2. Añadir vitest + RTL y el primer test de humo.
3. Limpiar el boilerplate de la plantilla (logos, CSS demo) en el mismo ticket.

(No prescriptivo — el implementador puede divergir si encuentra mejor camino.)

## ADR asociado
No — [ADR-0016](../../../docs/adr/0016-frontend-layers-and-no-data-library.md) ya registra las capas y la ausencia de librería de datos. Este ticket las aplica.

## Depende de
— (paralelizable; solo 16 necesita la API viva)

## Prioridad sugerida
media — el backend puntúa más; timebox estricto.

## Notas y referencias
- Roster de revisión para diffs de frontend: `security-reviewer` · `frontend-architecture-reviewer` · `clean-code-reviewer` · `frontend-test-quality-reviewer`. Los de backend (`architecture-reviewer`, `test-quality-reviewer`) **no** se lanzan aquí: sus rúbricas son Deptrac y PHPUnit, y sobre React no dan falsos positivos sino ruido. La regla está en `review-before-push`.
- `clean-code-reviewer` vigila: sin lógica de negocio en el cliente, sin console.log, componentes <200 líneas.
- Los dos agentes nuevos se cargan al arrancar la sesión: si acaban de crearse, hay que reiniciar antes de poder invocarlos.

## Origen
Desglose de backlog — sesión PM de 2026-08-18.
