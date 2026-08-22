# Comprobar los ejemplos de éxito publicados en docs/openapi.yaml

## Contexto

`docs/openapi.yaml` publica cinco ejemplos de respuesta correcta — `stocked` (`:115`), `oneEuroInserted` (`:179`), `twoDimesBack` (`:288`), `exactMoney` (`:371`) y `oneProductOneCoin` (`:582`) — y **no los comprueba nada**. Los gates existentes validan las respuestas *reales* de la suite contra el schema (`OpenApiContract::assertResponseMatches`, `:214`) y ejecutan los ejemplos de error (`PublishedExamplesTest:44`), pero la recolección de ejemplos filtra por `self::PROBLEM_MEDIA_TYPE` (`OpenApiContract.php:149`), así que los de `application/json` quedan fuera de las dos redes.

Esto no es teórico: durante el ticket 03, al ganar el estado los campos `supportedCoins` y `outOfService`, **los cinco ejemplos quedaron inválidos contra su propio schema** (les faltaban dos propiedades `required`) y ninguna suite se puso roja. Se detectaron a mano y se arreglaron en ese mismo commit, verificándolos con un validador desechable. El agujero sigue abierto, y es el mismo que ya justificó el ADR-0015: un documento que promete algo que el código no da envejece en silencio, y este es el trozo del documento que nadie vigila.

## Criterios de aceptación

- [ ] Un test falla si un ejemplo publicado bajo `application/json` **no cumple su propio schema** (propiedad `required` ausente, tipo equivocado, propiedad de más con `additionalProperties: false`).
- [ ] Ese test se demuestra rojo antes de darse por bueno: borrar `outOfService` de un ejemplo lo pone en rojo nombrando el ejemplo, y restaurarlo lo devuelve a verde. Output pegado.
- [ ] Los cinco ejemplos actuales pasan sin tocarlos (hoy son válidos; el arreglo se hizo en el ticket 03).
- [ ] Un ejemplo nuevo sin nombre (`example:` en vez de `examples:`) falla con un mensaje que dice qué hacer, como ya hace `OpenApiContract::examplesOf` (`:191`) para los de problem+json.
- [ ] Decidir explícitamente — y dejarlo escrito en el test — si además se exige que el ejemplo sea **una respuesta que la API dé de verdad** (lo que hace `PublishedExamplesTest` con los errores) o solo que cumpla el schema. Ver "Enfoque sugerido": no son la misma promesa ni cuestan lo mismo.
- [ ] `make qa` en verde.

## Capa

docs, delivery (los tests viven en `backend/tests/`)

## Archivos probablemente afectados

- `backend/tests/Support/OpenApi/OpenApiContract.php:130-186` — `documentedExamples()` y el filtro por `PROBLEM_MEDIA_TYPE` de `:149`; probablemente hace falta un recolector hermano que no filtre por media type
- `backend/tests/Support/OpenApi/OpenApiContract.php:191` — `examplesOf()`, ya reutilizable tal cual (exige nombre y devuelve el valor)
- `backend/tests/Acceptance/Http/PublishedExamplesTest.php:44` — el patrón a imitar; decidir si el test nuevo vive aquí o en un fichero propio
- `docs/openapi.yaml:115,179,288,371,582` — los cinco ejemplos bajo vigilancia (no se tocan si el test pasa)

## Enfoque sugerido

1. Rojo primero: quitar un campo `required` de un ejemplo y comprobar que hoy **nada** falla — es la prueba de que el hueco existe.
2. Recolectar los ejemplos nombrados de cualquier media type (generalizar `documentedExamples()` o escribir su hermano) y validar cada uno contra el schema de su operación con el `SchemaValidator` que ya trae `league/openapi-psr7-validator`.
3. Sobre el criterio abierto: validar **contra el schema** es barato y atrapa el fallo que ya ocurrió. Exigir que el ejemplo sea una respuesta real, como hace `PublishedExamplesTest`, atrapa además ejemplos plausibles pero falsos (un `dispensableAsChange` equivocado) — y cuesta escribir el escenario que produce cada uno, que es justo lo que hace útil a ese test. Empezar por lo primero y razonar en el propio test por qué se paró ahí es una respuesta legítima; hacer las dos también. Lo que no vale es dejarlo sin decidir.

(No prescriptivo — el implementador puede divergir si encuentra mejor camino.)

## ADR asociado

No — aplica ADR-0015 (el contrato OpenAPI como test), y precisamente cierra el trozo que aquel ADR dejó sin cubrir. Si al implementarlo se decide **no** exigir que los ejemplos sean respuestas reales, esa renuncia sí merece una frase en ADR-0015 más que un ADR nuevo.

## Depende de

—

## Prioridad sugerida

media — no hay bug en producción y los cinco ejemplos son correctos hoy; pero el contrato es entregable evaluado, los ejemplos son lo primero que un integrador copia, y ya se demostró que pueden pudrirse sin que nadie se entere.

## Notas y referencias

- Patrón canónico a imitar: `PublishedExamplesTest` y su mensaje de fallo, que dice qué hacer ("Write the scenario below, or stop publishing the example").
- El repo ya ha sido mordido cuatro veces por cifras y documentos publicados que envejecieron; este ticket convierte una de esas clases en un gate.
- Cuidado con el coste: si se opta por "el ejemplo es una respuesta real", cada ejemplo necesita su escenario, y `oneProductOneCoin` (`:582`, la respuesta del service) exige montar una máquina concreta.

## Origen

Detectado durante implement-feature del ticket 03 (contrato de monedas configurables) — los cinco ejemplos quedaron inválidos a mitad del ticket sin que ninguna suite lo notara
