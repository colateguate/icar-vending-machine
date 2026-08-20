# Construir el panel de la máquina con tests de componente

## Contexto
La única pantalla del frontend: el panel de una máquina expendedora operable por el evaluador. Botones de moneda, selección de producto, devolución, display del importe insertado, lamparita "EXACT CHANGE ONLY" (alimentada por `exactChangeOnly` de la API — el error convertido en feature), y un cajón de servicio para reabastecer. Sin lógica de negocio: el panel muestra estado y despacha acciones.

## Criterios de aceptación
- [ ] Componentes: CoinButtons (0.05/0.10/0.25/1), ProductGrid (selector+precio+stock, deshabilitado sin stock), InsertedDisplay, ReturnCoinButton, ExactChangeLamp, ServiceDrawer (form de reabastecimiento), DispenseTray (producto y cambio de la última acción)
- [ ] Estados de la API reflejados: loading, error (mensaje según `code` del problem+json), éxito
- [ ] Comprar sin fondos suficientes muestra el mensaje correcto; comprar sin cambio componible muestra el de exact change — ambos con test
- [ ] `hooks/useMachine.js` es el **único** módulo que llama a `services/`: expone el estado, las acciones, el error y un flag de acción en curso. Los componentes reciben props y emiten callbacks
- [ ] `pages/MachinePage.jsx` consume el hook y compone; `App.jsx` no guarda estado remoto
- [ ] Los controles se deshabilitan mientras hay una acción en vuelo. No es cosmético: sin librería de datos no hay deduplicación de peticiones, así que es lo único que impide que un doble clic mande dos compras ([ADR-0016](../../../docs/adr/0016-frontend-layers-and-no-data-library.md), consecuencia negativa declarada)
- [ ] El mensaje de error se elige por el `code` del problem+json, nunca parseando `detail`
- [ ] Tests de componente (Vitest+RTL) mockeando el **módulo** `services/` —nunca `global.fetch`—: render del estado, interacción de compra feliz, interacción con error. Consultas por rol y nombre accesible
- [ ] Sin console.log, componentes <200 líneas, cero cálculo de importes en el cliente
- [ ] Verificación activa con el MCP de Chrome DevTools contra el backend real: `navigate_page` → `take_snapshot` → interacción → `take_snapshot` → `list_console_messages`, con los snapshots y la consola **pegados** en el informe

## Capa
frontend

## Archivos probablemente afectados
- `frontend/src/components/CoinButtons.jsx`, `ProductGrid.jsx`, `InsertedDisplay.jsx`, `ReturnCoinButton.jsx`, `ExactChangeLamp.jsx`, `ServiceDrawer.jsx`, `DispenseTray.jsx` (+ sus `.test.jsx`) (a crear)
- `frontend/src/hooks/useMachine.js` (+ su test) (a crear) — dueño del estado remoto
- `frontend/src/pages/MachinePage.jsx` (+ su test) (a crear) — la pantalla
- `frontend/src/App.jsx` — solo layout y composición

## Enfoque sugerido
1. TDD por componente presentacional (props → render).
2. `useMachine` como único dueño del estado remoto. **No hace falta refetch tras cada acción**: los cuatro endpoints de escritura devuelven el estado completo de la máquina, así que la respuesta de la acción *es* el estado nuevo y se guarda tal cual. Ése es el motivo de que aquí no haga falta librería de datos, y conviene saber contarlo.
3. Timebox duro: si un componente pide "una cosita más", va a ticket.

(No prescriptivo — el implementador puede divergir si encuentra mejor camino.)

## ADR asociado
No — aplica decisiones tomadas.

## Depende de
16

## Prioridad sugerida
media — completa la entrega visible; el backend sigue siendo lo puntuado.

## Notas y referencias
- La lamparita EXACT CHANGE ONLY es el mejor demo del caso límite estrella: mostrarla en la entrevista.

## Origen
Desglose de backlog — sesión PM de 2026-08-18.
