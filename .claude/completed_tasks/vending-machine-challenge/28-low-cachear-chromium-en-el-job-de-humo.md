# Dejar de descargar Chromium en cada ejecución del job de humo

## Contexto
El job `smoke` de `.github/workflows/ci-frontend.yml:91` tardó **1m 37s** en su primera ejecución real (15:02:41 → 15:04:18), frente a los 22s del job de lint, tests y build que corre en paralelo. Sus pasos son cuatro: `npm ci`, `npx playwright install --with-deps chromium` (línea 108), `docker compose up -d --build --wait` (línea 118) y los cinco specs (línea 122).

De esos, el que domina es la descarga del navegador: son **114,5 MB** medidos al instalarlo en local, y se bajan enteros en cada ejecución. La construcción de las dos imágenes en frío y sin caché de capas mide 33s, y la suite en sí corre en menos de dos segundos.

`actions/setup-node` ya cachea `~/.npm` en este mismo job (línea 99), así que la infraestructura de caché está montada y la costumbre existe. Lo que falta es hacer lo propio con `~/.cache/ms-playwright`, que es donde Playwright deja el navegador.

No es urgente: minuto y medio de CI no bloquea a nadie y el job corre en paralelo con los otros dos. Es un ticket de higiene, y entra en el backlog para que la cifra publicada en `docs/adr/0017` no envejezca sin que nadie lo note.

## Criterios de aceptación
- [ ] `~/.cache/ms-playwright` se cachea entre ejecuciones, con una clave que **incluya la versión de Playwright** — cachear por rama o por SO serviría un navegador viejo cuando la dependencia suba, que es peor que no cachear
- [ ] En un acierto de caché, el paso de instalación no vuelve a descargar los 114 MB. Comprobado leyendo el log del job, no supuesto
- [ ] Se mide el tiempo del job **antes y después**, y las dos cifras se pegan
- [ ] `--with-deps` sigue haciendo su trabajo: instala las librerías del sistema que Chromium enlaza, y **eso no está en la caché de Playwright** sino en la del sistema. Si el paso se salta entero cuando hay acierto, hay que comprobar que el navegador sigue arrancando en un runner limpio
- [ ] `docs/adr/0017-browser-smoke-for-what-jsdom-cannot-see.md` menciona el coste; si cambia, cambia ahí con la medición nueva

## Capa
infra, docs

## Archivos probablemente afectados
- `.github/workflows/ci-frontend.yml` — el job `smoke` (líneas 91-136), en particular el paso de instalación de la línea 108
- `frontend/package-lock.json` — de donde sale la versión de Playwright que debe entrar en la clave de caché
- `docs/adr/0017-browser-smoke-for-what-jsdom-cannot-see.md` — la sección de consecuencias positivas, que publica el coste

## Enfoque sugerido
1. `actions/cache` sobre `~/.cache/ms-playwright`, con la clave derivada de la versión que fija el lock.
2. Dejar el `install` corriendo igualmente: con la caché caliente termina en segundos y sigue instalando las dependencias de sistema. Saltárselo con un `if` es la optimización que rompe el runner limpio.
3. Medir con caché fría y con caché caliente, que son dos números distintos y los dos interesan.

(No prescriptivo — el implementador puede divergir si encuentra mejor camino.)

## ADR asociado
No — aplica el ADR-0017, que ya decidió que el humo corre en CI. Esto es cuánto cuesta, no si se hace.

## Depende de
—

## Prioridad sugerida
baja — es minuto y medio de CI en un job que corre en paralelo. Lo primero que se cae si aprieta el tiempo, y no pasa nada si se cae.

## Notas y referencias
- Precedente de anclado y cacheado en este mismo repo: `ramsey/composer-install` en `.github/workflows/ci-backend.yml:120`, que cachea `vendor/` por el hash de `composer.lock`. Misma idea, misma trampa a evitar: la clave tiene que depender de lo que fija la versión.
- Cuidado con `cache-dependency-path` y las rutas: ya mordió una vez en este workflow. Se resuelve desde la raíz del workspace, **no** desde el `working-directory` por defecto, que solo alcanza a los pasos `run:`.
- La medición original (1m 37s, 114,5 MB) es del primer estreno del job; conviene rehacerla antes de tocar nada, porque los runners varían.

## Origen
Detectado durante implement-feature del ticket 24, al leer los tiempos del primer estreno del job de humo en CI.
