# Decir en el panel que la máquina está fuera de servicio

## Contexto

Desde el ticket 03 una visita de servicio puede apagar todas las denominaciones, y desde el 04 se hace con seis interruptores. El estado publica el hecho — `outOfService` (`backend/src/VendingMachine/Delivery/Http/Response/MachineStateResponse.php:44`, calculado en `VendingMachine.php:216`) — y **el panel no lo lee en ninguna parte**: `outOfService` aparece exactamente una vez en todo `frontend/src`, y es una fixture de test (`frontend/src/pages/MachinePage.test.jsx:48`).

Lo que ve un cliente delante de la máquina apagada, verificado en el navegador durante el ticket 04: la ranura conserva su título "Insert a coin" (`CoinButtons.jsx:17-20`) con **la lista vacía debajo** (`:21-34`, `coins` llega como `[]`), y la lámpara se enciende diciendo "Exact change only" (`ExactChangeLamp.jsx:16`, porque `requiresExactChange()` restringe la reserva al conjunto aceptado y ese conjunto está vacío — `VendingMachine.php:202-208`). O sea: la pantalla no dice que esté apagada, y lo único que sí dice es una razón que no es la razón. Una máquina real apagada lo anuncia; esta parece rota.

## Criterios de aceptación

- [ ] Con `outOfService: true` el panel lo dice en texto que un lector de pantalla lee — no por ausencia de botones ni solo por color.
- [ ] Decidido y escrito en el código: si ese mensaje es el mismo que el de `machine_not_provisioned` (`MachineDisplay.jsx:90` ya usa la cadena "Out of service" para el 503) o si son dos cosas distintas. Son estados distintos — "nunca se aprovisionó, culpa nuestra" vs "el técnico la apagó" — y compartir cadena sin decidirlo es cómo se funden dos significados.
- [ ] Decidido qué hace la lámpara: hoy afirma "Exact change only" cuando la causa real es el aceptador apagado. O se calla, o cede el sitio, o se justifica por escrito que decir las dos cosas es correcto.
- [x] ~~**Los botones de producto y RETURN-COIN siguen operables si hay dinero en el escrow.**~~ **Criterio escrito sobre una premisa falsa, corregido al implementar.** Era cierto que `purchase()` no consulta `acceptedCoins` salvo para el pool de cambio, pero de ahí no se sigue lo que decía este criterio: `service()` **devuelve el escrow antes de aplicar nada** (`VendingMachine.php`, primera línea del método; ya estaba escrito en `documentation/flujos/05-servicio.md:94`). Como el aceptador solo se apaga en una visita, y apagado ya no entra ninguna moneda, **una máquina fuera de servicio tiene el escrow vacío por construcción**. Sin dinero que atrapar, dejar vivos unos botones que solo pueden responder "Insert 0.65 more" en una ranura que no admite monedas era el peor de los dos males. Decisión tomada con el usuario: **productos y RETURN-COIN se deshabilitan**, y la puerta de servicio no, porque es el único camino de vuelta.
- [ ] Test de componente/página por rol y nombre accesible, con fixture `outOfService: true`. La fixture actual de `MachinePage.test.jsx:41-49` ya trae el campo en `false`.
- [ ] Spec de navegador **solo** si aparece algo que jsdom no pueda ver (listón de `frontend/e2e/README.md`).
- [ ] `make qa` en verde.

## Capa

frontend

## Repo

icar-vending-machine

## Archivos probablemente afectados

- `frontend/src/pages/MachinePage.jsx:44-52` — el único sitio que reparte el estado a las mitades de la pantalla; `machine.outOfService` no se lee hoy
- `frontend/src/components/CoinButtons.jsx:15-35` — la sección que hoy se queda con título y lista vacía
- `frontend/src/components/ExactChangeLamp.jsx:12-19` — la lámpara que se enciende por la razón equivocada
- `frontend/src/components/MachineDisplay.jsx:90` — donde ya existe la cadena "Out of service", con otro significado
- `frontend/src/pages/MachinePage.test.jsx:41-49` — la fixture donde vive hoy el único `outOfService` del panel
- Backend: **no se espera tocarlo**. El flag ya está publicado y documentado en `docs/openapi.yaml`

## Enfoque sugerido

1. Rojo primero: un test de página con `outOfService: true` que exija el mensaje. Debe fallar antes de escribir nada.
2. Elegir dónde vive el aviso. El display (`role="status"`) ya es el sitio donde la máquina cuenta lo que pasa, y ya tiene la regla de "o el importe o el mensaje, nunca los dos"; un cartel sobre la ventana es la otra opción y se parece más a un armario real. Decidir con una razón, no por costumbre.
3. Comprobar el caso del escrow con dinero dentro antes de deshabilitar nada.

(No prescriptivo — el implementador puede divergir si encuentra mejor camino.)

## ADR asociado

No — aplica ADR-0018 (fuera de servicio emerge del modelo, sin bandera) y ADR-0016 (el panel decide nada). **Salvo** que al implementarlo se decida que la máquina apagada tampoco debe poder vender con el escrow que ya tiene: eso sería una regla nueva del dominio, backend, y sí necesitaría su ADR — y probablemente su propio ticket.

## Depende de

03 (el flag existe desde ahí). El 04 es lo que hace el estado alcanzable desde el panel, pero no bloquea: por API se alcanza desde el 03.

## Prioridad sugerida

media — no hay pérdida de dinero ni bug de dominio, y la máquina se comporta correctamente; pero es un estado que la épica hizo alcanzable con dos clics, la pantalla no lo explica, y lo único que sí dice ("Exact change only") apunta a la causa equivocada.

## Notas y referencias

- Patrón canónico a imitar: `MachineDisplay.jsx` — mensajes elegidos por `code`, nunca por `detail`, y el mapa `MESSAGES` documenta por escrito qué entradas son alcanzables y cuáles no.
- Cuidado con el reflejo de "si está fuera de servicio, deshabilito todo": el criterio del escrow existe justo para impedirlo.
- El estado también publica `supportedCoins` con su flag `enabled`, así que el panel puede distinguir "la máquina no lee esa moneda" de "hoy no la toma" si el mensaje lo necesita.

## Origen

Detectado durante implement-feature del ticket 04 (panel: till con interruptor por moneda) — visto en el navegador al apagar las seis denominaciones contra el stack real

---

## Cierre

Implementado en `feat/the-machine-says-when-it-is-off`, cortada sobre la rama del ticket 04 (ambas tocan `MachinePage.jsx` y su test).

Las tres decisiones que el ticket dejaba abiertas, resueltas con el usuario:

1. **Dónde** — en el display. Se descartó el cartel sobre el escaparate.
2. **La lámpara** — se calla. No `lit={false}`, que diría "Change available" y es falso: el componente no se renderiza.
3. **La cadena** — separadas. El 503 `machine_not_provisioned` pasa a decir "Not ready yet", que es la respuesta honesta que `CLAUDE.md` ya le daba a ese estado, y "Out of service" queda para lo que el técnico apagó.

Y una cuarta que no existía en el ticket: la **precedencia del display**, `error > fuera de servicio > importe`, con test propio para que sea decisión y no accidente.
