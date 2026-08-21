# Contar en la estrategia de test los niveles del panel, o decir que no los cuenta

## Contexto
`docs/testing-strategy.md` se abre diciendo que el enunciado pide tests que demuestren "what and how to test at different levels" y que **este documento es la respuesta escrita** (línea 3). El `README.md:254` lo enlaza como **"Full strategy →"**.

No lo es. Sus siete secciones —`Four suites, four questions`, el contract test, el contrato publicado como aserción, mutation testing, lo que comprueba el pipeline, lo que la suite deliberadamente no hace— hablan **solo del backend**. Un `grep -i "frontend\|panel\|vitest\|playwright"` sobre el fichero no devuelve una línea.

Mientras tanto el repo tiene hoy tres niveles más que ese documento no menciona: los tests de módulo de `services/`, los de componente con Testing Library, y desde el ticket 24 los cinco specs de navegador de `frontend/e2e/`. Son 115 tests y un ADR (0017) que ese documento ignora.

Es el mismo desfase que el ticket 22 corrigió en el README, con un agravante: aquí no es una cifra vieja, es un documento cuyo título promete el doble de lo que entrega. Y la estrategia de test **es** uno de los criterios de evaluación declarados del reto, así que este es exactamente el fichero que se lee entero.

La decisión no es obvia y por eso el ticket existe: puede que la respuesta correcta sea acotar el título en vez de ampliar el contenido. El backend es lo evaluado, y un documento que dedica media página a Vitest para ser exhaustivo diluye el argumento que sí importa.

## Criterios de aceptación
- [ ] Se elige **una** de las dos, y se defiende en el commit: (a) el documento cubre los cinco niveles del repo, con la misma tabla de "qué pregunta contesta cada uno" que ya usa para los cuatro del backend; (b) el documento se declara explícitamente del backend, y el enlace del README deja de llamarlo "Full strategy"
- [ ] Si se elige (a), los niveles del panel se describen con el mismo criterio que los del backend: **qué pregunta contesta cada uno**, no qué herramienta usa. El de `frontend/e2e/` ya tiene esa respuesta escrita en `frontend/e2e/README.md` y no debe duplicarse, sino enlazarse
- [ ] Si se elige (b), queda dicho dónde vive la estrategia del panel — hoy está repartida entre `CLAUDE.md:112`, `docs/adr/0016`, `docs/adr/0017` y `frontend/e2e/README.md`, y nada lo dice en un solo sitio
- [ ] Ninguna cifra nueva sin comando que la compruebe. Hoy son 478 backend y 115 panel; las del backend ya están en la tabla de la línea 9
- [ ] `README.md:254` y el texto del enlace acaban diciendo lo que el documento de verdad contiene

## Capa
docs

## Archivos probablemente afectados
- `docs/testing-strategy.md` — el documento entero; en particular la línea 3 (la promesa), la 5 (`## Four suites`) y la 134 (`## Things this suite deliberately does not do`), que es donde una omisión deliberada se declararía
- `README.md` — línea 254, el enlace "Full strategy →", y la 248, que resume la estrategia en prosa
- `CLAUDE.md` — línea 112, que hoy es la única descripción completa de los niveles del panel
- `frontend/e2e/README.md` — la frontera del quinto nivel, ya escrita; fuente a enlazar, no a copiar

## Enfoque sugerido
1. Leer el documento entero de una sentada preguntando por cada sección: ¿esto es una afirmación sobre el repo o sobre el backend? La sección `What the pipeline checks` (línea 112) es la que peor envejece: hay diez jobs y ella cuenta siete.
2. Decidir antes de escribir. Ampliar y acotar producen documentos distintos, y empezar a escribir sin haber elegido produce el híbrido que no defiende ninguna de las dos.
3. Sea cual sea, dejar el fichero con la propiedad que el ticket 22 dejó en el README: cada afirmación, su comando.

(No prescriptivo — el implementador puede divergir si encuentra mejor camino.)

## ADR asociado
No — no toma ninguna decisión de arquitectura. Documenta las que ya están tomadas en los ADR-0014, 0016 y 0017.

## Depende de
—

## Prioridad sugerida
media — el documento es entregable evaluado y hoy promete más de lo que da, que es la peor dirección. Pero no rompe nada y el backend, que es lo que se juzga, sí está bien cubierto.

## Notas y referencias
- Precedente del criterio a aplicar: ticket 22, commit `8c34a4b fix(docs): correct what the README claims about itself`, donde cada cifra se verificó con un comando antes de escribirla.
- La sección `Things this suite deliberately does not do` (línea 134) es el precedente interno de la opción (b): este repo ya tiene la costumbre de declarar los límites en vez de dejarlos deducir.
- Lo señaló `frontend-architecture-reviewer` durante la review previa al push del ticket 24, como observación fuera del alcance de aquel diff.

## Origen
Detectado durante review-before-push del ticket 24.
