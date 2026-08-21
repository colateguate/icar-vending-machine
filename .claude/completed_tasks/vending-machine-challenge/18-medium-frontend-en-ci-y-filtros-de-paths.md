# Añadir el job de frontend a CI y filtrar los jobs por paths

## Contexto
`.github/workflows/ci.yml` tiene siete jobs y **todos son de backend**: `dependencies`, `code-style`, `static-analysis`, `architecture`, `schema`, `tests`, `mutation`. El panel React no lo mira nadie: ni se lintea, ni se testea, ni se comprueba que compila.

Y hay un segundo problema, que hoy no molesta y molestará en cuanto exista el frontend: los triggers de `ci.yml:3-23` no llevan filtro `paths:`, así que **un cambio que solo toque `frontend/` dispara los siete jobs de backend** — incluido `mutation`, que tarda unos cuatro minutos. Siete jobs que no pueden aprender nada sobre el diff que los provocó.

Detalle que hay que tener presente al implementarlo: el workflow declara `defaults.run.working-directory: backend` a nivel global (`ci.yml:38-40`), así que un job de frontend tiene que sobreescribirlo explícitamente o correrá los `npm` en la carpeta equivocada.

El ticket 14c dejó esto dicho por escrito al aplazarlo: *"El frontend queda fuera: `package.json` no existe hasta el ticket 15. `npm audit` es un ticket posterior, no este."* Éste es ese ticket.

## Criterios de aceptación
- [ ] Job `frontend` nuevo: `npm ci` + `npm run lint` + `npm test` + `npm run build`, con `working-directory: frontend` sobreescribiendo el default global
- [ ] Job `frontend-dependencies` con `npm audit --omit=dev`, equivalente al `composer audit --locked` que ya existe para el backend, y colgado del mismo `schedule` semanal
- [ ] Node fijado a la versión del proyecto (22 LTS) con `actions/setup-node`, **anclada por SHA** como los otros veinte `uses:` del fichero desde el ticket 14f, con el número de versión en comentario
- [ ] Caché de `npm` habilitada (`cache: npm` + `cache-dependency-path`), porque sin ella `npm ci` domina el tiempo del job
- [ ] Filtros `paths:` en `push` y `pull_request`: los jobs de backend no corren ante un cambio que solo toca `frontend/`, ni al revés
- [ ] **Los filtros no dejan huecos**: un cambio en la raíz (`Makefile`, `docker-compose.yml`, `.github/`, `CLAUDE.md`) tiene que seguir disparando lo que corresponda. Un filtro que apaga un gate en silencio es peor que no tener filtro — comprobar con un push real de cada tipo, no razonando sobre el YAML
- [ ] Si el repo tiene checks obligatorios configurados en GitHub, revisar que un job que no corre por filtro no deje la PR bloqueada esperándolo eternamente

## Capa
infra

## Archivos probablemente afectados
- `.github/workflows/ci.yml` — jobs nuevos y bloque de triggers (`:3-23`); el default `working-directory: backend` está en `:38-40`
- `frontend/package.json` — los scripts `lint`, `test` y `build` que el job invoca (creados en el ticket 15)
- `README.md` — la sección que describe el pipeline, si enumera los jobs

## Enfoque sugerido
1. Primero los filtros `paths:`, y comprobarlos con pushes reales de las tres formas (solo backend, solo frontend, raíz).
2. Después el job de frontend, con Node anclado por SHA y caché.
3. Por último `npm audit`, decidiendo si `--audit-level` filtra ruido de transitivas de desarrollo o si `--omit=dev` ya basta.

(No prescriptivo — el implementador puede divergir si encuentra mejor camino.)

## ADR asociado
No — aplica [ADR-0013](../../../docs/adr/0013-enforce-boundaries-with-deptrac-phpstan-max.md) (lo que no se comprueba se degrada) al lado del repo que hasta ahora no lo tenía.

## Depende de
15 (no hay `package.json` que instalar antes)

## Prioridad sugerida
media — sin esto el frontend es la única parte del entregable sin ningún gate, y el reto evalúa cómo se construye. No es alta porque no bloquea escribir el panel.

## Notas y referencias
- Las veinte actions del workflow están ancladas por SHA con la versión en comentario desde el ticket 14f; `actions/setup-node` tiene que entrar igual o rompe la coherencia del fichero.
- `npm audit` sin `--omit=dev` reporta transitivas de la toolchain de build que no llegan al bundle; el criterio es la superficie de producción.
- Comprobar si `paths-ignore` es mejor que `paths` para los jobs de backend: con `paths` hay que enumerar todo lo que sí los dispara, y olvidarse de uno los apaga en silencio.

## Origen
Detectado durante la sesión de preparación del frontend (2026-08-20), al medir qué cobertura de CI existía antes de instalar React.
