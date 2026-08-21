# Cerrar la contradicción entre el suelo de Node declarado y el que se usa

## Contexto
Hoy coexisten tres versiones de Node y ninguna coincide con otra. `frontend/package.json:6-8` declara `"node": ">=22.22.2"`. La máquina que desarrolla esto tiene **22.14.0**. CI resuelve la línea `'22'` (`.github/workflows/ci-frontend.yml:74`), que sí cumple. La suite pasa verde en las tres.

El ticket 19 dejó esto deliberadamente en un aviso: `frontend/scripts/check-node-version.js` avisa por stderr y **nunca sale con código distinto de cero**, porque bloquear habría dejado al usuario sin poder ejecutar nada. Fue la decisión correcta entonces y se aplazó dos veces la pregunta de fondo.

Medido ahora, y cambia el planteamiento que se venía arrastrando:

```
$ npm ci --engine-strict --dry-run
npm ERR! code EBADENGINE
npm ERR! notsup Not compatible with your version of node/npm: vending-machine-frontend@0.1.0
npm ERR! notsup Required: {"node":">=22.22.2"}
npm ERR! notsup Actual:   {"npm":"9.6.3","node":"v22.14.0"}
```

Falla por el **paquete raíz**, no por una dependencia. La medición anterior —"ningún paquete del árbol rechaza el Node que CI resuelve"— era cierta y respondía a otra pregunta. Así que `engine-strict=true` no es un endurecimiento gratuito: rompería `npm ci` en la máquina de desarrollo tal cual está hoy.

Lo que importa no es el flag, es que un suelo declarado que nadie cumple y nada aplica no es un suelo: es una nota. Y una nota que contradice al repo es lo que se lee en voz alta en la entrevista.

## Criterios de aceptación
- [ ] Queda escrita **una** decisión, no las tres a la vez. Las opciones defendibles son: (a) subir el Node local a ≥22.22.2 y añadir `frontend/.npmrc` con `engine-strict=true`; (b) bajar lo declarado a la versión que de verdad se usa, con la evidencia de que la suite pasa en ella; (c) mantener el aviso y dejar dicho por qué el suelo declarado no se aplica
- [ ] Elija lo que elija, `npm ci` y `make front-install` funcionan en la máquina que desarrolla esto — comprobado ejecutándolos, no razonado
- [ ] Si entra `engine-strict`, se demuestra **mutando**: `npm ci` sobre un Node por debajo del suelo debe negarse, y la salida se pega
- [ ] Si se baja el suelo, se comprueba antes qué paquete lo imponía. Hoy es jsdom; si el suelo baja por debajo de lo que jsdom pide, hay que decir qué se pierde
- [ ] `frontend/scripts/check-node-version.js` y su comentario siguen siendo ciertos tras la decisión. Si `engine-strict` entra, el script pasa a ser redundante o pasa a explicar otra cosa: decidir cuál
- [ ] `CLAUDE.md:131-134` afirma que los `front-*` avisan en vez de parar. Si eso cambia, esa frase cambia con ello

## Capa
frontend, infra, docs

## Archivos probablemente afectados
- `frontend/package.json` — líneas 6-8, el `engines` declarado
- `frontend/.npmrc` — **(a crear)** solo si se elige la opción (a)
- `frontend/scripts/check-node-version.js` — las 44 líneas del guard, y su razón de existir
- `Makefile` — `_ensure-node` y `_ensure-frontend` (líneas 136 en adelante)
- `CLAUDE.md` — línea 131, el párrafo que promete que avisan en vez de parar
- `.github/workflows/ci-frontend.yml` — línea 74 y su comentario, que explica por qué CI fija la línea y no el parche

## Enfoque sugerido
1. Averiguar qué paquete impone 22.22.2 hoy. `npm ls jsdom` y su `engines`. Sin ese dato ninguna de las tres opciones se puede defender.
2. Probar la suite completa en el Node local **declarándolo**: si pasa en 22.14.0, la opción (b) tiene evidencia; si no, la (a) es la única honesta.
3. Decidir, escribir la decisión donde alguien la buscaría, y hacer que el resto de los ficheros digan lo mismo.

(No prescriptivo — el implementador puede divergir si encuentra mejor camino.)

## ADR asociado
No — es una decisión de tooling local, y cabe en el mensaje de commit. Si acabara cambiando la política de versiones de todo el repo (backend incluido), entonces sí merecería ADR.

## Depende de
—

## Prioridad sugerida
media — no rompe nada hoy y por eso lleva dos aplazamientos, pero es una contradicción visible en un fichero que el evaluador abre, y el coste de cerrarla es una tarde corta.

## Notas y referencias
- Precedente de la decisión de avisar en vez de parar: ticket 19, commit `3036f79 chore(make): drive the panel from make, and let qa cover both halves`.
- Ojo con la trampa que ya costó una vez: `"type": "module"` alcanza a los `.js` que Node ejecuta directamente, y el guard se escribió como ESM por eso. Cualquier fichero nuevo aquí hereda esa regla.
- La medición de este contexto (`EBADENGINE` sobre el paquete raíz) es la que hay que rehacer si pasa tiempo: el Node local puede haber cambiado.

## Origen
Detectado durante implement-feature del ticket 19, aplazado explícitamente dos veces, y medido durante el cierre del sprint de frontend.
