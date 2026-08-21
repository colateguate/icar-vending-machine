# Dar a Infection los hilos que `--threads=max` no le da en Windows

## Contexto
`Makefile:102` lanza Infection con `--threads=max`. En esta máquina eso no paraleliza **nada**: la última línea de la ejecución dice `Threads: 1` y el target tarda **3m 53s**. La misma suite con `--threads=4` explícito tarda **1m 11s** y dice `Threads: 4`.

No es que la detección de núcleos falle. `Infection\Resource\Processor\CpuCoresCountProvider::provide()` abre con un `return` codificado:

```php
if (defined('PHP_WINDOWS_VERSION_MAJOR')) {
    return 1;
}
```

Ni siquiera pregunta. Y preguntar funcionaría: `(new CpuCoreCounter())->getCount()` devuelve **24** en esta máquina si se le llama a mano. Así que el flag está puesto, es válido, y no hace nada.

Importa por dos motivos y ninguno es la vanidad del número. El primero es el bucle de trabajo: mutation es la puerta que hay que pasar cada vez que un cambio toca `Domain/` o `Application/`, y cuatro minutos frente a uno decide si se lanza o si se pospone "para luego". El segundo es que la cifra está publicada — `docs/testing-strategy.md` y el README dicen ~4 minutos — y esa cifra describe una ejecución de un hilo por accidente, no por decisión.

En CI no aplica: los runners son Linux y ahí `max` sí resuelve (`.github/workflows/ci-backend.yml:208`).

## Criterios de aceptación
- [ ] `make test-mutation` en Windows usa más de un hilo, comprobado leyendo la línea `Threads: N` de su propia salida
- [ ] La forma elegida **no** degrada CI, donde `max` ya funciona. Fijar un número a pelo para todos es la opción fácil y la peor: en un runner de dos núcleos, `--threads=8` es peor que `max`
- [ ] El tiempo nuevo se mide y se pega, no se estima
- [ ] Toda cifra publicada que cambie se corrige en el mismo commit: `docs/testing-strategy.md:80` y `:126`, y `README.md` si menciona el coste
- [ ] Queda escrito **por qué** el flag no bastaba, en el sitio donde está el flag. Un `--threads=4` sin explicación es un número mágico que el siguiente borrará

## Capa
infra, docs

## Archivos probablemente afectados
- `Makefile` — línea 102, la receta de `test-mutation`
- `.github/workflows/ci-backend.yml` — línea 208, donde `max` sí funciona y no debe tocarse a ciegas
- `docs/testing-strategy.md` — líneas 80 y 126, que publican los ~4 minutos
- `backend/infection.json5` — solo si la configuración de hilos acaba viviendo ahí en vez de en el flag

## Enfoque sugerido
1. Mirar si Infection acepta los hilos desde `infection.json5` además de por flag: si es así, la decisión es dónde vive, no qué número es.
2. Buscar una expresión que valga en las dos plataformas. `nproc` no existe en Windows; `$NUMBER_OF_PROCESSORS` sí. El Makefile ya distingue casos, así que una variable con valor por defecto y sobreescribible (`THREADS ?= …`) puede bastar y deja la puerta abierta a `make test-mutation THREADS=1` cuando alguien quiera depurar.
3. Medir las dos plataformas si se puede; si no, medir esta y decir explícitamente que la otra queda apoyada en que `max` ya funcionaba allí.

(No prescriptivo — el implementador puede divergir si encuentra mejor camino.)

## ADR asociado
No — aplica una decisión ya tomada (ADR-0014, mutation sobre Domain + Application). Esto es cómo se ejecuta, no qué se mide.

## Depende de
—

## Prioridad sugerida
media — no afecta a la corrección de nada, pero triplica el coste de la única puerta que se salta cuando hay prisa, y es justo la puerta que no conviene saltarse.

## Notas y referencias
- La medición completa está en `documentation/ci-y-checks.md`, sección del ticket 24 (fichero gitignorado, apuntes personales).
- Cuidado con dar por bueno `Threads: N` sin mirarlo: el flag se aceptó sin error durante todo el proyecto y no hizo nada. **La salida del propio Infection es la única evidencia que cuenta aquí.**
- El `--threads=max` de CI lleva anclado desde el ticket 03 y nunca se cuestionó porque allí siempre funcionó.

## Origen
Detectado durante fix-bug del ticket 22, al cronometrar `make test-mutation` para verificar que el README no mentía sobre su coste.
