# Construir el panel operable y su estado remoto

## Contexto
El frontend ya sabe hablar con la API (ticket 16) pero no enseña nada: `frontend/src/pages/MachinePage.jsx:10` sigue siendo el placeholder del scaffold y `frontend/src/hooks/` solo contiene su `.gitkeep`. Este ticket pone la mitad operable de la máquina —monedas, escaparate, devolución, display, lamparita y bandeja— con `hooks/useMachine.js` como único dueño del estado remoto.

Deliberadamente **sin hoja de estilos y sin cajón de servicio**: van en 17c y 17b. El ticket original los metía todo junto y salían ~19 archivos en un commit; partirlo en tres deja que el commit que da comportamiento y el que da aspecto se revisen por separado, y que el del aspecto no pueda romper nada porque no toca lógica.

## Criterios de aceptación
- [ ] `hooks/useMachine.js` es el **único** módulo que importa de `services/`. Expone: el estado de la máquina, el error actual, un flag de acción en curso, y las cuatro acciones (`insertCoin`, `returnCoins`, `purchase` y la carga inicial)
- [ ] La carga inicial va en un `useEffect` **con cleanup**: si el componente se desmonta antes de que llegue la respuesta, no se escribe estado. Un efecto sin cleanup es un High para `frontend-architecture-reviewer`
- [ ] **No hay refetch después de ninguna acción.** Los cuatro endpoints de escritura devuelven `machine` completo (`docs/openapi.yaml:660`, `:683`, `:698`), así que la respuesta de la acción *es* el estado nuevo y se guarda tal cual. Es el motivo de que aquí no haga falta librería de datos y conviene saber contarlo
- [ ] Componentes presentacionales, props dentro y callbacks fuera, ninguno importa de `services/`:
  - `CoinButtons` — las cuatro monedas aceptadas (`docs/openapi.yaml:580`), cada una un `<button>` cuyo nombre accesible es el importe
  - `ProductGrid` — selector, nombre, precio y unidades; el botón se deshabilita con `count === 0`
  - `MachineDisplay` — el importe insertado, o el mensaje cuando hay error. Región con `aria-live`
  - `ExactChangeLamp` — alimentada por `exactChangeOnly`; el estado se dice con **texto**, no solo con color
  - `ReturnCoinButton` — el botón RETURN-COIN
  - `DispenseTray` — lo que cayó en la bandeja tras la última acción: producto y cambio de una compra, o monedas de una devolución
- [ ] `MachineDisplay` elige el mensaje por el **`code`** del problem+json, nunca parseando `detail` (`frontend/src/services/problemDetails.js:30` lo argumenta). Tres ramas: código conocido → frase propia · código desconocido → frase genérica de avería · `TransportFailure` → frase de "no se ha podido contactar", que es un fallo distinto y el panel no debe confundirlos
- [ ] Todos los controles se deshabilitan mientras hay una acción en vuelo. No es cosmético: sin librería de datos no hay deduplicación de peticiones en vuelo, así que esto es lo único que impide que un doble clic mande dos compras ([ADR-0016](../../../docs/adr/0016-frontend-layers-and-no-data-library.md), consecuencia negativa declarada)
- [ ] `pages/MachinePage.jsx` consume el hook y compone; `App.jsx` sigue sin guardar estado remoto
- [ ] El markup se escribe ya con la anatomía del cabinet dentro (cuerpo, escaparate, columna de controles, bandeja), para que 17c sea CSS y nada más
- [ ] Tests de componente (Vitest + RTL) mockeando el **módulo** `services/`, nunca `global.fetch`. Consultas por rol y nombre accesible. Cubiertos: render del estado, compra feliz, `insufficient_funds`, `exact_change_required`, controles deshabilitados durante la acción
- [ ] Cero `console.log`, componentes <200 líneas, **cero aritmética de importes en el cliente** — los importes llegan como string y se pintan como string
- [ ] Verificación activa con el MCP de Chrome DevTools contra el backend real: `navigate_page` → `take_snapshot` → interacción → `take_snapshot` → `list_console_messages`, con los snapshots y la consola **pegados** en el informe

## Capa
frontend

## Archivos probablemente afectados
- `frontend/src/hooks/useMachine.js` (+ su test) (a crear) — dueño del estado remoto
- `frontend/src/components/CoinButtons.jsx`, `ProductGrid.jsx`, `MachineDisplay.jsx`, `ExactChangeLamp.jsx`, `ReturnCoinButton.jsx`, `DispenseTray.jsx` (+ sus `.test.jsx`) (a crear)
- `frontend/src/pages/MachinePage.jsx` (+ su test) — hoy placeholder; pasa a componer el panel
- `frontend/src/services/machineApi.js:17-37` — se consume, no se toca

## Enfoque sugerido
1. TDD componente a componente, de abajo arriba: presentacionales primero (props → render, sin red), `useMachine` después, `MachinePage` al final.
2. La bandeja recibe la última acción física en una sola forma discriminada (`{ kind: 'purchase', … }` | `{ kind: 'return', … }` | `null`), porque comprar y devolver dejan cosas distintas en el cajetín y el componente no debe adivinar cuál mira.
3. Timebox duro: si un componente pide "una cosita más", va a ticket.

(No prescriptivo — el implementador puede divergir si encuentra mejor camino.)

## ADR asociado
No — aplica ADR-0004 (dinero como string), ADR-0012 (contrato de errores RFC 7807 con `code` estable) y ADR-0016 (capas y ausencia de librería de datos).

## Depende de
16

## Prioridad sugerida
media — completa la entrega visible; el backend sigue siendo lo puntuado.

## Notas y referencias
- El componente del display se llamaba `InsertedDisplay` en el ticket original. Renombrado a `MachineDisplay` porque una máquina real tiene **una** pantalla que unas veces enseña el importe y otras un aviso; un componente llamado `InsertedDisplay` que muestra "SOLD OUT" está mal nombrado.
- La lamparita EXACT CHANGE ONLY es el mejor demo del caso límite estrella del dominio: mostrarla en la entrevista.
- Para la verificación en navegador la máquina tiene que estar aprovisionada, o la API responde 503 (`machine_not_provisioned`): `app:machine:provision` antes de abrir el panel.
- Ningún botón del panel puede provocar `unsupported_coin` ni `invalid_product_selector` — solo manda valores que salen del propio estado. El mapa de mensajes se escribe igualmente completo, porque un panel que enseña `undefined` cuando la API crece un código es peor que uno que enseña una frase genérica.

## Origen
Desglose de backlog — sesión PM de 2026-08-18, repartido en tres el 2026-08-21 tras la conversación de UI/UX.
