# Corregir el recuento de statuses en ADR-0015

## Contexto
`docs/adr/0015-openapi-as-a-tested-contract.md:17` dice: *"The error contract is eleven codes across six status codes (ADR-0012)."* Son **cinco**, no seis.

`ErrorCatalog::PROBLEMS` (`backend/src/VendingMachine/Delivery/Http/Error/ErrorCatalog.php:43`) tiene once entradas repartidas en 422, 404, 409, 400 y 503 — comprobado contando statuses distintos en la tabla. El seis sale de la tabla de [ADR-0012](../../../docs/adr/0012-rfc7807-errors-with-explicit-status-rule.md) (`:42-47`), que tiene seis filas porque incluye el **500**. Pero el 500 no tiene `code` y no es parte del contrato: el propio ADR-0015 lo excluye del documento a propósito y explica por qué (`docs/openapi.yaml:12-14`, *"500 is not documented. It is not a promise this API makes, it is the promise failing"*).

O sea que la frase mezcla dos recuentos: once `code`s repartidos en cinco statuses, más un sexto status que no tiene ningún `code`. No rompe nada; es una afirmación verificable que un evaluador puede contrastar en treinta segundos contra una tabla de once líneas.

## Criterios de aceptación
- [ ] `docs/adr/0015-...:17` dice cinco, o distingue explícitamente los cinco statuses del contrato del sexto que es la ausencia de contrato
- [ ] El resto del ADR sigue coherente con el cambio (`grep -n "six\|eleven"` sobre el archivo)
- [ ] No se toca la tabla de ADR-0012: sus seis filas son correctas, porque describen la regla de status completa y el 500 forma parte de la regla

## Capa
docs

## Archivos probablemente afectados
- `docs/adr/0015-openapi-as-a-tested-contract.md:17` — una frase
- Fuentes de verdad a leer, no duplicar: `backend/src/VendingMachine/Delivery/Http/Error/ErrorCatalog.php:43` y `docs/adr/0012-rfc7807-errors-with-explicit-status-rule.md:42-47`

## Enfoque sugerido
1. Cambiar el número. Es una palabra.
2. Considerar redactarlo de forma que no vuelva a confundirse — algo como *"eleven codes across five status codes, plus the 500 that is the absence of a contract"* dice las dos cosas y explica de dónde salía el seis.

**Si aparece otra corrección de documentación antes de que esto se aborde, van juntas en un commit.** Ya hay precedente: `eed480a fix(docs): correct four claims the deliverable makes about itself`. Un commit por palabra corregida no es un log más honesto, es un log más largo.

(No prescriptivo — el implementador puede divergir si encuentra mejor camino.)

## ADR asociado
**No** — corrige un dato dentro de un ADR sin tocar la decisión que registra. Y **no** lleva nota fechada, a diferencia de la que 14d añade a las consecuencias negativas de ese mismo ADR: aquella marca un argumento que perdió, esto es un número mal contado, y tratarlos igual devalúa la nota.

## Depende de
—

## Prioridad sugerida
baja — una palabra, sin impacto funcional. Lo que le da valor es dónde está: en el documento donde el proyecto argumenta que un documento escrito a mano se puede mantener honesto con gates. Una cifra mal contada justo ahí se lee peor de lo que es.

## Notas y referencias
- Comprobado contando: `grep -o "'status' => [0-9]*" backend/src/VendingMachine/Delivery/Http/Error/ErrorCatalog.php | sort -u` devuelve 400, 404, 409, 422, 503. Cinco.
- Es el mismo tipo de hallazgo que produjo la PR #18 durante la revisión de release: afirmaciones que el entregable hace sobre sí mismo y que nadie comprueba. Los tests cubren el código; la prosa no la cubre nadie.

## Origen
Detectado durante review-before-push de las ramas 14c/14d/14e — hallazgo Low del `clean-code-reviewer`, verificado después contando statuses distintos en el catálogo.
