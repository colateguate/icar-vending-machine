# Endurecer el workflow de CI: permisos mínimos y actions ancladas por SHA

## Contexto
`.github/workflows/ci.yml` no declara `permissions:` en ningún nivel (el bloque `on:` empieza en `:3` y `jobs:` en `:28`), así que cada job corre con los permisos por defecto del `GITHUB_TOKEN` del repositorio. Y las seis actions que usa vienen por tag semver, no por SHA: `actions/checkout@v4`, `shivammathur/setup-php@v2` y `ramsey/composer-install@v3`, repetidas en los siete jobs (`:33-34`, `:68-73`, `:82-87`, `:96-101`, `:110-119`, `:134-139`, `:149-154`).

Hoy el riesgo real es cero: `grep -rn "secrets\.\|GITHUB_TOKEN" .github/` no devuelve nada, ningún job escribe de vuelta al repositorio, y el job de auditoría ni siquiera toca el token (`composer audit` pregunta a Packagist por HTTPS anónimo). **Lo que cambió es la frecuencia**: el ticket 14c añadió `on: schedule:` (`:21`), así que el workflow ahora se ejecuta solo, semanalmente, sin que nadie empuje nada ni esté mirando. Un tag movido río arriba deja de necesitar que alguien haga un commit para ejecutarse aquí.

## Criterios de aceptación
- [ ] El workflow declara `permissions:` a nivel de workflow con el mínimo que necesita — hoy `contents: read`, que es lo que pide `actions/checkout`
- [ ] Si algún job necesita más, se eleva **en ese job**, no en el nivel global
- [ ] Las tres actions se referencian por **SHA de commit** con el tag en comentario al lado (`uses: actions/checkout@<sha> # v4.x.y`), que es lo que permite saber qué versión es sin resolver el SHA
- [ ] El pipeline sigue **verde en los siete jobs** tras el cambio, comprobado en una ejecución real y no razonado
- [ ] La anotación de mutantes escapados de Infection sigue apareciendo en la PR
- [ ] `documentation/ci-y-checks.md` explica en una línea por qué las actions llevan SHA, para que nadie las "actualice" a tag en la siguiente PR

## Capa
infra

## Archivos probablemente afectados

> **Las líneas citadas en este ticket son las de `.github/workflows/ci.yml` *después* de que 14c entre.** Hoy, sobre `release/backend`, ese archivo no tiene ni el job `dependencies` ni el bloque `schedule:`: los añade el ticket del que este depende. Si lees esto antes de ese merge, los números no cuadrarán y no es un error de transcripción.
- `.github/workflows/ci.yml` — bloque `permissions:` nuevo (junto a `on:`/`defaults:`, antes de `jobs:` en `:28`) y las líneas `uses:` de los siete jobs
- `documentation/ci-y-checks.md` — la sección de los 7 jobs, español, notas de estudio

## Enfoque sugerido
1. Añadir `permissions: contents: read` a nivel de workflow. **Ya está comprobado que es suficiente para Infection**: su logger de GitHub no llama a la API, escribe un workflow command por stdout — `backend/vendor/infection/infection/src/Logger/GitHubAnnotationsLogger.php:96` devuelve `"::warning file={$filePath},line={$error['line']}::{$message}"`. Los workflow commands por stdout no consumen permisos del token. Aun así el criterio de arriba pide verlo verde, porque una lectura del código fuente no es una ejecución.
2. Anclar por SHA. Resolver el SHA de cada tag (`gh api repos/actions/checkout/git/ref/tags/v4 --jq .object.sha`, o desde la UI del release) y dejar el tag como comentario.
3. Decidir si el mantenimiento se automatiza. Dependabot con `package-ecosystem: github-actions` actualiza SHAs de actions y abre PRs — **y el ticket 14c ya descartó Dependabot por escrito** (`docs/testing-strategy.md`, sección "What the pipeline checks") por no querer PRs automáticas en un repo cuyo log es entregable. Si esa decisión se mantiene, decir aquí que el coste aceptado es actualizar los SHAs a mano; si se revisa, es una decisión nueva y hay que escribirla donde está la anterior, no en otro sitio.

(No prescriptivo — el implementador puede divergir si encuentra mejor camino.)

## ADR asociado
**No** — endurece configuración sin cambiar ninguna decisión registrada. La única frontera con un ADR es el punto 3: si se decide adoptar Dependabot, eso **contradice** lo que 14c dejó escrito en `docs/testing-strategy.md` y hay que corregirlo ahí mismo, no dejar dos párrafos que digan cosas distintas.

## Depende de
14c (introdujo el `schedule` que convierte esto en periódico, y el séptimo job)

## Prioridad sugerida
baja — nada explotable hoy: sin secretos, sin escrituras, token sin uso. Es defensa en profundidad, y su momento es ahora justamente porque el workflow ya no depende de que alguien empuje código para ejecutarse.

## Notas y referencias
- Comprobado antes de escribir el ticket: `grep -rn "secrets\.\|GITHUB_TOKEN" .github/` → sin resultados. O sea que nada del pipeline lee un secreto hoy, y por eso esto es preventivo.
- El job `dependencies` (`:29`) es el único que ya reduce superficie a propósito: se salta `ramsey/composer-install` porque `--locked` no necesita `vendor/`. Menos código de terceros ejecutado, menos que anclar.
- Trampa de los SHA: anclar y olvidar es cambiar un riesgo por otro. Un SHA no recibe parches de seguridad. Por eso el punto 3 no es opcional: o hay un mecanismo de actualización, o hay una decisión escrita de actualizar a mano.

## Origen
Detectado durante review-before-push de las ramas 14c/14d/14e — dos hallazgos Low del `security-reviewer`, ambos sobre `.github/workflows/ci.yml` y ambos amplificados por el `schedule` que ese mismo diff añadía. Van en un ticket y no en dos porque son un archivo, un commit y una sola ejecución de CI para verificarlos.
