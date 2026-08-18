# Implementar el cliente API del frontend (fetch + problem+json)

## Contexto
Módulo único que encapsula toda conversación con la API: los componentes no conocen fetch ni URLs. Maneja las dos particularidades del contrato: el dinero viaja como string decimal (NUNCA convertir a Number para operar — solo mostrar), y los errores llegan como application/problem+json con `code` estable para decidir el mensaje de UI.

## Criterios de aceptación
- [ ] `frontend/src/api/machineApi.js`: getState, insertCoin, returnCoins, purchase, service — una función por endpoint
- [ ] Errores problem+json parseados a un objeto `{status, code, detail, ...extras}`; red errors distinguidos de errores de API
- [ ] Los importes se tratan como strings de presentación de punta a punta (test que lo verifica)
- [ ] Tests con fetch mockeado: caso feliz y caso problem+json por función
- [ ] Base URL configurable (proxy en dev, build-arg en docker)

## Capa
frontend

## Archivos probablemente afectados
- `frontend/src/api/machineApi.js` (a crear)
- `frontend/src/api/problemDetails.js` (a crear — parser de RFC 7807)
- `frontend/src/api/machineApi.test.js` (a crear)

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
