# Producir `concurrent_modification` sobre HTTP en la suite de aceptación

## Contexto
`concurrent_modification` es **el único de los once fallos del catálogo que la suite de aceptación nunca produce**. Los otros diez tienen al menos un test que llama a la API, recibe ese error concreto y valida el documento resultante contra `docs/openapi.yaml`; este no.

Lo que sí está probado, por partes: el adaptador lo lanza bajo una carrera real (`backend/tests/Integration/VendingMachine/Infrastructure/Persistence/Doctrine/ConcurrentPurchaseTest.php:60`), la excepción tiene su test de forma (`backend/tests/Unit/VendingMachine/Domain/Exception/ConcurrentMachineModificationTest.php`), el catálogo lo mapea a 409 (`backend/src/VendingMachine/Delivery/Http/Error/ErrorCatalog.php:57`) y el spec lo documenta en las dos direcciones (`OpenApiErrorCoverageTest`). Pero esas pruebas son **composicionales**: nadie ha ejecutado nunca la tubería `subscriber → ProblemDetailsFactory → problem+json` con esta excepción concreta.

**El comportamiento ya se comprobó a mano durante la revisión de release y es correcto** — devuelve 409, `application/problem+json`, con el documento bien formado, y pasa la validación contra el contrato publicado. O sea que esto **no arregla un bug**: fija por escrito algo que hoy funciona y que nada impediría romper.

## Criterios de aceptación
- [ ] Un test en `backend/tests/Acceptance/Http/` obtiene una respuesta **409 `concurrent_modification`** de la API
- [ ] Afirma el status, el `code`, el `Content-Type: application/problem+json` y que el documento cumple el esquema `Problem` del contrato (esto último sale gratis si la petición pasa por `ApiTestCase::request()`)
- [ ] El test **no** usa `requestOutsideTheContract()`: 409 `concurrent_modification` sí está documentado en `docs/openapi.yaml` para las cuatro operaciones que escriben, así que la validación debe correr
- [ ] Queda escrito en el propio test **por qué** hace falta un montaje especial, para que nadie lo "simplifique" después a una petición normal
- [ ] `make qa` en verde

## Capa
delivery (solo tests)

## Archivos probablemente afectados
- `backend/tests/Acceptance/Http/ConcurrentModificationEndpointTest.php` (a crear) — o un método dentro de `PurchaseEndpointTest.php`, si se decide que no merece fichero propio
- `backend/tests/Acceptance/Http/ApiTestCase.php` — solo si el montaje necesita un punto de extensión que hoy no existe
- Fuentes de verdad a leer, no duplicar: `backend/src/VendingMachine/Delivery/Http/Error/ErrorCatalog.php:57` y `backend/src/VendingMachine/Infrastructure/Persistence/Doctrine/DoctrineVendingMachineRepository.php:59`

## Enfoque sugerido
Hay dos caminos y conviene elegir a conciencia, porque no prueban lo mismo.

1. **Sustituir el puerto por un doble que lanza** (probado durante la revisión, funciona). Es coherente con el hexágono: reemplazar un adaptador secundario es exactamente para lo que existe el puerto. Prueba la tubería de errores del borde, que es lo que falta.

   **Trampa concreta, ya encontrada:** `self::getContainer()->set(VendingMachineRepository::class, ...)` falla con *"service is already initialized, you cannot replace it"* si algo pidió antes el repositorio — y `ApiTestCase::givenAStockedMachine()` lo pide. El doble hay que instalarlo **antes de tocar el contenedor**, y por tanto tiene que servir él mismo la máquina en `find()` con `VendingMachineBuilder::aStockedMachine()->withId('lobby-01')->build()`, en vez de apoyarse en el repositorio real.

2. **Provocar el conflicto de versión de verdad**, escribiendo en la fila por fuera entre el `find()` y el `flush()` del request, con la segunda conexión que ya sabe abrir `DoctrineTestEnvironment::anotherEntityManager()`. Más fiel —prueba también el bloqueo optimista, no solo el borde— y bastante más frágil de montar dentro de `WebTestCase` con la conexión que el kernel mantiene viva.

La recomendación es la **1**, y decir en el test que es la 1 y por qué: lo que este nivel tiene que responder es *"¿qué ve el cliente cuando esto pasa?"*, y esa pregunta no necesita que la carrera sea real. Que el adaptador lanza bajo una carrera real ya lo prueba `ConcurrentPurchaseTest`, que es donde esa pregunta vive.

(No prescriptivo — el implementador puede divergir si encuentra mejor camino.)

## ADR asociado
**No** — aplica decisiones ya tomadas: la regla de status de [ADR-0012](../../../docs/adr/0012-rfc7807-errors-with-explicit-status-rule.md), el bloqueo optimista de [ADR-0011](../../../docs/adr/0011-optimistic-locking-for-concurrent-purchases.md) y los niveles de test de [ADR-0014](../../../docs/adr/0014-four-test-levels-mutation-gated-domain.md).

Si se elige el camino 2 y acaba obligando a tocar `ApiTestCase` para todos los tests, eso sí sería una decisión sobre la infraestructura de la suite y merecería un párrafo en ADR-0014.

## Depende de
—

## Prioridad sugerida
media — no arregla nada roto (el comportamiento se verificó a mano y es correcto), pero cierra el único camino de error del que la suite no tiene prueba observable. Es también la respuesta a una pregunta muy probable en la entrevista: *"¿cómo sabes que una carrera devuelve 409 y no un 500?"*.

## Notas y referencias
- Patrón canónico a imitar: `backend/tests/Acceptance/Http/ProblemDetailsContractTest.php`, que es donde vive la forma del envoltorio problem+json.
- Dobles ya disponibles en `backend/tests/Support/Doubles/`: `FailingOnSaveRepository` hace justo esto con una `RuntimeException` y sirve de plantilla, aunque envuelve un repositorio real y por eso choca con la trampa del contenedor descrita arriba.
- El `OpenApiContract` valida cada respuesta que pasa por `ApiTestCase::request()`, así que el criterio del esquema no hay que escribirlo: se cumple o el test falla solo.

## Origen
Detectado durante la revisión de release de la PR #17 (`release/backend` → `main`) — el `test-quality-reviewer` lo señaló como el hueco de conjunto de la suite, y la comprobación manual posterior confirmó que el comportamiento es correcto pero no está fijado.
