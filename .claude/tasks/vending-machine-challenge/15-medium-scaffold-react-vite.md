# Montar el esqueleto del frontend React + Vite (JavaScript) con Vitest

## Contexto
Frontend deliberadamente fino: React + JavaScript (sin TypeScript — decisión de no crear deuda en la parte no evaluada), Vite como bundler, Vitest + Testing Library para tests de componente. Cero lógica de negocio: todo cálculo vive en la API. La estructura queda preparada para añadir E2E Playwright más adelante sin reorganizar.

## Criterios de aceptación
- [ ] `frontend/package.json` con react, vite, vitest, @testing-library/react, eslint
- [ ] `npm run dev`, `npm run build`, `npm test` funcionan
- [ ] Estructura: `src/components/`, `src/api/`, `src/App.jsx` — y carpeta `e2e/` reservada (`.gitkeep` + nota) para Playwright futuro
- [ ] Un componente placeholder renderizado con su test de humo verde
- [ ] Proxy de dev de Vite hacia el backend local (evita CORS en desarrollo)
- [ ] ESLint sin errores

## Capa
frontend

## Archivos probablemente afectados
- `frontend/package.json`, `frontend/vite.config.js`, `frontend/index.html` (a crear)
- `frontend/src/App.jsx`, `frontend/src/main.jsx` (a crear)
- `frontend/src/components/`, `frontend/src/api/` (estructura)
- `frontend/e2e/.gitkeep` (a crear — reserva para Playwright)

## Enfoque sugerido
1. `npm create vite@latest` con plantilla react (JS).
2. Añadir vitest + RTL y el primer test de humo.
3. Limpiar el boilerplate de la plantilla (logos, CSS demo) en el mismo ticket.

(No prescriptivo — el implementador puede divergir si encuentra mejor camino.)

## ADR asociado
No — la decisión SPA-sobre-API ya está tomada; solo se materializa. (Si al cerrar el proyecto el frontend creciera, valorar el ADR-0016 del blueprint.)

## Depende de
— (paralelizable; solo 16 necesita la API viva)

## Prioridad sugerida
media — el backend puntúa más; timebox estricto.

## Notas y referencias
- `clean-code-reviewer` vigila: sin lógica de negocio en el cliente, sin console.log, componentes <200 líneas.

## Origen
Desglose de backlog — sesión PM de 2026-08-18.
