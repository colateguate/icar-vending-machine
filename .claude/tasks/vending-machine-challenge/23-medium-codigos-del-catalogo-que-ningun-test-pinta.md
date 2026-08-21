# Decidir, código a código, qué hace el display con los once mensajes del catálogo

## Contexto
`MachineDisplay.jsx:33-56` mapea los once códigos del `ErrorCatalog` a la frase que enseña el display. Medida la cobertura código a código sobre `frontend/src/**/*.test.*`, **cuatro no aparecen en ningún test**: `invalid_money_amount`, `invalid_product_selector` y `malformed_json` en cero ficheros, y `concurrent_modification` solo en `machineApi.test.js:96-98` — o sea, se prueba que el cliente lo *parsea*, nunca que el display lo *pinta*.

Lo interesante no es el hueco de cobertura, es lo que el hueco sugiere. Este panel construye su JSON con `JSON.stringify`, así que **no puede** producir `malformed_json`; y solo envía denominaciones y selectores que la propia API le publicó en `acceptedCoins` y `products`, así que difícilmente produce `invalid_money_amount` o `invalid_product_selector`. Una entrada del mapa para un caso que este cliente no puede provocar es código muerto disfrazado de cobertura: engorda el mapa, parece defensivo, y nadie sabrá nunca si funciona.

`concurrent_modification` es el opuesto: sí es alcanzable —dos pestañas, o el técnico y un cliente a la vez— y es justo el que no se pinta en ningún test.

## Criterios de aceptación
- [ ] Para cada uno de los once códigos, una decisión escrita: **alcanzable desde este cliente** o **no alcanzable**. La respuesta se justifica con lo que el panel envía, no con lo que la API podría devolver
- [ ] Todo código declarado alcanzable tiene test que comprueba **la frase que se lee en pantalla**, al nivel que le corresponde
- [ ] `concurrent_modification` tiene test de página sobre al menos una acción de escritura: un 409 durante la acción y el display diciendo "Busy, try again"
- [ ] Todo código declarado no alcanzable, o **se borra del mapa**, o se queda con un comentario que diga por qué se conserva pese a no poder ocurrir. Las dos son defendibles; lo que no lo es es dejarlo sin decidir
- [ ] Si se borra alguno, se comprueba que `messageFor` sigue degradando a `GENERIC_FAULT` para un código desconocido — ese camino ya existe y no debe romperse
- [ ] Suite verde y sin bajar el número de tests salvo por borrados justificados

## Capa
frontend

## Archivos probablemente afectados
- `frontend/src/components/MachineDisplay.jsx` — el mapa `MESSAGES` (33-56) y `messageFor` (64-76)
- `frontend/src/components/MachineDisplay.test.jsx` — nivel natural para la frase de cada código
- `frontend/src/pages/MachinePage.test.jsx` — el 409 de escritura; hoy ejercita once casos y ninguno es éste
- `docs/openapi.yaml` y `backend/src/.../ErrorCatalog.php` — la lista autoritativa contra la que contrastar

## Enfoque sugerido
1. Sacar la lista de códigos del `ErrorCatalog` del backend, no del mapa del frontend: si al frontend le falta uno, esta comparación es la que lo enseña.
2. Por cada código, contestar "¿qué tendría que enviar este panel para provocarlo?". Si no hay respuesta, es no alcanzable.
3. Los alcanzables primero, en rojo antes que en verde. Los no alcanzables al final, que es donde está la discusión.

(No prescriptivo — el implementador puede divergir si encuentra mejor camino.)

## ADR asociado
No — aplica ADR-0012 (los errores se leen por `code`, nunca por `detail`). Si se decide borrar entradas del mapa, la decisión cabe en el mensaje de commit.

## Depende de
—

## Prioridad sugerida
media — el hueco de `concurrent_modification` es real y el resto es una pregunta de diseño que hoy no tiene respuesta escrita en ningún sitio.

## Notas y referencias
- La cobertura se midió recorriendo los once códigos con grep sobre los ficheros de test; el resultado exacto está en el commit del ticket 17c.
- Cuidado con el nivel: la frase de un código concreto es un test de componente. El de página solo hace falta para el que necesita una acción de escritura de verdad, que es `concurrent_modification`.
- `showing()` en `MachineDisplay.jsx:25-31` ya cubre el caso de una extensión ausente; los códigos que la usan tienen dos caminos, no uno.

## Origen
Detectado durante implement-feature del ticket 17c, al medir qué vigila la suite ante un cambio de CSS.
