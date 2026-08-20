# Declarar el 400 en las dos operaciones que lo devuelven y no lo documentan

## Contexto
`docs/openapi.yaml` declara `'400'` en **una sola** operación, `POST /api/machine/coins` (`:177`, vía `$ref: '#/components/responses/MalformedJson'`). Pero la API lo devuelve en tres.

Medido, no supuesto — sonda de aceptación mandando `{"broken": ` a cada endpoint con cuerpo:

| endpoint | respuesta real | declarado en el spec |
|---|---|---|
| `POST /api/machine/coins` | 400 `malformed_json` | sí |
| `POST /api/machine/purchases` | 400 `malformed_json` | **no** |
| `PUT /api/machine/service` | 400 `malformed_json` | **no** |
| `POST /api/machine/coins/return` | 200 | correcto: no lee cuerpo |

Y cuadra con la estructura, que es la comprobación que importa: exactamente tres request-DTOs usan `JsonBody` — `InsertCoinRequest`, `PurchaseProductRequest` y `ServiceMachineRequest` — y `JsonBody` es quien lanza (`backend/src/VendingMachine/Delivery/Http/Request/JsonBody.php:44` y `:48`). `ReturnCoinsController` no parsea nada, así que su ausencia del 400 no es un olvido.

**Por qué importa más de lo que parece.** Quien integre contra el contrato publicado no espera un 400 de esas dos operaciones y no lo tratará. Y hay un segundo efecto, de build: desde el ticket 14b `ApiTestCase` valida **toda** respuesta contra el spec, así que el primer test que provoque un cuerpo ilegible en `purchases` o `service` pondrá el pipeline en rojo con *"the contract does not document status 400 for this operation"*. Ya ocurrió durante 14g, al escribir un escenario que mandaba `[]` por error: PHP serializa `[]` como array JSON, la API lo rechazó como "no es un objeto" y el gate protestó. Se sorteó cambiando el test, no arreglando el documento.

## Criterios de aceptación
- [ ] `POST /api/machine/purchases` y `PUT /api/machine/service` declaran `'400'` en `docs/openapi.yaml`, apuntando a la respuesta compartida `#/components/responses/MalformedJson`
- [ ] `POST /api/machine/coins/return` **no** lo declara, y queda dicho por qué: no lee cuerpo, así que no puede fallar al leerlo
- [ ] Un test de aceptación provoca el 400 en cada una de las tres operaciones que lo dan. Sin eso, la declaración nueva no la comprueba nadie: `PublishedExamplesTest` cubre el ejemplo `bodyThatIsNotJson` una sola vez, y le basta con un endpoint
- [ ] `make qa` en verde
- [ ] Ninguna de las tres declaraciones nuevas rompe el gate de ejemplos de 14g — el ejemplo es el mismo (`bodyThatIsNotJson`) publicado en tres sitios, y `OpenApiContract` los deduplica por nombre siempre que el valor sea idéntico

## Capa
delivery (spec + tests), docs

## Archivos probablemente afectados
- `docs/openapi.yaml` — el bloque `responses:` de `POST /api/machine/purchases` y de `PUT /api/machine/service`; el componente ya existe en `:768`
- `backend/tests/Acceptance/Http/ProblemDetailsContractTest.php` — es donde vive la forma del envoltorio problem+json y donde ya está `test_a_body_that_is_not_json_is_a_400`; ampliarlo a las tres operaciones es más barato que una clase nueva
- Fuentes de verdad a leer, no duplicar: `backend/src/VendingMachine/Delivery/Http/Request/JsonBody.php:44,48` (quien lanza) y `backend/src/VendingMachine/Delivery/Http/Error/ErrorCatalog.php:60` (quien lo mapea a 400)

## Enfoque sugerido
1. Añadir el `$ref` en las dos operaciones. Son dos líneas por operación.
2. Convertir `test_a_body_that_is_not_json_is_a_400` en un data provider sobre las tres operaciones. El test existente ya usa `requestWithRawBody`, que valida contra el contrato, así que la declaración nueva queda comprobada por construcción.
3. Comprobar el criterio de que muerde al revés: quita una de las declaraciones nuevas y confirma que el test correspondiente se pone rojo con el mensaje del contrato, no con un assert cualquiera.

**Una pregunta que conviene contestar por escrito y no dar por hecha:** ¿debería `POST /api/machine/coins/return` aceptar y rechazar un cuerpo, en vez de ignorarlo? Hoy devuelve 200 ante `{"broken": `, que es defendible —no pidió nada— y también es un contrato que no declara. No lo cambies dentro de este ticket; decide si merece uno propio.

(No prescriptivo — el implementador puede divergir si encuentra mejor camino.)

## ADR asociado
**No** — aplica la regla de status ya registrada en [ADR-0012](../../../docs/adr/0012-rfc7807-errors-with-explicit-status-rule.md) (400 = no pudimos leer la petición) y el contrato verificado de [ADR-0015](../../../docs/adr/0015-openapi-as-a-tested-contract.md). No cambia ninguna decisión: hace que el documento diga lo que el código ya hace.

## Depende de
—

## Prioridad sugerida
media — el documento publicado es incorrecto sobre dos de sus seis operaciones, y esa es exactamente la clase de defecto que el reto evalúa. No es alta porque nada está roto en ejecución: el cliente recibe un problem+json bien formado, solo que uno que el contrato no le anunció.

## Notas y referencias
- Los tres gates de `docs/openapi.yaml` no pueden ver esto, y merece entenderse: el gate 1 solo comprueba las respuestas que la suite **produce**, y ninguna producía un 400 ahí; el gate 2 cruza el catálogo con el documento **globalmente**, y `malformed_json` sí está documentado, en otra operación; el gate 3 comprueba los ejemplos, y el ejemplo es correcto donde está. Un hueco por operación se les escapa a los tres.
- De ahí sale la pregunta grande, que **no** es este ticket: ¿debería existir un gate que compare, operación por operación, los status que el código puede producir contra los que el documento declara? Requiere saber qué excepciones puede lanzar cada controlador, que no es derivable sin ejecutar. Si a alguien se le ocurre cómo, es un ticket que vale más que este.

## Origen
Detectado durante la implementación del ticket 14g, al escribir un escenario que mandaba un cuerpo que no era un objeto JSON: el gate del contrato protestó porque `POST /api/machine/purchases` no declara 400. Confirmado después con una sonda sobre los cuatro endpoints y contrastado contra qué DTOs usan `JsonBody`.
