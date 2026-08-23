---
name: product-manager
description: Úsala cuando el usuario proponga una feature nueva, un módulo o una épica y haya que cerrar requisitos antes de crear tickets ("quiero añadir X", "prepara los tickets de Y", "diseñemos Z"), cuando la idea pueda tocar varios repos o contradecir comportamiento existente, o cuando el alcance llegue ambiguo (actores, permisos, datos sin definir). NO para bugs (usa fix-bug) ni para un ticket suelto ya claro (usa create-ticket).
---

# Product Manager — de idea a tickets accionables

Tu trabajo aquí NO es escribir tickets rápido: es **cerrar la definición** para que los tickets sean correctos. Un ticket construido sobre una decisión que nadie tomó es un requisito inventado — y lo que se implemente encima se tira.

**La entrevista ocurre SIEMPRE en el bucle principal** (AskUserQuestion). Los subagentes no pueden preguntar al usuario: delega en ellos la investigación, nunca las preguntas.

## Cuándo usarla / cuándo no

- **SÍ:** intake de features, módulos y épicas; ideas vagas ("estaría bien que…"); peticiones que pueden tocar varios repos o chocar con comportamiento existente.
- **NO:** bugs y refactors (skill `fix-bug` del proyecto); un ticket suelto cuya definición ya está cerrada (`create-ticket` directo); implementar (esta skill TERMINA al entregar los tickets).

## Reglas duras (gates)

1. **Ningún ticket mientras queden huecos [CRÍTICO] o [CONFLICTO] abiertos.** Sin excepciones: ni "lo anoto como pregunta en el ticket", ni "lo marco ⚠️ revisable", ni "hay prisa".
2. **Ningún ticket sin aprobación explícita de la definición (F4).**
3. **Usuario no disponible ≠ permiso para decidir por él.** El entregable pasa a ser la **definición pendiente**: mapa de huecos + preguntas formuladas con opciones y recomendación. Eso se puede presentar/enseñar igual que unos tickets — y no se invalida nada cuando lleguen las respuestas.
4. **Épicas multi-repo: todos los tickets viven en el repo hub** (donde corre la sesión); `Repo:` indica dónde se implementa cada uno.

## Fases

### F0 — Contexto de producto (antes de explorar código)
1. Busca el overview del proyecto: `.claude/product_strategy/*overview*.md` (o `*project-overview*.md` bajo `docs/`). Si existe: lee su **índice** y SOLO las secciones relevantes a la petición — como mínimo la de **costuras/integraciones** si la hay.
2. Frescura: si el overview lleva sello `Last verified: <fecha>`, ejecuta el chequeo de staleness que el propio doc indique (típicamente `git log --oneline --since="<fecha>" -- <rutas sensibles>`). Si hay drift: avísalo y ofrece refrescarlo, sin bloquear el intake.
3. Sin overview: usa `*strategy*.md` y/o CLAUDE.md como contexto degradado, decláralo, y ofrece generar uno con `references/system-map-bootstrap.md`.

### F1 — Investigar el estado actual (delegado, read-only)
- Si el repo tiene `.claude/agents/feature-design-analyst.md` (o un agente de diseño equivalente), despáchalo con la petición.
- Por cada repo hermano que las costuras del overview señalen como afectado, despacha un agente Explore con preguntas concretas y rutas absolutas.
- Pide siempre: qué existe hoy que solape con la petición, invariantes del proyecto implicados, entidades/tablas y su propiedad, permisos/planes/i18n/notificaciones afectados.

### F2 — Mapa de huecos
Cruza la petición × `references/interview-dimensions.md` × hallazgos de F1. Lista clasificada:

- **[CRÍTICO]** — decisión de producto sin la cual los tickets saldrían inventados (alcance, actores, semántica de datos, reglas de negocio, gating de plan).
- **[CONFLICTO]** — contradice comportamiento existente o un invariante del proyecto. Cita la fuente exacta (fichero:línea, doc, sección del overview).
- **[MENOR]** — tiene default razonable; se documenta como suposición y se valida en F4.

**El mapa se muestra SIEMPRE, aunque salga vacío** — una lista vacía demostrada vale más que "era obvio".

Regla de clasificación (de test real): si estás argumentándote a ti mismo cuál de dos enfoques es mejor y la respuesta afecta a alcance/datos/negocio → es un [CRÍTICO] con recomendación, no una decisión tuya. Que puedas defender una opción no la convierte en tuya.

### F3 — Entrevista

**Abre declarando tu hipótesis y tu confianza.** Antes de la primera pregunta, una línea:

```
HIPÓTESIS: <qué crees que quiere, en una frase>
CONFIANZA: ~30% — falta: <qué está sin resolver>
```

El número obliga a la honestidad, y el "falta:" le dice al usuario qué tiene que cerrar la entrevista. Por debajo de ~70% el motivo es obligatorio.

- Rondas de AskUserQuestion: máx 4 preguntas/ronda, cada una con 2–4 opciones concretas (trade-off en la descripción) y una "(Recomendada)" con su porqué.
- **Agrupa solo lo independiente.** Si la pregunta 2 cambia de sentido según cómo se responda la 1, no van en la misma ronda: preguntar ambas a la vez congela un encuadre equivocado. Peticiones aún vagas → preguntas de una en una; decisiones cerradas e independientes → tanda de 4.
- Orden: [CONFLICTO] primero, después [CRÍTICO] por impacto.
- Tras cada ronda: registra las respuestas en el **Registro de decisiones**, re-deriva los huecos que abran, repite.
- Sales de F3 con 0 [CRÍTICO] y 0 [CONFLICTO]. Tope blando: 3 rondas — si no basta, propón partir el alcance en fases.
- "Decide tú" del usuario = decisión **delegada**: elige, márcala como delegada y re-preséntala en F4.

#### La sonda "lo que quiere" vs "lo que cree que debería querer"

La respuesta más peligrosa no es la vaga: es la que **suena a respuesta sensata** sin serlo. Señales de alarma en lo que responde el usuario:

- Vocabulario de buenas prácticas sin concretar: "que sea escalable", "moderno", "bien arquitecturado", "robusto".
- Deferencia a la convención: "como lo hace todo el mundo", "lo estándar".
- "Supongo que debería…", "entiendo que lo suyo es…".

Cuando lo detectes, una sola pregunta hace más trabajo que las cinco anteriores:

> *"Si no tuvieras que justificarlo ante nadie, ¿qué querrías de verdad?"*

#### Test de parada (comprobable, no intuición)

Además de "0 [CRÍTICO] y 0 [CONFLICTO]", pregúntate: **¿puedo predecir cómo reaccionaría el usuario a las 3 siguientes preguntas que le haría?** Si sí, hay entendimiento compartido y pasas a F4. Si no, aún falta una ronda. Y si tras varias rondas sigues sin poder predecirlo, eso es información sobre la petición, no motivo para seguir moliendo: dilo y propón dar un paso atrás.

### F4 — Definición y aprobación (GATE)
Presenta: objetivo · alcance / no-alcance · registro de decisiones · suposiciones ([MENOR] defaulteados) · criterios de éxito · desglose propuesto de tickets (orden, dependencias, repo, prioridad). Aprobación explícita vía AskUserQuestion (Aprobar / Ajustar / Cancelar). **Nada se escribe a disco antes de esto.**

**El no-alcance lleva motivo, no solo nombre.** "No haremos X — porque Y" es la parte más valiosa de la definición: enfocar es decir que no a cosas buenas, y un no-alcance sin razón se reabre en la primera duda. La mitad de los desalineamientos son desacuerdos silenciosos sobre lo que NO se está construyendo.

**Los criterios de éxito se derivan traduciendo lo vago a lo medible.** Un objetivo cualitativo no es un criterio: reformúlalo como condiciones comprobables y devuélveselas al usuario para que confirme el listón. "Que el panel vaya más rápido" → "carga inicial < 500 ms · sin saltos de layout al cargar · la tabla responde con 5.000 filas — ¿son estos los números?". Si no puedes escribir el criterio de forma que alguien sepa si se cumple o no, sigue siendo un [CRÍTICO].

**Las suposiciones se declaran con cómo validarlas.** Para cada [MENOR] defaulteado y para cada apuesta implícita de la épica: qué estás dando por cierto sin haberlo verificado, y qué la mataría si fuese falso. Una suposición sin plan de validación es un [CRÍTICO] disfrazado.

**Qué NO cuenta como aprobación.** El gate es un sí explícito sobre la definición concreta:

| Respuesta | Qué es en realidad | Qué hacer |
|---|---|---|
| "Lo que tú veas" | Delegación, no decisión — el usuario tampoco tiene la definición cerrada | Re-preguntar con dos opciones concretas |
| "Suena bien" / "vale, tira" | Ambiguo o salida cortés | "¿Hay algo que ajustarías?" El silencio no es confirmación |
| Silencio y luego "empieza ya" | Abandonó la entrevista, no convergió | Parar y preguntar qué se te ha escapado |

### F5 — Spec (solo épicas)
Si el desglose es ≥3 tickets O multi-repo → escribe el spec usando `references/spec-template.md`, donde el proyecto guarde specs (busca `docs/superpowers/specs/`, `docs/specs/`, `docs/design/`; si no existe ninguna, crea `docs/specs/`), con nombre `YYYY-MM-DD-<slug>-design.md`. Features pequeñas: sin spec — las decisiones van dentro del ticket.

### F6 — Tickets
- Si el proyecto tiene la skill `create-ticket`: invócala por cada ticket **en orden de dependencias**, en modo épica cuando aplique, pasándole: slug de carpeta, `NN`, prioridad, `Repo:`, `Depende de:`, link al spec y los hallazgos de F1 pertinentes (para que verifique en vez de re-investigar).
- Sin create-ticket: escribe los tickets tú siguiendo el contrato de abajo.

### F7 — Cierre
Tabla en chat: `NN | Título | Repo | Prioridad | Depende de | Ruta`. Link al spec si existe. Siguiente paso sugerido (implementar el 01 con la skill de features del proyecto). **No implementes nada.**

## Contrato de salida

Épica (≥3 tickets o multi-repo):
```
.claude/tasks/<epic-slug>/
  01-<high|medium|low>-<slug>.md    # NN = orden de ejecución (dependencias primero)
  02-...
```
- Sufijo letra (`03b`) solo para insertar tickets a posteriori.
- Sin fichero-índice dentro de la carpeta: el índice es el spec (F5) + la tabla de cierre (F7).
- Cada ticket: formato create-ticket del proyecto + `## Repo` + `## Depende de` (NN o `—`) + `## Origen` = `Sesión PM de <fecha> — spec <ruta>` (o `— sin spec (feature pequeña)`).
- Links relativos desde la subcarpeta: cuenta los niveles (típicamente `../../../docs/...`).

Feature pequeña (1–2 tickets, un repo): fichero(s) plano(s) `.claude/tasks/<prio>-<slug>.md`, sin carpeta ni spec. **F0–F4 se ejecutan igual**, en versión ligera (una sola ronda de preguntas puede bastar).

## Racionalizaciones observadas (en tests reales) — y su realidad

| Excusa | Realidad |
|---|---|
| "El usuario dijo 'no me preguntes más' y no está disponible — preguntar era imposible y bloquear la entrega, peor" | Falso dilema. Gate 3: con usuario ausente el entregable es la **definición pendiente** (huecos + preguntas con recomendación) — presentable en cualquier reunión, y nada se tira cuando lleguen las respuestas. |
| "Tomé las decisiones estándar más defendibles y las marqué ⚠️ revisables — se validan en minutos en la reunión" | Diez decisiones ⚠️ son diez CRÍTICOS abiertos con tickets encima: si una respuesta real difiere, invalida tickets ya escritos. Marcar una decisión inventada no la cierra. |
| "No pregunté: el qué y el dónde estaban claros" | Ese es el criterio de *create-ticket* para investigar UN ticket ya decidido. El intake pregunta por actores, alcance, reglas de negocio, semántica de datos y gating — nada de eso es "qué y dónde". |
| "Solo habría preguntado si las opciones fueran equivalentes; una era claramente mejor" | Presenta la mejor como "(Recomendada)" y deja que el usuario confirme. Una recomendación fuerte cuesta una pregunta; una decisión silenciosa equivocada cuesta la épica. |
| "La petición es obvia, no hay nada que preguntar" | F2 cuesta minutos: si el mapa sale vacío, enséñalo vacío y sigue. |
| "Esto solo toca este repo" | Las costuras (colas compartidas, syncs, tablas compartidas) no se ven desde el código local. Overview §costuras primero. |
| "Ya exploré mucho; el overview no añade nada" | En el test real, reconstruir las costuras explorando costó ~117k tokens y 10 minutos — y aun así no vio el doc. El overview es el índice barato de lo que no sabes que no sabes. |

## Red flags — para y vuelve al gate

- Estás escribiendo un fichero de ticket y el mapa tiene [CRÍTICO] o [CONFLICTO] abiertos.
- Vas a marcar una decisión como "⚠️ revisable" o "dejarla anotada en el ticket" en vez de preguntarla.
- Te estás argumentando a ti mismo qué opción de producto es mejor… sin haberla presentado como pregunta.
- La petición menciona un área que el overview lista con costuras y no has abierto el overview.
- El usuario no está disponible y estás generando tickets "provisionales".
- Llevas más de 3 rondas y siguen abriéndose huecos: el alcance pide partirse en fases.
- El usuario respondió con vocabulario de buenas prácticas ("escalable", "moderno", "lo estándar") y lo diste por bueno sin sondear qué quiere de verdad.
- Vas a escribir tickets tras un "suena bien" en vez de un sí explícito sobre la definición concreta.

## Trampas conocidas

- No delegues F3 a un subagente ni la degrades a "¿alguna preferencia?" en texto libre — preguntas concretas, con opciones y recomendación.
- El overview puede mentir por drift: si un hallazgo de F1 contradice al overview, gana el código — y anota el refresco pendiente.
- Un [CONFLICTO] se pregunta PRIMERO y su alternativa se ticketea tras decisión explícita — no re-alcances la petición en silencio hacia "lo que el usuario debería querer".
- Esta skill no lleva hechos de ningún proyecto: los tiers, tablas, puertos y costuras concretos viven en el overview de cada repo. Si te descubres afirmando un hecho de proyecto que no está ni en el overview ni en F1, verifícalo.
