# Partir ServiceDrawer en la mitad del estante y la mitad del till

## Contexto

`frontend/src/components/ServiceDrawer.jsx` mide **309 líneas** frente al umbral de 200 que aplica la revisión de este repo, y su función `submit` mide **45** (`:163-208`) frente al umbral de 30. No es un fichero que creciera sin darse cuenta: los tickets 04, 05a y 05b le añadieron el interruptor por moneda, las filas de producto editables y el alta con validación, y cada uno lo dejó un poco más grande de forma defendible.

El argumento de fondo no es el número. Dentro de este fichero conviven **dos historias distintas**: la del estante (`setProducts` `:133`, `addProduct` `:150`, `removeProduct` `:159`, el fieldset de `:250` a `:271`) y la del till (las filas de monedas y sus interruptores, desde `:274`). Hoy solo las separa un comentario. Ninguna de las dos tiene nombre propio, y por eso el `submit` baja a manejar filas individuales en vez de hablar de dos secciones.

## Criterios de aceptación

- [ ] El estante sale a su propio componente — algo como `ShelfFieldset` — que recibe las filas, sus problemas y los tres callbacks (cambiar, añadir, quitar). El till puede quedarse: es más compacto y no tiene ese peso.
- [ ] `ServiceDrawer.jsx` queda por debajo de 200 líneas y vuelve a ser lo que su nombre promete: el contenedor del formulario, no el editor de filas de catálogo.
- [ ] `submit` (`:163-208`) baja de 30 líneas. El bloque extraíble está identificado: las tres transformaciones que construyen los argumentos de `onService` — productos, monedas y denominaciones aceptadas — con sus comentarios. Un `buildServicePayload(form)` deja el `submit` contando su propia historia: validar → si hay problemas, enfocar el primero y volver → enviar.
- [ ] **Cero cambios de comportamiento**: los 207 tests del panel y las 7 specs de navegador pasan sin tocarlos. Si algún test hay que cambiarlo, es señal de que la extracción cambió algo que no debía.
- [ ] Los comentarios que explican *por qué* (el `noValidate`, el precio como texto, la identidad de fila, las monedas varadas) viajan con el código al que pertenecen, no se quedan huérfanos en el fichero padre.
- [ ] `make qa` en verde.

## Capa

frontend

## Archivos probablemente afectados

- `frontend/src/components/ServiceDrawer.jsx:133-159,163-208,246-271` — lo que sale y lo que queda
- `frontend/src/components/ShelfFieldset.jsx` *(a crear)*
- `frontend/src/components/ServiceDrawer.test.jsx` — **no debería hacer falta tocarlo**; que siga verde es el criterio de que la extracción fue solo eso

## Enfoque sugerido

1. Extraer primero `buildServicePayload`, que es puro y no mueve markup — se ve enseguida si algo se rompió.
2. Después el fieldset del estante, moviendo con él sus tres mutadores.
3. En ningún paso tocar los tests: son la red que dice que esto fue un refactor y no un rediseño.

(No prescriptivo.)

## ADR asociado

No — aplica ADR-0016 sin cambiarlo.

## Depende de

—

## Prioridad sugerida

media — no hay bug ni riesgo; es fricción de lectura que ya se ha señalado en dos revisiones seguidas, sobre un fichero que va a seguir creciendo si el panel gana algo más.

## Notas y referencias

- Detectado por `clean-code-reviewer` en los tickets 05a y 05b. En el 05a se midió que la deuda era preexistente (216 líneas antes del ticket); en el 05b la recomendación pasó a incluir por dónde cortar.
- Se decidió **no** meter la extracción en el commit de una feature: un diff que mezcla "añadir productos" con "mover un componente de sitio" es el que nadie puede revisar por partes.

## Origen

Detectado durante review-before-push del ticket 05b (alta de productos) — dos hallazgos Medium del eje clean-code, aceptados y aplazados a ticket propio con acuerdo del revisor
