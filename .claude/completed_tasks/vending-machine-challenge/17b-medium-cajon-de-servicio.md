# Añadir el cajón de servicio para reponer stock y monedas

## Contexto
El ticket 17 deja la máquina operable desde fuera: se meten monedas y se compra. Falta el otro lado, el de quien tiene la llave: `PUT /api/machine/service` (`docs/openapi.yaml:432`) fija qué hay en las bandejas y qué hay en la hucha, y hoy solo se puede invocar por `curl`.

Se resuelve con un cajón que entra desde el borde derecho al pulsar un botón de servicio, como abrir la puerta de la máquina. Separar visualmente "soy cliente" de "soy el técnico" es la misma distinción que la API ya hace entre comprar y hacer un service, y hace la demo mucho más legible en la entrevista.

## Criterios de aceptación
- [ ] `components/ServiceDrawer.jsx` es dueño de su propio abierto/cerrado —estado local de UI, no remoto— y expone el botón que lo abre. La puerta y su manilla son la misma pieza
- [ ] Contrato de accesibilidad completo: el disparador lleva `aria-expanded`, el panel tiene nombre accesible, **Esc cierra**, el foco entra al abrir y **vuelve al botón** al cerrar
- [ ] El formulario edita las unidades de cada producto y el número de monedas de cada denominación. Nada más
- [ ] **Reenvía `name` y `price` sin tocarlos.** `PUT` declara el resultado, no un delta: lo que no se mande desaparece de la máquina. Un cajón que solo mandara los `count` reprovisionaría el catálogo sin nombres ni precios
- [ ] **Muestra las cuatro denominaciones, también las que no vienen en la respuesta.** `CoinBag` omite las denominaciones sin monedas (`docs/openapi.yaml:610`), y precisamente el caso interesante es cargar la que se agotó — que es la que encendió la lamparita EXACT CHANGE ONLY. Un formulario que solo pintase lo que llega haría imposible arreglar el problema estrella del dominio
- [ ] La fila de la moneda de 1.00 avisa de que **nunca se devuelve como cambio** (`docs/openapi.yaml:583`): cargarla no apaga la lamparita, y quien reponga debe saberlo antes de hacerlo
- [ ] `useMachine` gana la acción `service`; sigue siendo el único módulo que importa de `services/`. La respuesta trae `machine` completo, así que tampoco aquí hay refetch
- [ ] Los errores del service se muestran con el mismo camino que los demás: por `code`, nunca por `detail`. `invalid_request_payload` trae `field` en una extensión y merece usarse
- [ ] Los controles del cajón se deshabilitan mientras la acción está en vuelo, igual que los de fuera
- [ ] Tests (Vitest + RTL) mockeando el módulo `services/`: abrir y cerrar por teclado y por ratón, el foco donde debe estar, el envío con `name` y `price` intactos, una denominación ausente editada desde cero, y el caso de error
- [ ] Sigue sin haber hoja de estilos: el aspecto de cajón deslizante llega en 17c. Aquí el markup solo tiene que estar estructurado para admitirlo

## Capa
frontend

## Archivos probablemente afectados
- `frontend/src/components/ServiceDrawer.jsx` (+ su test) (a crear)
- `frontend/src/hooks/useMachine.js` — añadir la acción `service` (a crear en el ticket 17)
- `frontend/src/pages/MachinePage.jsx` — montar el cajón (a completar en el ticket 17)
- `frontend/src/services/machineApi.js:35` — `service(products, changeReserve)`, ya existe

## Enfoque sugerido
1. Test rojo primero del contrato de foco y teclado: es lo que más se olvida y lo único que no se ve mirando la pantalla.
2. El estado del formulario se inicializa desde el estado de la máquina y se rellenan a cero las denominaciones ausentes en una sola función pura, fácil de probar aparte.
3. Enviar siempre las cuatro denominaciones, incluidas las que quedan a cero: el request las admite con `count: 0` aunque la respuesta las omita.

(No prescriptivo — el implementador puede divergir si encuentra mejor camino.)

## ADR asociado
No — aplica ADR-0012 (contrato de errores RFC 7807 con `code` estable) y ADR-0016 (capas).

## Depende de
17

## Prioridad sugerida
media — sin esto la máquina no se puede reponer desde el panel y la demo se queda a medias.

## Notas y referencias
- **Fuera de alcance a propósito**: dar de alta un producto nuevo (haría falta un formulario de selector, nombre y precio) y tocar el escrow. El contrato dice por qué lo segundo no procede: "the escrow is not part of a service visit — a customer's coins are not the operator's to set" (`docs/openapi.yaml:445`).
- El cajón es el sitio natural para provocar el `exact_change_required` en la entrevista: vaciar la reserva de cambio desde aquí y comprar a continuación.

## Origen
Desglose de backlog — sesión PM de 2026-08-18, separado del ticket 17 el 2026-08-21 tras la conversación de UI/UX.
