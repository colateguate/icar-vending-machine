# Corregir las tres afirmaciones que el README hace sobre sí mismo y ya no son ciertas

## Contexto
El README es entregable evaluado y es lo primero que abre quien revisa el reto. Tres de sus afirmaciones han caducado según avanzaba el frontend, y las tres dicen que el proyecto está menos hecho de lo que está — que es la peor dirección posible en la que equivocarse en un documento de presentación.

No es un desfase nuevo: nació al cerrar los tickets 16 y 17 y ha ido creciendo con el 17b, el 17c, el 20 y el 21. Ninguno de esos tickets lo tocó porque en cada uno habría sido ruido fuera de su carril; acumulado, ya no lo es.

Mismo patrón que el commit `eed480a fix(docs): correct four claims the deliverable makes about itself`, y la misma razón para arreglarlo: un README que se contradice con el repo se lee como descuido, no como modestia.

## Criterios de aceptación
- [ ] `README.md:11` deja de decir que el panel es "the remaining work — tickets 16–17". El panel está construido: 15 (andamio), 16 (cliente API), 17 (panel), 17b (cajón de servicio), 17c (la piel), más 20 y 21. Lo que queda es 13b, 18 y 19
- [ ] `README.md:336` deja de describir `frontend/` como "(scaffolded; tickets 16–17)"
- [ ] `README.md:340` dice **seis** agentes revisores, no cuatro. Existen `security-reviewer`, `architecture-reviewer`, `clean-code-reviewer`, `test-quality-reviewer`, `frontend-architecture-reviewer` y `frontend-test-quality-reviewer` en `.claude/agents/`
- [ ] Se comprueba que no queda ninguna otra afirmación de estado desfasada: contar de verdad lo que se afirma (ADRs, suites, jobs de CI, número de tests) en vez de darlo por bueno. "sixteen decision records" en `README.md:335` **sí** es correcto — 0001 a 0016; la plantilla y el índice no cuentan
- [ ] Ninguna afirmación nueva que no se pueda verificar con un comando

## Capa
docs

## Archivos probablemente afectados
- `README.md` — líneas 11, 336 y 340

## Enfoque sugerido
1. Releer el README entero buscando afirmaciones numéricas o de estado, no solo las tres citadas.
2. Para cada una, el comando que la comprueba. Si no se puede comprobar con un comando, replantear la frase para que sí.
3. Decidir si la línea de "Status" debe seguir enumerando tickets. Un README que nombra números de ticket caduca cada vez que se cierra uno; describir el estado sin citarlos envejece mejor.

(No prescriptivo — el implementador puede divergir si encuentra mejor camino.)

## ADR asociado
No — corrige documentación, no toma ninguna decisión de arquitectura.

## Depende de
—

## Prioridad sugerida
media — no rompe nada, pero es la primera página que lee el evaluador y hoy vende el proyecto por debajo de lo que es.

## Notas y referencias
- Precedente exacto: commit `eed480a`, que corrigió cuatro afirmaciones del mismo tipo.
- El mismo desfase existía en `documentation/que-revisar.md` ("cuatro revisores") y se corrigió durante el ticket 17c. Ese fichero está gitignorado; el README no.

## Origen
Detectado durante implement-feature del ticket 17c, al revisar `documentation/` en la fase de cierre.
