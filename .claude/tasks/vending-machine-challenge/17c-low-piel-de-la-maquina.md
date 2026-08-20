# Vestir el panel con la piel de una máquina expendedora real

## Contexto
Los tickets 17 y 17b dejan el panel funcionando con markup desnudo: una lista de botones, un formulario y un par de regiones. Este ticket le pone el aspecto —cuerpo de metal, cristal sobre el escaparate, display encendido, lamparita que se enciende de verdad, botones con relieve y el cajón de servicio deslizándose desde el borde— y **no toca ni una línea de comportamiento**.

Ésa es exactamente la razón de que sea un commit aparte: un cambio que solo añade CSS no puede romper nada, y se revisa mirando la pantalla en vez de leyendo lógica.

## Criterios de aceptación
- [ ] **Cero cambios de comportamiento.** Ningún test se modifica y todos siguen verdes. Si un test se pone rojo, el ticket se ha salido de su carril y hay que parar
- [ ] Se permite añadir `className` y elementos **puramente decorativos** marcados `aria-hidden`. No se toca ningún rol, nombre accesible, texto visible ni handler
- [ ] CSS plano, **cero dependencias nuevas**: un `.css` por componente junto a su `.jsx`, más un `src/index.css` con las variables de paleta, tipografía y sombras. Ni Tailwind ni librería de componentes — mismo razonamiento con el que ADR-0016 rechazó la librería de datos: no se paga una dependencia por un problema que no se tiene
- [ ] **Sin fuentes ni assets externos.** Nada de Google Fonts ni CDN: el aspecto de display de 7 segmentos se consigue con la pila de fuentes del sistema y espaciado. Una SPA que pide un recurso a un tercero en cada carga es una petición de red que hay que justificar, y aquí no se justifica
- [ ] Contraste AA en todo el texto, incluido el verde del display sobre el negro y la lamparita encendida
- [ ] La lamparita sigue diciendo su estado **con texto**, no solo con color. La piel no puede convertir información en decoración
- [ ] `prefers-reduced-motion` respetado: el deslizamiento del cajón y cualquier transición se anulan cuando el sistema lo pide
- [ ] Responsive: una máquina expendedora es alta y estrecha, así que debe seguir usable en móvil sin scroll horizontal
- [ ] Verificación activa con el MCP de Chrome DevTools: `take_snapshot` **antes y después** de aplicar la piel, y los dos árboles de accesibilidad deben ser equivalentes — la prueba de que solo han cambiado los píxeles. Más `lighthouse_audit` de accesibilidad con la puntuación pegada, y una captura de pantalla, que aquí el hallazgo sí es visual

## Capa
frontend

## Archivos probablemente afectados
- `frontend/src/index.css` (a crear) — variables de paleta, tipografía y sombras
- `frontend/src/components/*.css` (a crear) — uno por componente
- `frontend/src/pages/MachinePage.css` (a crear) — la carcasa y la rejilla que coloca escaparate, columna de controles y bandeja
- `frontend/src/main.jsx` — importar `index.css`
- `frontend/src/components/*.jsx`, `pages/MachinePage.jsx` — **solo** `className` y decoración `aria-hidden`

## Enfoque sugerido
1. Primero las variables y la carcasa; los detalles (relieve de los botones, brillo del cristal) al final, que es lo que se puede recortar si el tiempo aprieta.
2. Comprobar el árbol de accesibilidad en cuanto la carcasa esté puesta, no al final: si la rejilla ha obligado a mover markup, cuanto antes se sepa mejor.
3. Timebox duro. Es la parte no evaluada del entregable no evaluado: se para cuando parece una máquina, no cuando parece una fotografía.

(No prescriptivo — el implementador puede divergir si encuentra mejor camino.)

## ADR asociado
No — aplica ADR-0016 (capas del frontend y criterio para no añadir dependencias).

## Depende de
17, 17b

## Prioridad sugerida
baja — es lo único de todo el backlog que no cambia comportamiento; si algo se cae del sprint, se cae esto.

## Notas y referencias
- El markup de 17 y 17b se escribe ya con la anatomía del cabinet dentro (cuerpo, escaparate, columna de controles, bandeja, puerta), precisamente para que este ticket sea CSS y nada más. Si aquí hace falta reestructurar markup, el fallo estaba en aquéllos.
- Regla que gobierna las dudas: si el realismo pide sacrificar un nombre accesible o convertir un `<button>` en un `<div>` bonito, gana el markup. Entre otras cosas porque es lo que sostiene los tests.

## Origen
Desglose de backlog — sesión PM de 2026-08-18, separado del ticket 17 el 2026-08-21 tras la conversación de UI/UX.
