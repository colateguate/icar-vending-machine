# Implementar el cliente API del frontend (fetch + problem+json)

## Contexto
Módulo único que encapsula toda conversación con la API: los componentes no conocen fetch ni URLs. Maneja las dos particularidades del contrato: el dinero viaja como string decimal (NUNCA convertir a Number para operar — solo mostrar), y los errores llegan como application/problem+json con `code` estable para decidir el mensaje de UI.

## Criterios de aceptación
- [ ] `frontend/src/services/machineApi.js`: getState, insertCoin, returnCoins, purchase, service — una función por endpoint
- [ ] `frontend/src/services/httpClient.js` es **el único sitio del proyecto donde aparece `fetch`**: envía, distingue error de red de error de API, y traduce el problem+json
- [ ] Errores problem+json parseados a un objeto `{status, code, detail, ...extras}`; red errors distinguidos de errores de API
- [ ] Los importes se tratan como strings de presentación de punta a punta (test que lo verifica). Ningún `Number()`, `parseFloat` ni `toFixed` sobre un importe, ni en el código ni en los asserts
- [ ] Tests con fetch mockeado: caso feliz y caso problem+json **por función**. Las fixtures se copian de los ejemplos publicados en `docs/openapi.yaml`, que desde el ticket 14g están comprobados contra respuestas reales — no se inventan
- [ ] Base URL configurable (proxy en dev, `VITE_API_URL` como build-arg en docker). Nada más con prefijo `VITE_`: lo que lleve ese prefijo se hornea en el bundle y es público

## Capa
frontend

## Archivos probablemente afectados
- `frontend/src/services/machineApi.js` (a crear)
- `frontend/src/services/httpClient.js` (a crear — el único `fetch`)
- `frontend/src/services/problemDetails.js` (a crear — parser de RFC 7807)
- `frontend/src/services/machineApi.test.js`, `httpClient.test.js`, `problemDetails.test.js` (a crear)
- `docs/openapi.yaml` — fuente de las fixtures, no se toca

## Enfoque sugerido
1. Rojo: tests del parser problem+json con fixtures reales copiadas de la API del ticket 10.
2. Cliente función a función.

(No prescriptivo — el implementador puede divergir si encuentra mejor camino.)

## ADR asociado
No — aplica el contrato del ADR-0012 y la regla money-as-string del ADR-0004.

## Depende de
10, 15

## Prioridad sugerida
media — bloquea el panel (17).

## Notas y referencias
- Los `code` estables del ErrorCatalog (`exact_change_required`, `insufficient_funds`, ...) son la interfaz del cliente con los errores — no parsear `detail`.

## Origen
Desglose de backlog — sesión PM de 2026-08-18.
