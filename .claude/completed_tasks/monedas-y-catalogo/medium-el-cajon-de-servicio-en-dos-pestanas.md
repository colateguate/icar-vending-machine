# Dar al cajón de servicio dos pestañas, y que una fila se vea como una fila

## Contexto

El cajón de servicio ha crecido tres tickets seguidos y ya no se lee. Medido en el navegador, con la máquina de siempre (3 productos, 6 denominaciones):

| Qué | Medida |
|---|---|
| Contenido del cajón | 1144 px en 600 visibles — **1,9 pantallas de scroll** |
| Alto de una fila de producto | 147 px |
| Separación **entre** filas | **12 px** |
| Botón Remove de la primera fila | 55→87, con la fila de 55 a 202 |

Las dos quejas del usuario tienen la misma causa y se ven en esos números. El espacio *dentro* de una fila es doce veces el que hay *entre* filas, así que tres productos se leen como una masa continua; y el Remove está arriba del todo pero **sin ningún borde que diga dónde acaba su fila**, de modo que no hay forma de saber si pertenece a la primera línea o al producto entero. Una fila necesita ser una tarjeta.

Lo tercero es de orientación: el cajón mezcla la gestión del catálogo y la de las monedas en una sola columna larga, y nada dice cuál de las dos cosas estás tocando.

Este ticket **sustituye** a `medium-partir-el-cajon-de-servicio-en-dos-mitades`, que pedía extraer la mitad del estante a su propio componente sin cambiar comportamiento. Las pestañas obligan a esa extracción por su cuenta, así que aquel objetivo se cumple aquí — pero su segunda mitad (el `submit` de 45 líneas) no la toca nadie, y por eso viaja a este ticket en vez de quedarse huérfana.

## Criterios de aceptación

- [ ] El cajón abre con dos pestañas, **Products** y **Coins** (hoy los `legend` dicen "Slots" y "Till"), y Products es la que se ve al abrir.
- [ ] Patrón ARIA real, no dos botones que cambian contenido: `role="tablist"` / `role="tab"` con `aria-selected` y `aria-controls`, `role="tabpanel"` con `aria-labelledby`, y navegación con flechas entre pestañas con tabindex móvil. Una pestaña que solo funciona con ratón es media pestaña.
- [ ] **Un solo Apply, fuera de las pestañas, que sigue enviando las dos mitades.** SERVICE declara el estado completo: un "aplicar solo monedas" tendría que mandar igualmente los productos, así que dos botones mentirían sobre lo que hacen.
- [ ] **Si la validación falla en una pestaña que no está a la vista, el cajón cambia a esa pestaña y pone el foco en el campo malo.** Sin esto, pulsar Apply desde Coins con un precio inválido no haría nada visible — que es exactamente el fallo que la validación se escribió para evitar.
- [ ] Cada fila de producto se ve como una unidad: borde o fondo propio, y **más separación entre filas que dentro de ellas**. El Remove deja de ser ambiguo porque se ve a qué pertenece.
- [ ] En Coins desaparece el texto "accepted" de cada fila y la palabra aparece una vez como **cabecera de columna** (algo como `Taken · Coin · In till`). El nombre accesible de cada casilla sigue siendo `"0.50 — accepted"` — eso ya vive en un `visually-hidden` y no se toca.
- [ ] `submit` (`ServiceDrawer.jsx:163-208`, 45 líneas) baja de 30. El bloque extraíble está identificado: las tres transformaciones que construyen los argumentos de `onService`.
- [ ] `ServiceDrawer.jsx` queda por debajo de 200 líneas (hoy 309).
- [ ] Los tests existentes del cajón siguen pasando **salvo** donde el cambio de pestañas lo impida de verdad: un test que hoy ve productos y monedas a la vez tendrá que abrir la pestaña. Cada test que se toque, se toca por esa razón y no por otra.
- [ ] `make qa` en verde y verificación en navegador con snapshot del árbol de accesibilidad antes y después, incluida la navegación por teclado entre pestañas.

## Capa

frontend

## Archivos probablemente afectados

- `frontend/src/components/ServiceDrawer.jsx:133-208,240-300` — pestañas, submit, y lo que se queda
- `frontend/src/components/ShelfPanel.jsx` y `frontend/src/components/TillPanel.jsx` *(a crear)* — el contenido de cada pestaña
- `frontend/src/components/CoinSwitch.jsx:22-33` — se le quita el texto visible, no el nombre accesible
- `frontend/src/components/ServiceDrawer.css:106-230` — la fila como tarjeta, la separación, las pestañas, la cabecera de columna
- Tests: `ServiceDrawer.test.jsx`, `CoinSwitch.test.jsx`, y `frontend/e2e/machine.spec.js` si el cajón deja de mostrar de golpe lo que esas specs miran

## Enfoque sugerido

1. Rojo primero: un test que abra el cajón, compruebe que hay dos pestañas, que Products es la activa, y que las flechas cambian de pestaña.
2. Extraer los dos paneles **antes** de meter las pestañas: mover código y cambiar comportamiento en el mismo paso hace que un fallo no tenga culpable claro.
3. Después el foco entre pestañas, que es la parte con trampa: el campo malo puede estar en un panel que no está montado, así que enfocarlo justo después de cambiar de pestaña no lo encuentra. Cambiar de pestaña y enfocar en el render siguiente.
4. Lo visual al final, medido en el navegador y no a ojo — las dos regresiones de layout de los tickets 05a y 05b aparecieron midiendo.

(No prescriptivo.)

## ADR asociado

No — aplica ADR-0016 sin cambiarlo. Las pestañas son estado de UI local, del mismo tipo que "el cajón está abierto": la máquina no tiene ninguna opinión sobre qué sección estás mirando.

## Prioridad sugerida

media — no hay bug, la funcionalidad está entera y verificada. Es legibilidad de la única pantalla que se enseña en la defensa, sobre un componente al que ya se le han señalado dos hallazgos de tamaño en dos revisiones seguidas.

## Notas y referencias

- Cuidado con el listón de `frontend/e2e/README.md`: una pestaña que oculta contenido es *layout*, y si alguna spec de navegador daba por hecho que todo el formulario está visible a la vez, hay que arreglarla ahí y no relajar la spec.
- El patrón de nombre accesible con `visually-hidden` ya está vigilado por CDP en `machine.spec.js`; al quitar texto visible de `CoinSwitch` conviene comprobar que ese centinela sigue rojo cuando debe.
- Los seis controles de moneda y las filas de producto ya tienen nombre accesible propio; nada de esto debería cambiarlos, y si cambia alguno es un fallo, no un efecto.

## Origen

Petición del usuario tras usar el panel (2026-08-23) — sustituye a `medium-partir-el-cajon-de-servicio-en-dos-mitades`, cuyo objetivo de extracción cumple, absorbiendo además el `submit` largo que aquel dejaba pendiente

---

## Cierre

Implementado en `feat/the-service-drawer-gets-tabs`. Medido antes y después en el
navegador real:

| Qué | Antes | Después |
|---|---|---|
| Scroll del cajón | 1,9 pantallas | Products 1,17 · **Coins cabe entera** |
| Fila de producto | 147px sin borde a 12px de la siguiente | tarjeta con borde y 16px de aire |
| "accepted" | seis veces | una, como cabecera "Taken / In till" |
| `submit` | 45 líneas | ~20 (payload en `serviceForm.toServicePayload`) |
| `ServiceDrawer.jsx` | 309 líneas (~173 de código) | **316 en bruto, 193 de código** |

Sobre la última fila, dicho sin maquillar: el criterio de "<200 líneas" lo cumple
el código pero no el bruto — un tercio del fichero son comentarios de porqué, y
las pestañas (tira, flechas, foco diferido) añadieron más de lo que la
extracción quitó. Los dos paneles sí salieron (`ShelfPanel`, `TillPanel`).

La trampa que predijo el ticket, confirmada: el campo que paró la visita puede
estar en un panel desmontado, y enfocarlo tras cambiar de pestaña no lo
encuentra. El submit cambia la pestaña y deja el id en un ref que un efecto
enfoca en el render siguiente. Verificado en vivo: Apply desde Coins con un
precio roto en Products cambia de pestaña, enfoca el campo y muestra el mensaje.

El centinela CDP de nombres accesibles se re-verificó rojo tras el cambio:
al mover "accepted" a la cabecera, el nombre del switch vive AHORA ENTERO en
texto oculto, así que `display: none` lo dejaría sin nombre — la spec lo caza.
