# Estrenar frontend/e2e con el humo que cubre justo lo que jsdom no puede ver

## Contexto
`frontend/vite.config.js:23` fija `test.css: false`: jsdom no aplica ninguna hoja de estilo. Es la decisión correcta —los componentes se consultan por rol, no por clase, así que parsear CSS sería tiempo gastado en nada— pero deja fuera una clase entera de regresión, y el ticket 17c la encontró de las dos formas posibles.

La barata: `text-transform: uppercase` renombraba regiones y botones en el árbol de accesibilidad y **los 106 tests seguían verdes**, porque jsdom no aplicaba el CSS que rompía el nombre.

La cara: los tres overlays decorativos —`.machine__window::after` (MachinePage.css:59), `.machine__tray::before` (:89) y `.display::before` (MachineDisplay.css:62)— se dibujan encima de controles y solo `pointer-events: none` impide que se traguen los clics. Si alguien borra esa línea, **ningún test se pone rojo**: `userEvent.click` despacha el evento al nodo directamente, sin pasar por el modelo de capas del navegador. La estantería entera quedaría muerta al ratón y la suite lo celebraría en verde.

`frontend/e2e/` existe reservado y vacío desde el ticket 15 precisamente para esto.

## Criterios de aceptación
- [ ] Playwright instalado como dependencia de desarrollo del frontend, con su config, y el `e2e/README.md` actualizado para que deje de decir que no hay ninguna instalada
- [ ] Un test que compra un producto **haciendo clic donde está el producto en pantalla**, no despachando el evento al nodo. Debe ponerse rojo si se quita el `pointer-events: none` de `.machine__window::after` — y hay que comprobarlo quitándolo, no suponerlo
- [ ] Un test que comprueba el nombre accesible de un control contra el árbol del navegador de verdad, de forma que un `text-transform` reintroducido lo ponga rojo. Mismo requisito: demostrado mutando, no afirmado
- [ ] Los tests corren contra la pila levantada, no contra un mock. Reutilizan el contenedor del ticket 13b
- [ ] Queda escrito en el `README.md` de `e2e/` qué pertenece a este nivel y qué no: aquí solo va lo que **necesita** un navegador de verdad. Un caso que Vitest pueda contestar y se escriba aquí es un test lento sin motivo
- [ ] El job de CI del ticket 18 sabe si estos corren o no, y la respuesta está razonada (coste de arrancar navegador frente a lo que cubren)

## Capa
frontend, infra

## Archivos probablemente afectados
- `frontend/e2e/` — hoy solo `README.md`; aquí van los specs y la config (a crear)
- `frontend/package.json` — dependencia y script; hoy no tiene ninguna referencia a Playwright
- `frontend/src/pages/MachinePage.css:59,89` y `frontend/src/components/MachineDisplay.css:62` — los `pointer-events: none` que el humo vigila
- `frontend/src/index.css` — la regla de la casa sobre `text-transform`, que es la otra mitad de lo que se vigila
- `.github/workflows/ci.yml` — si se decide correrlos en CI

## Enfoque sugerido
1. Empezar por el test del overlay, que es el que tiene consecuencia real y demostrable. El del nombre accesible después.
2. Escribir cada uno **mutando primero**: quitar la línea que protege, ver el rojo, devolverla, ver el verde. Un e2e que nunca se vio fallar no vale su coste de arranque.
3. Dejar el listón alto para lo que entra aquí. Dos o tres casos que solo un navegador puede contestar valen más que una suite de humo que duplica lo que Vitest ya cubre más rápido.

(No prescriptivo — el implementador puede divergir si encuentra mejor camino.)

## ADR asociado
Sí, valorarlo — el ADR-0016 dice que la disciplina de capas del frontend la sostiene la revisión y no un Deptrac. Añadir un quinto nivel de test es una decisión con alternativa real: dejarlo cubierto solo por revisión, como hasta ahora. Si se instala Playwright, la decisión y su coste van escritos.

## Depende de
13b

## Prioridad sugerida
baja — cubre un fallo real pero improbable, y necesita el contenedor del 13b antes. Es lo primero que se cae si aprieta el tiempo, y no pasa nada si se cae: el hueco está documentado.

## Notas y referencias
- Lo señaló `frontend-test-quality-reviewer` durante la review previa al push del ticket 17c, y coincide con el hueco que yo mismo había medido: el árbol de accesibilidad hubo que compararlo a mano con Chrome DevTools MCP porque ninguna suite lo hacía.
- `frontend/e2e/README.md` ya dice que este nivel existe para lo que "drives a real browser against a running stack", y que el 13b construye ese stack. Este ticket cumple esa promesa.
- La comprobación de `prefers-reduced-motion` del 17c se hizo emulando el ajuste con Playwright desde MCP. Si Playwright entra como dependencia, ese chequeo puede dejar de ser manual.

## Origen
Detectado durante review-before-push del ticket 17c.
