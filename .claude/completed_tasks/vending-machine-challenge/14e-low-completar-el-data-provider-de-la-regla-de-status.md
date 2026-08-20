# Completar el data provider de la regla de status del catálogo de errores

## Contexto
`ErrorCatalogTest` tiene dos tests que se reparten el trabajo: uno dice que **el catálogo está completo** (recorre el dominio por reflexión y falla si un fallo no está catalogado) y otro dice que **cada entrada tiene el status que la regla exige**, con un data provider fila a fila.

El segundo está incompleto y su propio comentario afirma lo contrario. `backend/tests/Unit/VendingMachine/Delivery/Http/Error/ErrorCatalogTest.php:55` dice *"The rule, one row per failure"*, pero el provider (`:74`) tiene **8 filas** para las **11 entradas** de `ErrorCatalog::PROBLEMS`. Faltan tres:

| Clase | Status y código esperados |
|---|---|
| `ConcurrentMachineModification` | 409 · `concurrent_modification` |
| `InvalidRequestPayload` | 422 · `invalid_request_payload` |
| `MalformedJson` | 400 · `malformed_json` |

No es casualidad cuáles faltan: el test de completitud recorre `Domain/`, y `InvalidRequestPayload` y `MalformedJson` viven en `Delivery/Http/Error/` — el dominio nunca los ve. `ConcurrentMachineModification` sí es de dominio, y simplemente se quedó fuera.

**El agujero concreto:** hoy nadie puede cambiar el status de esas tres sin que algo proteste, salvo por el spec. Si alguien moviera `concurrent_modification` de 409 a 503 **y actualizara `docs/openapi.yaml` para que cuadre**, la suite entera seguiría verde. Para las otras dos, los tests de aceptación cubren status y código de rebote, así que el riesgo es menor pero la afirmación del comentario sigue siendo falsa.

## Criterios de aceptación
- [ ] `theStatusRule` tiene una fila por cada entrada de `ErrorCatalog::PROBLEMS` — hoy once
- [ ] Las tres filas nuevas usan la misma forma de nombre que las existentes: una frase que explica *por qué* ese status, no el nombre de la clase
- [ ] Cambiar a mano el status de cualquiera de las once entradas del catálogo pone rojo este test. Compruébalo con al menos una, y pega el fallo
- [ ] El comentario de `:55` sigue siendo verdad después del cambio
- [ ] `make qa` en verde

## Capa
delivery (solo tests)

## Archivos probablemente afectados
- `backend/tests/Unit/VendingMachine/Delivery/Http/Error/ErrorCatalogTest.php:74-83` — el data provider; y `:55`, el comentario que hoy promete más de lo que hay
- Fuente de verdad a leer, no duplicar: `backend/src/VendingMachine/Delivery/Http/Error/ErrorCatalog.php:43` (la tabla `PROBLEMS`)

## Enfoque sugerido
1. Añadir las tres filas que faltan. Es un cambio de tres líneas.
2. Verificar el criterio de que muerde: cambia una entrada de `ErrorCatalog` a un status distinto, corre el test, confirma el rojo, revierte.

**Una tentación que conviene rechazar por escrito**, porque va contra el hábito del resto del repositorio: generar el provider desde `FailureClasses::catalogued()` para que no vuelva a quedarse corto. Aquí sería un error. Todos los demás recorridos por reflexión de este repo existen para que **una lista escrita a mano no se quede obsoleta** — pero este test no es una lista, es **una segunda declaración independiente de la regla**. Un provider generado desde el catálogo afirmaría que el catálogo coincide consigo mismo, que es siempre cierto y no prueba nada.

La forma correcta de que no se quede corto otra vez es el criterio de arriba: que el test falle si la tabla cambia. Si eso no basta, un test aparte puede comparar el número de filas contra el número de entradas y fallar cuando se separen — eso sigue siendo una comprobación de completitud, no una derivación de los valores.

(No prescriptivo — el implementador puede divergir si encuentra mejor camino.)

## ADR asociado
**No** — aplica la regla de status ya registrada en [ADR-0012](../../../docs/adr/0012-rfc7807-errors-with-explicit-status-rule.md). No cambia ninguna decisión: hace que el test diga lo que el ADR ya dice.

## Depende de
—

## Prioridad sugerida
baja — tres líneas de datos y un agujero de regresión estrecho. Va aquí y no más arriba porque nada está roto hoy; pero es de las cosas que conviene hacer de paso la próxima vez que alguien abra ese fichero, porque el coste es casi cero y deja de haber un comentario que miente.

## Notas y referencias
- El test hermano `test_every_named_domain_failure_is_catalogued` (`:40`) es el que garantiza que no falte ninguna entrada **en el catálogo**; este garantiza que cada entrada diga lo correcto. Son preguntas distintas y por eso son dos tests, no uno.
- `OpenApiErrorCoverageTest` cruza el catálogo con `docs/openapi.yaml` en las dos direcciones, pero **no comprueba que el status sea el que la regla exige** — solo que ambos documentos digan lo mismo. Por eso los dos pueden estar de acuerdo y equivocados a la vez, que es exactamente el agujero de arriba.

## Origen
Detectado durante la revisión de release de la PR #17 (`release/backend` → `main`), hallazgo Low del `test-quality-reviewer`, verificado después contando filas contra entradas.
