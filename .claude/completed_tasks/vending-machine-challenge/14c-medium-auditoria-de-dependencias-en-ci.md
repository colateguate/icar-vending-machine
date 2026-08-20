# Auditar dependencias en CI con `composer audit`

## Contexto
El pipeline tiene **seis jobs bloqueantes** (`.github/workflows/ci.yml:12,26,40,54,78,92`) y ninguno mira si alguna dependencia tiene un advisory de seguridad publicado. `make qa` (`Makefile:59`) tampoco.

Lo que convierte esto en un hueco de proceso y no solo en un job que falta: la rúbrica del revisor de seguridad (`.claude/agents/security-reviewer.md:24`) dice que el orquestador **le habrá pegado la salida de `composer audit` en el prompt**, y que sin ella no debe afirmar nada sobre advisories. O sea que hoy la única comprobación de la cadena de suministro depende de que una persona se acuerde de ejecutar un comando a mano. Ocurrió exactamente eso en la review del ticket 14b: el agente tuvo que pedirlo en su informe porque no lo tenía.

Y hay una segunda mitad que un job de `push` no cubre: **un advisory aparece sin que nadie haga un commit**. Un pipeline que solo audita cuando alguien empuja código informa del riesgo en el momento en que menos ha cambiado.

## Criterios de aceptación
- [ ] Un job nuevo en `.github/workflows/ci.yml` ejecuta `composer audit --locked` sobre `backend/`
- [ ] El job usa `--locked`: audita lo que está en `composer.lock`, que es lo que se despliega, y no lo que resuelva el instalador ese día
- [ ] Decidido y justificado en el propio YAML si audita también `require-dev` o solo producción (`--no-dev`). Dato: la imagen instala con `--no-dev` (`backend/Dockerfile:44`), así que una dev-dep vulnerable no llega al runtime — pero sí se ejecuta en CI
- [ ] El comportamiento ante **paquetes abandonados** está fijado explícitamente en el comando, no heredado. Composer local es **2.5.1**, `setup-php` instala el último en CI, y las versiones nuevas de `composer audit` tratan los abandonados de otra forma: sin fijarlo, el job puede ponerse rojo por un motivo que no es de seguridad. Hoy no hay ninguno abandonado en `composer.lock` (verificado), así que el arreglo es preventivo y barato ahora
- [ ] El pipeline se dispara además **por calendario** (`on: schedule:`), no solo en push/PR, para que un advisory publicado sin commits nuestros aparezca igual
- [ ] Documentación actualizada: `docs/testing-strategy.md:108` dice "Six jobs, all blocking" con su tabla, y `documentation/ci-y-checks.md:5` dice "Los 6 jobs". Ambos pasan a siete
- [ ] `make qa` sigue funcionando **sin red** (ver nota abajo)
- [ ] `make qa` en verde

## Capa
infra, docs

## Archivos probablemente afectados
- `.github/workflows/ci.yml` — job nuevo, siguiendo la plantilla uniforme de los seis existentes (`actions/checkout@v4` → `shivammathur/setup-php@v2` con `php-version: '8.2'` y `coverage: none` → `ramsey/composer-install@v3` con `working-directory: backend` → `run:`). Ojo: el job de auditoría **no necesita instalar dependencias**, le basta el `composer.lock`, así que puede saltarse `composer-install` y ser el job más rápido del pipeline
- `.github/workflows/ci.yml` — bloque `on:` (`:4-6`), para añadir `schedule:`
- `Makefile:59` (`qa`) y `Makefile:7` (`help`) — solo si se decide añadir un target
- `docs/testing-strategy.md:106-118` — el conteo y la tabla de jobs
- `documentation/ci-y-checks.md:5` — la sección "Los 6 jobs" (español, documentación de estudio)
- `backend/composer.lock` — la entrada del audit, no se modifica

## Enfoque sugerido
1. Job nuevo, sin instalar vendor: `composer audit --locked` desde `backend/`. Es el job más barato del pipeline y el único que puede correr en segundos.
2. Decidir `--no-dev` o no. Argumento a favor de auditar todo: las dev-deps se ejecutan en CI con el token del repositorio, así que "no llega al runtime" no es lo mismo que "no importa". Argumento en contra: un advisory en PHPUnit bloquea entregas de código que no lo usa. Lo que **no** vale es heredar el default sin decir cuál se eligió.
3. Añadir `on: schedule:` con una cadencia diaria o semanal. Esta es la mitad que un job de push no puede dar, y probablemente la más valiosa del ticket.
4. Sobre `make qa`: hoy es **ejecutable sin red**, y meter `composer audit` dentro lo rompería (consulta la API de advisories de Packagist). Sugerencia: dejar `qa` como está y añadir un `make audit` separado, igual que `test-mutation` vive fuera de `qa` por costar cuatro minutos. Que un gate esté fuera de `qa` por una razón dicha en voz alta ya es precedente en este repo.
5. Alternativa que conviene evaluar y descartar por escrito: **Dependabot** (`.github/dependabot.yml`, hoy inexistente — `.github/` solo contiene `workflows/`). No es lo mismo: Dependabot abre PRs de actualización, `composer audit` falla el build. Son complementarios y la pregunta real es si este reto quiere recibir PRs automáticas.
6. El frontend queda fuera: `package.json` no existe hasta el ticket 15. `npm audit` es un ticket posterior, no este.

(No prescriptivo — el implementador puede divergir si encuentra mejor camino.)

## ADR asociado
**No** — aplica una decisión ya tomada, la de preferir gates a acuerdos, registrada en [ADR-0013](../../../docs/adr/0013-enforce-boundaries-with-deptrac-phpstan-max.md) y [ADR-0014](../../../docs/adr/0014-four-test-levels-mutation-gated-domain.md).

Con un matiz: si la implementación elige la opción **no obvia** —que el job no bloquee, o que solo corra por calendario y no en cada PR— eso sí es una decisión con alternativas reales y una consecuencia negativa que merece escribirse: *un advisory publicado río arriba pone en rojo un build que no contiene ni un cambio tuyo*. En ese caso, un párrafo añadido a ADR-0013 antes que un decimosexto registro.

## Depende de
—

## Prioridad sugerida
media — no bloquea ningún ticket ni ninguna entrega, y cierra el único eje de la rúbrica de seguridad que hoy depende de que alguien se acuerde. Es además el job más barato de añadir de todo el pipeline.

## Notas y referencias
- Patrón canónico a imitar: el job `schema:` (`.github/workflows/ci.yml:54`), que fue la última incorporación al pipeline y es el ejemplo de un job que cierra un hueco concreto y se documenta a la vez en los dos sitios.
- `composer audit --locked` devuelve **exit 0** hoy con "No security vulnerability advisories found" — verificado en la rama `feat/openapi-contract`, tanto con dev-deps como con `--no-dev`. O sea que el job entra en verde y el primer rojo será una señal real, no deuda heredada.
- Riesgo conocido: es el primer job del pipeline cuyo resultado **depende de un servicio externo** (la API de advisories de Packagist). Un fallo de red se ve igual que un advisory si no se distingue, y eso erosiona la confianza en el rojo. Merece pensarse cómo se separa "no pude preguntar" de "la respuesta es mala".
- La documentación de estudio (`documentation/ci-y-checks.md`) se escribe en español y explica cómo leer un check en rojo; el job nuevo necesita su párrafo ahí, no solo el conteo actualizado.

## Origen
Detectado durante review-before-push del ticket 14b — el `security-reviewer` cerró su informe pidiendo la salida de `composer audit` porque su rúbrica se la exige al orquestador y nada del pipeline se la da.
