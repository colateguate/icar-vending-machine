# Dar al frontend sus targets en el Makefile

## Contexto
El `Makefile` declara catorce targets en su `.PHONY` (`:5`) y **ninguno toca el frontend**: `help up down reset test test-unit test-application test-integration test-acceptance qa schema-check cs-fix test-mutation _ensure-backend`. Todos los de test y calidad llevan la guarda `_ensure-backend` y corren dentro de `backend/`.

Eso deja el panel como la única parte del repo que se maneja escribiendo `cd frontend && npm ...` a mano, cuando el resto del proyecto se conduce entero desde `make`. Para quien evalúa —que llega al repo sin saber nada— la diferencia es notable: `make help` es el índice de lo que se puede hacer, y hoy mentiría por omisión.

`make up` (`:23`) es `docker compose up --build` y heredará el frontend automáticamente cuando el ticket 13b añada el servicio, así que ése no hay que tocarlo. Ojo con una afirmación que ya está publicada y todavía no es cierta: `CLAUDE.md:87` describe `make up` como "docker compose up (backend + frontend)".

## Criterios de aceptación
- [ ] Targets nuevos: `front-install`, `front-dev`, `front-test`, `front-lint`, `front-build`, todos con `working-directory` en `frontend/`
- [ ] Guarda `_ensure-frontend` análoga a `_ensure-backend`: un mensaje claro si falta `node_modules/` o si la versión de Node no llega al mínimo de Vite (20.19+ / 22.12+), en vez de un error de npm sin contexto
- [ ] Todos los targets nuevos aparecen en `make help` con su descripción, como los demás
- [ ] **Decidir y documentar si `make qa` incorpora el lint y los tests de frontend.** Hay tensión real: `qa` es hoy el gate único antes de commitear y debería cubrir todo lo que CI comprueba, pero arrancar Node añade segundos a un comando que se ejecuta constantemente durante el trabajo de backend. Decidir con criterio, escribirlo en el `help`, y no dejarlo implícito
- [ ] `make test` sigue significando lo que significa hoy (las cuatro suites de PHPUnit) o pasa a significar todo — pero no queda a medias
- [ ] Comprobación real: los targets nuevos funcionan desde un clon limpio sin `node_modules/`

## Capa
infra

## Archivos probablemente afectados
- `Makefile` — `.PHONY` (`:5`), el bloque `help` (`:8-19`) y los targets nuevos
- `CLAUDE.md` — la sección "Commands", que enumera los targets y hoy solo lista los de backend
- `README.md` — si describe los comandos de arranque

## Enfoque sugerido
1. Copiar la forma de `_ensure-backend` para la guarda de frontend; la coherencia entre los dos lados vale más que la brevedad.
2. Añadir los targets y sus líneas de `help`.
3. Decidir lo de `qa` al final, con los tiempos ya medidos en vez de estimados.

(No prescriptivo — el implementador puede divergir si encuentra mejor camino.)

## ADR asociado
No — decisión operativa. Si `make qa` acaba excluyendo el frontend, merece una línea en `docs/assumptions.md` explicando por qué, no un ADR.

## Depende de
15 (no hay nada que instalar ni testear antes)

## Prioridad sugerida
baja — es ergonomía, no corrección. Nada está roto sin esto; simplemente el repo se conduce de dos maneras distintas según la mitad que toques.

## Notas y referencias
- `CLAUDE.md:87` ya promete que `make up` levanta backend + frontend. Lo hará cuando el ticket 13b añada el servicio al compose; si este ticket se hace antes, la frase sigue siendo una promesa pendiente y conviene no darla por cumplida.
- El ticket 18 define qué comprueba CI del frontend; lo natural es que `make qa` y el pipeline digan lo mismo, sea lo que sea que se decida.

## Origen
Detectado durante la sesión de preparación del frontend (2026-08-20), al inventariar qué infraestructura existía antes de instalar React.
