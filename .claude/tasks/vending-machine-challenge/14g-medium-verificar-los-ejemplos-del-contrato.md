# Comprobar que los ejemplos publicados en el spec son los que la API devuelve

## Contexto
La cabecera de `docs/openapi.yaml:6-7` afirma: *"the examples below are not written from memory: each was captured from a real run of that suite"*. Nada comprueba esa afirmación, y ya fue falsa una vez.

Lo que sí se comprueba: `OpenApiContract::documentedProblems()` (`backend/tests/Support/OpenApi/OpenApiContract.php:52`) recorre los ejemplos del spec y extrae de cada uno **solo `status` y `code`** (`:73-80`), que es lo que `OpenApiErrorCoverageTest` cruza contra `ErrorCatalog` en las dos direcciones. Los otros tres miembros del documento problem+json —`type`, `title` y `detail`— no se comparan con nada. El gate de respuestas valida contra el **esquema**, y un ejemplo inventado cumple el esquema exactamente igual de bien que uno real.

**Cómo se manifestó:** el ejemplo de `concurrent_modification` (`docs/openapi.yaml:740-747`) publicó durante toda la release un `title` y un `detail` que la API nunca ha devuelto. No podía ser de otra manera — era el único fallo que la suite no producía, así que no había ejecución de la que capturarlo — y no se descubrió por ningún gate, sino porque el ticket 14d lo produjo por primera vez y el assert del documento completo chocó. Los otros diez ejemplos probablemente son correctos; el punto es que *probablemente* es todo lo que se puede decir.

## Criterios de aceptación
- [ ] Un test falla si el `title` de un ejemplo problem+json del spec no es el que `ErrorCatalog` tiene para ese `code`
- [ ] Un test falla si el `type` de un ejemplo no es el que `ProblemType::typeUri()` deriva de su `code`
- [ ] Queda decidido y escrito qué se hace con `detail`, que es el miembro que **no** se puede derivar: sale del mensaje de la excepción y a veces lleva datos de la petición (`'The machine "lobby-01" was changed...'`). O se compara contra respuestas reales capturadas, o se declara fuera de alcance con el motivo
- [ ] Mutar a mano el `title` de un ejemplo del spec pone rojo la suite. Compruébalo y pega el fallo
- [ ] La afirmación de `docs/openapi.yaml:6-7` sigue siendo verdad después del cambio — o se reescribe para decir exactamente lo que ahora se garantiza
- [ ] `make qa` en verde

## Capa
delivery (solo tests), docs

## Archivos probablemente afectados
- `backend/tests/Unit/VendingMachine/Delivery/Http/Error/OpenApiErrorCoverageTest.php` — es donde ya vive el cruce catálogo ↔ spec; el test nuevo es la misma pregunta sobre más miembros
- `backend/tests/Support/OpenApi/OpenApiContract.php:52` (`documentedProblems`) y `:96` (`examplesOf`) — `examplesOf` ya devuelve el ejemplo entero; `documentedProblems` es quien tira todo salvo dos campos. Puede hacer falta un método hermano que los conserve, en vez de ensanchar este
- Fuentes de verdad a leer, no duplicar: `backend/src/VendingMachine/Delivery/Http/Error/ErrorCatalog.php:43` (la tabla, de donde sale `title`) y `backend/src/VendingMachine/Delivery/Http/Error/ProblemType.php:50` (`typeUri()`, que deriva `type` del `code`)

## Enfoque sugerido
1. Empezar por `title` y `type`, que son los baratos: ambos son derivables de algo que ya está en el código, así que el test compara dos declaraciones independientes sin necesitar ninguna ejecución.
2. Decidir `detail` a conciencia. Hay dos caminos y no valen lo mismo:
   - **Declararlo fuera de alcance** con el motivo escrito: `detail` es prosa dirigida a una persona y depende de datos de la petición, así que fijarlo por test convierte cada cambio de redacción en un test rojo. Es una postura defendible.
   - **Capturarlo de verdad**: hacer que la suite de aceptación vuelque las respuestas problem+json que produce y comparar el spec contra ese volcado. Es lo que la cabecera promete hoy, y es bastante más máquina.
3. **La tentación a rechazar por escrito, igual que en 14e**: generar los ejemplos del spec desde el código. Un documento generado no puede discrepar del código porque deja de ser una segunda declaración — y ADR-0015 eligió una escrita a mano *precisamente* para tener dos declaraciones que un test compara. Generar los ejemplos ganaría este test y perdería el motivo por el que el documento existe.

(No prescriptivo — el implementador puede divergir si encuentra mejor camino.)

## ADR asociado
**No**, mientras se elija el camino barato — aplica [ADR-0015](../../../docs/adr/0015-openapi-as-a-tested-contract.md), que ya decidió documento escrito a mano + gates que lo comparan contra el código.

**Sí** si se elige capturar las respuestas reales para comparar `detail`: eso añade un tercer gate con maquinaria propia, y ADR-0015 enumera exactamente dos. Un párrafo en ese ADR, no un registro nuevo.

## Depende de
14d (produjo `concurrent_modification` por primera vez, que es lo que dejó los once fallos disponibles para comparar)

## Prioridad sugerida
media — no hay nada roto *ahora*: el ejemplo malo ya se corrigió en 14d. Va aquí y no en baja porque el documento publicado hace una promesa explícita que nada sostiene, y esa promesa ya se incumplió una vez sin que ningún gate lo notara. Es además el hallazgo más presentable de la review: lo encontró un test escrito para otra cosa.

## Notas y referencias
- Después de 14d, la suite de aceptación afirma **los once** códigos del catálogo (comprobado). Antes eran diez. O sea que la promesa de la cabecera es por fin comprobable para todos, que es lo que hace este ticket posible ahora y no antes.
- `examplesOf()` (`:96`) ya maneja las dos formas que usa este spec —`example:` único y `examples:` con nombre—, así que el trabajo de leerlos está hecho.
- Los tres ejemplos con nombre propio viven en `components/examples` (`docs/openapi.yaml:739`); el resto son inline en cada respuesta. El test tiene que ver los dos, y `examplesOf()` ya los ve.

## Origen
Detectado durante review-before-push de las ramas 14c/14d/14e, al comprobar qué códigos produce ya la suite frente a los que el spec ejemplifica. El fallo concreto lo había destapado 14d unas horas antes, arreglando el ejemplo pero no el hueco que lo permitió.
