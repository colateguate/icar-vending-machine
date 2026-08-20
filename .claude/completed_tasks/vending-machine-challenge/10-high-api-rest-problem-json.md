# Exponer la API REST con errores problem+json y tests de aceptación

## Contexto
El borde HTTP: cinco controladores invocables que deserializan, despachan al bus y serializan — nada más. Los errores de dominio viajan sin capturar hasta un subscriber del kernel que los traduce a RFC 7807 con un catálogo explícito (mapa como DATO, no un match enterrado). Los tres ejemplos del enunciado se convierten en especificación ejecutable por HTTP.

## Criterios de aceptación
- [ ] Endpoints: GET /api/machine · POST /api/machine/coins · POST /api/machine/coins/return · POST /api/machine/purchases · PUT /api/machine/service — controladores invocables de acción única
- [ ] Dinero como string decimal en TODO el contrato JSON ("0.65", nunca 0.65 numérico); `exactChangeOnly` en el estado
- [ ] `ErrorCatalog` (FQCN → status+code): UnsupportedCoin→422, **InvalidMoneyAmount→422**, **InvalidProductSelector→422**, UnknownProductSelector→404, ProductOutOfStock→409, InsufficientFunds→409, CannotDispenseChange→409 exact_change_required, MachineNotFound→503, resto→500 con detail suprimido
- [ ] **Test de exhaustividad del catálogo**: recorrer todas las implementaciones de `VendingMachineError` del dominio y fallar si alguna no está catalogada. Sin él, cada excepción de dominio nueva se degrada en silencio a 500 y culpa al servidor de un error del cliente
- [ ] `DomainExceptionSubscriber` produce application/problem+json; JSON malformado → 400 invalid_request
- [ ] **BLOQUEANTE — validación de forma en el borde.** Los comandos llevan primitivas y su PHPDoc declara la forma exacta; comprobar que el JSON la cumple es trabajo de Delivery. Cada controlador mapea el request a un DTO tipado (`ServiceMachineRequest`, etc.) y **rechaza con 422 antes de construir el comando**. Especialmente `PUT /api/machine/service`: su payload lleva arrays anidados (`products[].selector/name/price/count`, `changeReserve` como mapa céntimos→cantidad) y hoy un campo ausente llegaría al handler y reventaría con `TypeError` → 500 culpando al servidor de un error del cliente
- [ ] Tests que lo demuestren: campo ausente, campo con tipo equivocado (`count: "10"`), array anidado que no es array. Todos deben dar **422 con problem+json**, ninguno 500
- [ ] `ChallengeExamplesTest` de aceptación con los 3 ejemplos literales del enunciado
- [ ] Suite `backend/tests/Acceptance/Http/` cubre cada endpoint + `ProblemDetailsContractTest`
- [ ] nelmio/cors configurado para el frontend local
- [ ] Verificación activa: curl real de un caso feliz y un caso de error, outputs pegados

## Capa
delivery

## Archivos probablemente afectados
- `backend/src/VendingMachine/Delivery/Http/Controller/{GetMachineState,InsertCoin,ReturnCoins,PurchaseProduct,ServiceMachine}Controller.php` (a crear)
- `backend/src/VendingMachine/Delivery/Http/Request/*.php`, `Response/*.php` (a crear)
- `backend/src/VendingMachine/Delivery/Http/Error/{DomainExceptionSubscriber,ProblemDetailsFactory,ErrorCatalog}.php` (a crear)
- `backend/config/routes/api.yaml`, `backend/config/packages/nelmio_cors.yaml`
- `backend/tests/Acceptance/Http/*Test.php` (a crear)

## Enfoque sugerido
1. Rojo primero: `ChallengeExamplesTest` completo (aún sin controladores → todo falla).
2. Endpoint a endpoint hasta verde, empezando por GET /api/machine.
3. El subscriber al final, guiado por `ProblemDetailsContractTest`.

(No prescriptivo — el implementador puede divergir si encuentra mejor camino.)

## ADR asociado
Sí — crear `docs/adr/0012-rfc7807-errors-with-explicit-status-rule.md` (la regla: 422 = valor inválido · 409 = valor válido en conflicto con el estado · 404 = nombras algo inexistente).

## Depende de
09

## Prioridad sugerida
alta — desbloquea frontend (16) y persistencia (11) puede ir en paralelo tras esto.

## Notas y referencias
- `POST /api/machine/purchases` plural a propósito: deja la costura abierta para un read model de compras futuro sin URL RPC.
- Ruta singleton `/api/machine`, no `/api/machines/{id}`: contención deliberada a nombrar en la entrevista (MachineId ya existe; el cambio futuro es de una línea).

## Origen
Desglose de backlog — sesión PM de 2026-08-18.
