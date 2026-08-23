# Panel: hacer editables las filas de producto y poder quitarlas

## Contexto

El backend acepta un catálogo distinto en cada visita desde siempre — `PUT /service` fija valores absolutos y el README lo demuestra por curl con SPARKLING_WATER — pero el formulario pinta cada producto como **texto** con un único input de unidades: `label={`${selector} · ${name} · ${price} — units`}` (`frontend/src/components/ServiceDrawer.jsx:173`), y lo único que se puede cambiar es el count. El nombre y el precio se reenvían tal cual llegaron (`ServiceDrawer.jsx:115-120`), así que hoy la UI no sabe contar lo que el contrato ya permite: un técnico no puede corregir un precio ni retirar un producto descatalogado sin abrir una terminal.

Este es el primero de los dos tickets en que se partió el 05: aquí **nada se teclea desde cero**, porque los selectores existentes son la identidad de su fila y siguen siendo de solo lectura. Eso deja este ticket sin ninguna validación nueva de cliente — toda esa parte vive en el 05b, junto con el alta.

## Criterios de aceptación

- [ ] Cada fila de producto ofrece inputs editables de **nombre**, **precio** y **unidades**, cada uno con su nombre accesible; el **selector no es editable** y sigue identificando la fila.
- [ ] Cada fila tiene un control de **quitar** con nombre accesible que dice qué quita (no un aspa sin nombre). Quitar una fila la saca del payload, y como SERVICE declara valores absolutos, eso retira el producto de la máquina.
- [ ] **El precio viaja como el string decimal que se tecleó.** `Number()`/`parseFloat` sobre un importe sigue siendo Critical (ADR-0004). Ojo con el reflejo de usar `<input type="number">` para un precio: las unidades sí son enteros, un importe no — decidir el tipo de input y **dejar escrito por qué**.
- [ ] El estado del formulario deja de depender del **índice posicional**: `update(kind, index, change)` (`ServiceDrawer.jsx:104-109`) y `key={selector}` (`:172`) funcionan mientras las filas no desaparezcan. En cuanto se puede quitar una, hace falta identidad estable — el selector la da para las filas existentes, y el 05b traerá filas que aún no tienen selector, así que conviene elegir algo que sobreviva a ese ticket.
- [ ] El test que hoy afirma que se reenvían nombre y precio intactos (`ServiceDrawer.test.jsx`, "sends back the names and prices it was given, untouched") se **renegocia, no se borra**: su premisa cambia — ahora salen de inputs — pero lo que protegía (que un PUT sin nombre ni precio reprovisiona el catálogo en blanco) sigue siendo cierto y debe seguir cubierto.
- [ ] Tests de componente por rol y nombre accesible; fixtures actualizadas donde asuman filas de solo lectura.
- [ ] `make qa` en verde y verificación en navegador contra el backend real: editar un precio y quitar un producto, y que el estante lo refleje.

## Capa

frontend

## Repo

icar-vending-machine

## Archivos probablemente afectados

- `frontend/src/components/ServiceDrawer.jsx:104-109,111-133,167-177` — el mutador por índice, el submit y las filas de producto
- `frontend/src/components/serviceForm.js:16` — la semilla de productos (`{ ...product, count: String(product.count) }`), que ahora tiene que producir campos editables
- `frontend/src/components/CountField.jsx` — hoy es "un entero con etiqueta"; una fila de producto son cuatro campos y un botón, así que o se generaliza o nace un hermano *(a crear)*
- `frontend/src/components/ServiceDrawer.css:106-114` — la rejilla de fila es `label + input`; una fila de producto ya no cabe ahí
- Tests: `frontend/src/components/ServiceDrawer.test.jsx`, `frontend/src/pages/MachinePage.test.jsx`

## Enfoque sugerido

1. Rojo primero: un test que edite el precio de una fila y compruebe que el submit envía el precio nuevo como string.
2. Identidad estable de fila antes de tocar la UI — es lo que hace seguro quitar.
3. Después la baja, que es una operación pura sobre ese estado.
4. Verificación activa en navegador (MCP Chrome DevTools), con snapshot antes y después.

(No prescriptivo — el implementador puede divergir si encuentra mejor camino.)

## ADR asociado

No — aplica ADR-0016 (capas del panel) y ADR-0004 (el dinero nunca es un float). El contrato no cambia: este ticket no toca el backend.

## Depende de

03

## Prioridad sugerida

media — el contrato ya sirve la funcionalidad por curl; esto la hace usable. Sin riesgo de dominio: no se estrena validación de cliente en este ticket.

## Notas y referencias

- Spec: [../../../docs/specs/2026-08-21-configurable-coins-and-catalogue-design.md](../../../docs/specs/2026-08-21-configurable-coins-and-catalogue-design.md) (K5, criterio de éxito 4).
- Patrón canónico a imitar: el ticket 04 hizo lo análogo con las monedas — `CoinSwitch.jsx` y el hueco de `children` de `CountField` son el precedente de "una fila con más de un control".
- Trampa conocida: un `<input type="number">` acepta `1e21`, que sobrevive a `min`, `step` y `required` y llega al servidor como algo que refusa. Ya está documentado en el mapa de `MachineDisplay.jsx` para las unidades; un precio tiene además el problema del separador decimal según la configuración regional.

## Origen

Desglose del ticket 05 (decisión del usuario, 2026-08-22) — el 05 original mezclaba edición, alta, baja y tres validaciones nuevas en un solo commit

---

## Cierre

Implementado en `feat/the-shelf-can-be-edited`.

Dos hallazgos que solo dio el navegador, y que ninguna de las 161 pruebas de
Vitest podía ver — jsdom corre con `css: false`, no aplica hoja de estilos ni
hace layout:

1. **La fila de cinco columnas no cabía.** Medido en el cajón real: el fieldset
   de Slots ocupaba 366 px dentro de un panel de 367 y ponía una barra de scroll
   horizontal bajo todo el formulario. La fila pasa a envolverse en tres líneas.
2. **El precio se veía recortado a "1.0".** El valor era correcto (`"1.00"`); la
   caja medía 44 px y necesitaba 51, porque la columna `auto` le había dado 229
   al contador de unidades. La segunda columna pasa a medir los mismos 5rem que
   las filas del till, así que los números de los dos fieldsets quedan alineados.

Ninguno de los dos habría aparecido en revisión de código: los dos son la hoja
de estilos cambiando lo que la pantalla dice.
