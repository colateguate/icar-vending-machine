# Panel: sección de till con toggle habilitar/deshabilitar por moneda

## Contexto

El formulario de servicio siembra las filas del till desde `acceptedCoins` (`frontend/src/components/serviceForm.js:15-24`) y solo edita counts (`ServiceDrawer.jsx:85-90`). Con el ticket 03, el estado publica `supportedCoins` (las 6 con `enabled`): el formulario debe sembrar de ahí, mostrar un toggle por denominación y mandar el payload con la forma que el 03 haya fijado. Los botones de insertar moneda NO se tocan: siguen leyendo `acceptedCoins` (`CoinButtons.jsx:15-34`), que conserva su significado.

## Criterios de aceptación

- [ ] La sección Till del drawer pinta las 6 denominaciones desde `supportedCoins`, cada una con toggle (checkbox o switch con nombre accesible) + input de count.
- [ ] Deshabilitar una moneda fuerza su count a 0 en el formulario (semántica K3: no puede cargarse apagada) — el 422 del servidor queda de red, no de primera línea.
- [ ] El payload enviado casa exactamente con la forma del 03 (recordar el strip deliberado de campos extra, `ServiceDrawer.jsx:102-104`, y su test `ServiceDrawer.test.jsx:251-266` — renegociarlos, no borrarlos sin mirar).
- [ ] Tras habilitar 0.50 y guardar: el botón 0.50 aparece en el panel sin tocar `CoinButtons` (la respuesta de la escritura ES el estado nuevo).
- [ ] El code 422 nuevo de "moneda deshabilitada" tiene mensaje en el `MESSAGES` map (`MachineDisplay.jsx:47-83`) y los comentarios de alcanzabilidad de `unsupported_coin` (`:48-52`) se actualizan: siguen siendo inalcanzables desde botones, pero el till ya no siembra de `acceptedCoins`.
- [ ] Tests de componente por rol y nombre accesible (el toggle sin nombre ES el hallazgo); costura de mock = módulo `services/`, nunca `global.fetch`.
- [ ] Fixtures de e2e revisadas (`frontend/e2e/machine.spec.js:15-21`); spec de navegador nueva SOLO si aparece algo que jsdom no ve (el listón de `frontend/e2e/README.md`).

## Capa

frontend

## Repo

icar-vending-machine

## Archivos probablemente afectados

- `frontend/src/components/serviceForm.js:6-24` — sembrar de `supportedCoins`
- `frontend/src/components/ServiceDrawer.jsx:85-107,157-170` — toggle, forzado de count, payload
- `frontend/src/components/MachineDisplay.jsx:47-83` — code nuevo + comentarios
- `frontend/src/services/machineApi.js:41-43` — si la firma de service cambia de forma
- `frontend/src/hooks/useMachine.js:115-120` — ídem
- Tests: `ServiceDrawer.test.jsx`, `MachinePage.test.jsx`, `machineApi.test.js`, `useMachine.test.js` (fixtures con 4 denominaciones literales)

## Enfoque sugerido

1. Rojo: test de componente — la sección till muestra las 6 con su estado y el toggle apaga el count.
2. Sembrar de `supportedCoins`; el resto del drawer no cambia de patrón.
3. Verificación activa en navegador (MCP Chrome DevTools) contra el backend real del 03.

(No prescriptivo.)

## ADR asociado

No — aplica ADR-0016 (capas del panel) sin cambiarlo.

## Depende de

03

## Prioridad sugerida

media — sin él la feature no es usable, pero el contrato ya la sirve por curl.

## Notas y referencias

- Spec: [../../../docs/specs/2026-08-21-configurable-coins-and-catalogue-design.md](../../../docs/specs/2026-08-21-configurable-coins-and-catalogue-design.md)
- Hallazgo F1: el panel ya es data-driven — no hay hardcodes de monedas en producción; los 12+ literales están en tests/fixtures.

## Origen

Sesión PM de 2026-08-21 — spec `docs/specs/2026-08-21-configurable-coins-and-catalogue-design.md`
