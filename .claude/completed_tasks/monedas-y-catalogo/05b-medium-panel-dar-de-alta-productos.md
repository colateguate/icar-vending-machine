# Panel: dar de alta productos, con la validación que eso estrena

## Contexto

Tras el 05a las filas existentes se editan y se quitan, pero el catálogo sigue siendo cerrado: no hay forma de meter un producto que la máquina no tuviera ya. El backend lo acepta desde siempre (`PUT /service` fija valores absolutos, `ProductSelector` es un string validado), así que la brecha es solo de UI.

Este ticket es donde el panel **estrena texto tecleado por una persona**, y con él toda la validación de cliente: hasta hoy cada selector y cada precio que el panel enviaba venían de la propia respuesta de la máquina. Ese cambio hace alcanzable un código de error que el mapa del display documenta hoy como inalcanzable (`MachineDisplay.jsx:73-76`).

## Criterios de aceptación

- [ ] Un control de **añadir producto** con nombre accesible crea una fila con inputs de **selector**, nombre, precio y unidades — los cuatro con nombre accesible, y el selector editable **solo** en filas nuevas (en las existentes es la identidad).
- [ ] Validación local del selector, espejo de `^[A-Z][A-Z0-9_-]{0,31}$` (`docs/openapi.yaml:691`), con el mensaje asociado al campo (`aria-describedby` / `aria-invalid`), no en un cartel suelto.
- [ ] Validación local del **precio como string decimal**. `Number()`/`parseFloat` sobre un importe sigue siendo Critical; la validación es sobre la forma del texto, no sobre su valor numérico convertido.
- [ ] **Selector duplicado dentro del formulario → error local antes de enviar.** El servidor ya lo rechaza (`ServiceMachineRequest.php:75`, `InvalidRequestPayload::duplicated('products', ...)`), y esa red se queda; pero mandar una visita que se sabe inválida y esperar el 422 no es validar.
- [ ] Con errores locales pendientes, **Apply no envía**. Decidir y dejar escrito qué se comunica: un formulario que no hace nada al pulsar y no dice por qué es peor que uno que manda basura.
- [ ] Los comentarios de alcanzabilidad del mapa `MESSAGES` se actualizan: `invalid_product_selector` (`MachineDisplay.jsx:73-76`) pasa a ser **alcanzable** — hoy dice literalmente "no selector is ever typed into this panel", y con este ticket deja de ser cierto. Ese mapa documenta por escrito qué entradas se pueden provocar, y una afirmación caducada ahí es peor que ninguna.
- [ ] Tras guardar: alta de TEA 0.80×4 → comprable al instante, sin recargar (la respuesta de la escritura ES el estado).
- [ ] Tests de componente por rol y nombre accesible, incluida la cara triste de cada validación.
- [ ] `make qa` en verde y verificación en navegador contra el backend real.

## Capa

frontend

## Archivos probablemente afectados

- `frontend/src/components/ServiceDrawer.jsx` — el control de añadir y el bloqueo del submit
- `frontend/src/components/serviceForm.js` — la fábrica de fila en blanco y, probablemente, las reglas de validación *(a crear o ampliar)*
- La fila de producto que salga del 05a — el selector editable solo en filas nuevas
- `frontend/src/components/MachineDisplay.jsx:73-76` — la alcanzabilidad de `invalid_product_selector`
- Tests: `frontend/src/components/ServiceDrawer.test.jsx`, `frontend/src/pages/MachinePage.test.jsx`

## Enfoque sugerido

1. Rojo primero: añadir una fila, rellenarla y comprobar que el submit la envía dentro de `products`.
2. Después cada validación con su test rojo propio, una a una — son tres reglas distintas y cada una tiene su mensaje.
3. Decidir dónde vive la validación: un módulo puro al lado de `serviceForm.js` se puede probar sin renderizar nada, y el repo ya separó así la semilla del formulario.
4. Verificación activa en navegador, incluida al menos una validación fallando de verdad.

(No prescriptivo.)

## ADR asociado

No — aplica ADR-0016 y ADR-0004. Pero **sí hay una decisión que dejar escrita en el código**: duplicar en el cliente una regla que el servidor ya tiene es aceptar que existan dos sitios donde vive el mismo regex. Se hace porque el ida y vuelta por red para decir "eso no es un selector" es mal servicio, y porque el 422 sigue siendo la autoridad. Si al implementarlo se decide lo contrario — no validar en cliente y dejar hablar al servidor — eso también es defendible y debe quedar escrito.

## Depende de

05a

## Prioridad sugerida

media — completa la gestión del catálogo. Es la mitad de riesgo del 05 original: aquí está todo lo que se teclea y todo lo que se valida.

## Notas y referencias

- Spec: [../../../docs/specs/2026-08-21-configurable-coins-and-catalogue-design.md](../../../docs/specs/2026-08-21-configurable-coins-and-catalogue-design.md) (K5, criterio de éxito 4).
- El servidor da la ruta del campo en el 422 (`field: products[0].count` y similares) y el display ya la lee: `invalid_request_payload` con su extensión `field` está en el mapa. La validación local no sustituye eso, lo adelanta.
- Cuidado con el orden: validar el duplicado exige mirar todas las filas a la vez, no solo la que se acaba de teclear.

## Origen

Desglose del ticket 05 (decisión del usuario, 2026-08-22) — el 05 original mezclaba edición, alta, baja y tres validaciones nuevas en un solo commit

---

## Cierre

Implementado en `feat/the-shelf-takes-a-new-product`. Cierra la épica de
funcionalidad; solo queda el 06 de documentación.

Las dos decisiones que el ticket dejaba abiertas, resueltas con el usuario:

1. **Espejo en el cliente**, no "solo el servidor". Pesó un dato medido: el 422
   de `invalid_product_selector` **no lleva la extensión `field`**, así que sin
   validación local el panel puede decir que algo está mal pero no cuál — el
   valor ofensivo solo aparece en el `detail`, que es prosa que el panel tiene
   prohibido leer. La decisión y su consecuencia negativa quedaron escritas en
   ADR-0016, no solo en un comentario.
2. **Habla al pulsar y luego en vivo.** Silencio mientras se teclea; al primer
   rechazo, mensajes junto a cada campo y foco en el primero malo; a partir de
   ahí se revalida según se corrige.

Dos cosas que no estaban en el ticket y aparecieron implementando:

- **`required` bloqueaba la validación propia.** Con filas en blanco los campos
  vacíos se volvieron alcanzables, y la validación nativa refusaba el submit
  antes de que corriera la nuestra: sin mensajes, sin foco, y con un bocadillo
  que ni un lector de pantalla anuncia bien ni un test puede leer. El formulario
  pasa a `noValidate` — un solo portero — y las reglas crecen para cubrir lo que
  el navegador cubría: nombre no vacío y unidades enteras.
- **El duplicado solo se marca en la fila nueva.** La primera versión marcaba
  las dos, "porque cualquiera podría ser el error". Lo desmintió un test: la
  fila que ya está en el estante muestra el selector como rótulo, no como
  campo, así que el mensaje no tenía dónde vivir ni nada que el lector pudiera
  hacer con él. La fila que se está dando de alta es la que puede ceder.

Queda una limitación conocida y aceptada: tras guardar, la fila recién creada
sigue con su selector editable hasta que se cierra y reabre el cajón. Es
coherente — el formulario siempre muestra lo que enviará, y lo que enviará es
lo que la máquina tiene — y reabrir la sella.
