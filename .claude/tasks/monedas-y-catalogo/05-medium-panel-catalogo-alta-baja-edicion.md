# Panel: gestión completa del catálogo — alta, baja y edición de productos

## Contexto

El backend acepta catálogos nuevos desde siempre (`PUT /service` fija valores absolutos; el README lo demuestra con SPARKLING_WATER por curl), pero el formulario pinta cada producto como texto con un único input de count (`ServiceDrawer.jsx:142-150`; label en `:147`) y su único mutador es `setCount` (`:85-90`). Decisión K5 del spec: el drawer gestiona el catálogo completo — añadir fila (selector, nombre, precio, count), quitar fila y editar nombre/precio de las existentes. La UI pasa a contar la verdad del contrato: SERVICE declara, no incrementa.

## Criterios de aceptación

- [ ] Botón "añadir producto" que crea una fila con inputs de selector, nombre, precio y count, todos con nombre accesible.
- [ ] Cada fila existente pasa a inputs editables (nombre, precio, count; el selector de una fila existente queda de solo lectura — es la identidad de la fila) y gana botón de quitar.
- [ ] Validación en cliente del selector espejo de `/^[A-Z][A-Z0-9_-]{0,31}$/` (`docs/openapi.yaml:616`) y del precio como string decimal — el 422 del servidor queda de red; el comentario "no selector is ever typed into this panel" (`MachineDisplay.jsx:59-61`) se actualiza: `invalid_product_selector` pasa a ser alcanzable y con mensaje.
- [ ] Selector duplicado en el formulario → error local antes de enviar (el servidor ya lo rechaza con 422 y ruta de campo, `ServiceMachineRequest.php:68-70`).
- [ ] El precio viaja como string decimal tal cual se tecleó — `Number()`/`parseFloat` sobre un importe sigue siendo Critical; los counts sí son enteros.
- [ ] Tras guardar: alta de TEA 0.80×4 comprable al instante; baja → desaparece de la estantería; edición de precio → se refleja (la respuesta de la escritura es el estado).
- [ ] `setCount(kind, index, ...)` sobrevive o se sustituye por key estable (`selector`/`denomination`): con filas que aparecen y desaparecen, el índice posicional deja de ser fiable — hallazgo F1, decisión del implementador.
- [ ] Tests de componente por rol; fixtures actualizadas donde asumen 3 productos.

## Capa

frontend

## Repo

icar-vending-machine

## Archivos probablemente afectados

- `frontend/src/components/ServiceDrawer.jsx:85-150` — filas editables, alta/baja, submit
- `frontend/src/components/serviceForm.js` — semilla con los campos ya presentes (el payload actual re-envía name/price tal cual, `ServiceDrawer.jsx:92-107` — ahora salen de inputs)
- `frontend/src/components/CountField.jsx` — probable generalización o hermanos (TextField/PriceField)
- `frontend/src/components/MachineDisplay.jsx:59-61` — alcanzabilidad de `invalid_product_selector`
- `frontend/src/components/ServiceDrawer.css:97-113` — la fila crece de `label+input` a varios campos
- Tests: `ServiceDrawer.test.jsx`, `MachinePage.test.jsx`

## Enfoque sugerido

1. Rojo: test — añadir una fila, rellenarla y el submit envía el producto nuevo en `products`.
2. Estado del formulario con key estable; alta/baja/edición como operaciones puras sobre ese estado.
3. Validación local (selector, precio, duplicados) con mensajes accesibles junto al campo.
4. Verificación activa en navegador contra el backend real (criterio 4 del spec entero).

(No prescriptivo.)

## ADR asociado

No — aplica ADR-0016; el contrato no cambia (este ticket no toca el backend).

## Depende de

03

## Prioridad sugerida

media — independiente del 04; ambos cuelgan del contrato.

## Notas y referencias

- Spec: [../../../docs/specs/2026-08-21-configurable-coins-and-catalogue-design.md](../../../docs/specs/2026-08-21-configurable-coins-and-catalogue-design.md) (K5, criterio de éxito 4).
- Depende del 03 solo por el shape final del payload de service; si el 03 no lo cambia para productos, puede arrancar en paralelo tras confirmarlo.

## Origen

Sesión PM de 2026-08-21 — spec `docs/specs/2026-08-21-configurable-coins-and-catalogue-design.md`
