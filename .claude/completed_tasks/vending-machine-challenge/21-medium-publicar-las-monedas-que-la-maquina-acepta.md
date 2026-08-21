# Publicar en el estado de la máquina qué monedas acepta y cuáles devuelve

## Contexto
`frontend/src/components/CoinButtons.jsx:10` lleva escrito a mano `['0.05','0.10','0.25','1.00']`, con un comentario que reconoce el problema: el estado de la máquina dice qué monedas **tiene**, nunca cuáles **acepta**, así que el panel duplica el enum del contrato (`docs/openapi.yaml:580`) y los dos tienen que moverse juntos.

Y no es una duplicación, son dos. El ticket 17b necesita la otra: el cajón de servicio debe avisar de que **la moneda de 1.00 nunca se devuelve como cambio**, que es la interpretación central del reto (`CoinDenomination::isDispensableAsChange()`, `backend/src/VendingMachine/Domain/Money/CoinDenomination.php:41`). Si no se publica, esa regla acaba escrita a mano en el cliente — y de todas las cosas que se pueden duplicar, una interpretación del enunciado es la peor.

El dominio ya sabe las dos cosas. Solo no las cuenta.

## Criterios de aceptación
- [ ] `MachineState` gana `acceptedCoins`: lista de `{ denomination, dispensableAsChange }`, ordenada de menor a mayor como ya lo están los `cases()` del enum
- [ ] Sale del dominio, no de una constante nueva: `CoinDenomination::cases()` e `isDispensableAsChange()` son la fuente
- [ ] `MachineStateView` lo transporta como valores de dominio y `MachineStateResponse` lo formatea, respetando la división que ya existe: la vista no sabe qué es un string decimal
- [ ] `docs/openapi.yaml`: esquema, `required` y **los cinco ejemplos publicados de estado** actualizados. Los tres gates del contrato tienen que seguir en verde — y son justamente ellos los que dirán qué falta, así que se corrige lo que señalen en vez de buscarlo a ojo
- [ ] `CoinButtons` deja de tener la lista escrita y la recibe por props desde el estado. El array literal desaparece del frontend
- [ ] Su test sigue nombrando las cuatro monedas **a mano**, no leyéndolas del fixture: un test que deriva su expectativa del dato que le pasan no comprueba nada
- [ ] Tests por nivel: aplicación (la vista lleva las cuatro), aceptación (el JSON las publica con `dispensableAsChange` correcto en las cuatro), componente (el panel pinta las que le den)
- [ ] `make qa` en verde, incluido `schema-check`

## Capa
domain (lectura), application, delivery, docs, frontend

## Archivos probablemente afectados
- `backend/src/VendingMachine/Application/Query/GetMachineState/MachineStateView.php:29` — un miembro más
- `backend/src/VendingMachine/Application/Query/GetMachineState/GetMachineStateHandler.php:19` — lo rellena
- `backend/src/VendingMachine/Delivery/Http/Response/MachineStateResponse.php:36` — lo formatea, y su docblock de tipos
- `docs/openapi.yaml:639` (esquema `MachineState`) y los ejemplos de las líneas 132, 184, 275, 349 y 512
- `backend/tests/Acceptance/Http/MachineStateEndpointTest.php`, `ServiceEndpointTest.php`, `backend/tests/Application/VendingMachine/Query/GetMachineStateHandlerTest.php`
- `frontend/src/components/CoinButtons.jsx:10` y su test, `frontend/src/pages/MachinePage.jsx:44`

## Enfoque sugerido
1. Backend primero, de dentro afuera: vista → handler → responder → contrato. Los gates del contrato irán diciendo qué queda.
2. El frontend al final, que es una línea menos y una prop más.
3. `dispensableAsChange` se publica como booleano y no como una lista aparte de "monedas de cambio" porque el cliente pregunta por moneda, no por conjunto.

(No prescriptivo — el implementador puede divergir si encuentra mejor camino.)

## ADR asociado
No — aplica ADR-0004 (dinero como string) y el ADR que ya documenta que la moneda de 1.00 no se devuelve. Esto lo **publica**, no lo decide.

## Depende de
17

## Prioridad sugerida
media — y conviene hacerlo **antes del 17b**, porque si no el cajón de servicio nace con la regla del 1.00 escrita a mano y luego hay que quitarla.

## Notas y referencias
- **La tensión, dicha en voz alta**: el conjunto de monedas no va a cambiar en este reto, así que hay un argumento YAGNI legítimo para dejarlo duplicado y documentado. Lo que inclina la balanza no es la primera duplicación —cuatro literales— sino la segunda: publicar una interpretación del enunciado es mejor que reimplementarla al otro lado de la red.
- Es además la mejor demostración de para qué sirve la maquinaria del ticket 14b: cambiar el contrato publicado y que tres gates digan exactamente dónde hay que tocar.

## Origen
Detectado durante implement-feature del ticket 17 — declarado en su informe como hallazgo lateral.
