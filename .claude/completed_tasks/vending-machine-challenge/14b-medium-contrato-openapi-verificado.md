# Publicar el contrato de la API en OpenAPI y hacerlo verificable

## Contexto
Hoy la única forma de saber qué devuelve esta API es leer los tests de aceptación o el código de `Delivery/Http/Response/`. Quien la integre —el panel del ticket 16, o quien evalúe con Postman— no tiene un contrato que importar, y el ticket 14 solo contrata una referencia en prosa dentro del README.

Un `openapi.yaml` resuelve las dos cosas a la vez: es legible, y Postman, Insomnia, Bruno y Swagger UI lo importan de forma nativa, así que la colección sale gratis sin mantener un `collection.json` versionado que se desincroniza en silencio.

Pero un spec escrito a mano envejece igual de mal que esa colección. **Lo que hace que este ticket valga la pena no es el fichero, es el gate**: si el spec y las respuestas reales dejan de coincidir, la suite se pone roja.

## Criterios de aceptación
- [ ] `docs/openapi.yaml` (OpenAPI 3.1) con los cinco endpoints de `backend/config/routes/api.yaml` más `GET /api/health`: método, ruta, cuerpo de petición y respuestas
- [ ] Dinero como `string` con `pattern` decimal en TODO el spec — nunca `number`, que es la trampa que el modelo entero existe para evitar (ADR-0004)
- [ ] Esquema `Problem` (RFC 7807) con los cinco miembros que emite `ProblemDetailsFactory.php:33` (`type`, `title`, `status`, `detail`, `code`) y las extensiones que añade (`changeDue`, `missingAmount`, `field`)
- [ ] **Ejemplos capturados de ejecuciones reales**, no escritos de memoria: al menos un 200 por endpoint y un ejemplo por cada familia de error (422 de forma, 422 de valor, 404, 409, 400, 503)
- [ ] **Test de exhaustividad de códigos**: recorrer `ErrorCatalog::PROBLEMS` (`ErrorCatalog.php:43`) y fallar si algún `code` no aparece documentado en el spec. Hoy son once: `unsupported_coin`, `invalid_money_amount`, `invalid_product_selector`, `invalid_request_payload`, `unknown_product`, `product_out_of_stock`, `insufficient_funds`, `exact_change_required`, `concurrent_modification`, `malformed_json`, `machine_not_provisioned`
- [ ] **Validación de respuestas reales contra el esquema**: las respuestas que ya producen los tests de `backend/tests/Acceptance/Http/` se validan contra `openapi.yaml`. Un cambio de forma en la API sin actualizar el spec deja la suite roja
- [ ] README enlaza el spec y explica en dos líneas cómo importarlo en Postman
- [ ] `make qa` en verde

## Capa
docs, delivery

## Archivos probablemente afectados
- `docs/openapi.yaml` (a crear)
- `backend/tests/Acceptance/Http/OpenApiContractTest.php` (a crear) — valida las respuestas reales contra el spec
- `backend/tests/Unit/VendingMachine/Delivery/Http/Error/ErrorCatalogTest.php` — o un test hermano, para la exhaustividad de códigos contra el spec
- `backend/composer.json` — `league/openapi-psr7-validator` en `require-dev` (comprobado: pide `php >=7.2`, compatible con el 8.2 del proyecto)
- `README.md` — enlace y nota de importación
- Fuentes de verdad a leer, no a duplicar: `backend/src/VendingMachine/Delivery/Http/Response/{MachineStateResponse,CoinsResponse,DispensedResponse}.php` (las formas ya están declaradas como array shapes de PHPStan, p.ej. `MachineStateResponse.php:25`) y `backend/src/VendingMachine/Delivery/Http/Error/ErrorCatalog.php`

## Enfoque sugerido
1. Rojo primero: `OpenApiContractTest` sobre un spec vacío o incompleto, para ver el fallo antes de que el spec exista.
2. Escribir el spec endpoint a endpoint, capturando cada ejemplo con `curl` contra el stack de `make up` y pegando la respuesta literal.
3. El test de exhaustividad de códigos al final, que es el que impide que un error futuro se quede sin documentar.
4. Decidir cómo se conectan los tests de aceptación con el validador sin duplicar las peticiones: lo más barato es un test que repita las llamadas clave, lo más completo es un hook en `ApiTestCase` que valide toda respuesta que pase por él — evaluar cuál se sostiene mejor.

(No prescriptivo — el implementador puede divergir si encuentra mejor camino.)

## ADR asociado
Sí — crear `docs/adr/0015-openapi-as-a-tested-contract.md`: por qué un spec verificado y no una colección de Postman (artefacto derivado que se desincroniza), y por qué OpenAPI y no API Blueprint/Apiary (formato legacy desde la compra de Apiary por Oracle). La consecuencia negativa honesta: el spec es una segunda declaración de una forma que ya vive en los array shapes de PHPStan, y el test es lo único que impide que sean dos verdades distintas.

## Depende de
13

## Prioridad sugerida
media — no bloquea nada del backend, y es lo que hace que la API sea consumible por alguien que no ha leído el código. El ticket 16 (cliente del frontend) se beneficia directamente.

## Notas y referencias
- Patrón canónico a imitar: `ErrorCatalogTest::test_every_named_domain_failure_is_catalogued` — recorre el dominio por reflexión y falla si algo no está catalogado. El test de códigos contra el spec es el mismo truco un nivel más afuera.
- Los ejemplos de `documentation/usar-la-consola.md` ya se capturaron de ejecuciones reales; ese es el listón para los del spec.
- Ojo con el orden de las monedas: la API las serializa ascendentes y el CLI descendente (`docs/assumptions.md`). El spec documenta la API, no el CLI.
- `GET /api/health` existe desde el ticket 02 y hoy no está documentado en ningún sitio de cara al usuario; el healthcheck del contenedor depende de él.

## Origen
Manual — pregunta del usuario sobre publicar una colección de Postman o documentación tipo Apiary, resuelta a favor de un spec OpenAPI verificado.
