# Construir el panel de la máquina con tests de componente

## Contexto
La única pantalla del frontend: el panel de una máquina expendedora operable por el evaluador. Botones de moneda, selección de producto, devolución, display del importe insertado, lamparita "EXACT CHANGE ONLY" (alimentada por `exactChangeOnly` de la API — el error convertido en feature), y un cajón de servicio para reabastecer. Sin lógica de negocio: el panel muestra estado y despacha acciones.

## Criterios de aceptación
- [ ] Componentes: CoinButtons (0.05/0.10/0.25/1), ProductGrid (selector+precio+stock, deshabilitado sin stock), InsertedDisplay, ReturnCoinButton, ExactChangeLamp, ServiceDrawer (form de reabastecimiento), DispenseTray (producto y cambio de la última acción)
- [ ] Estados de la API reflejados: loading, error (mensaje según `code` del problem+json), éxito
- [ ] Comprar sin fondos suficientes muestra el mensaje correcto; comprar sin cambio componible muestra el de exact change — ambos con test
- [ ] Tests de componente (Vitest+RTL) con `machineApi` mockeado: render del estado, interacción de compra feliz, interacción con error
- [ ] Sin console.log, componentes <200 líneas, cero cálculo de importes en el cliente
- [ ] Verificación activa: flujo completo en navegador contra el backend real, consola limpia

## Capa
frontend

## Archivos probablemente afectados
- `frontend/src/components/CoinButtons.jsx`, `ProductGrid.jsx`, `InsertedDisplay.jsx`, `ReturnCoinButton.jsx`, `ExactChangeLamp.jsx`, `ServiceDrawer.jsx`, `DispenseTray.jsx` (+ sus `.test.jsx`) (a crear)
- `frontend/src/App.jsx` — composición y estado

## Enfoque sugerido
1. TDD por componente presentacional (props → render).
2. App como único dueño del estado remoto (fetch on mount + refetch tras cada acción); sin librería de estado — no la necesita.
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
