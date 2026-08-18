# Añadir el runner CLI que acepta la sintaxis literal del enunciado

## Contexto
Segundo adaptador driving: `bin/console app:machine:run "1, 0.25, 0.25, GET-SODA"` imprime `-> SODA`. Acepta la sintaxis literal de los ejemplos del enunciado a través del MISMO bus de comandos que HTTP — la prueba más barata de que la arquitectura es hexagonal de verdad y no MVC con carpetas. El evaluador puede copiar y pegar los ejemplos del enunciado en su terminal.

## Criterios de aceptación
- [ ] `app:machine:run "<secuencia>"` parsea monedas (0.05|0.10|0.25|1), RETURN-COIN y GET-<SELECTOR>
- [ ] Despacha por el command/query bus compartido — CERO lógica de negocio en el comando de consola
- [ ] Los 3 ejemplos del enunciado producen exactamente: `-> SODA` · `-> 0.10, 0.10` · `-> WATER, 0.25, 0.10`
- [ ] Entrada inválida → mensaje de error legible y exit code != 0 (sin stack trace)
- [ ] `ChallengeScriptTest` en `backend/tests/Acceptance/Cli/` ejecuta las 3 secuencias literales y asserta stdout

## Capa
delivery

## Archivos probablemente afectados
- `backend/src/VendingMachine/Delivery/Cli/RunMachineScriptCommand.php` (a crear)
- `backend/tests/Acceptance/Cli/ChallengeScriptTest.php` (a crear)
- `backend/config/services/` — registro del comando

## Enfoque sugerido
1. Rojo: `ChallengeScriptTest` con las 3 secuencias.
2. Parser como clase pequeña separada (testeable a solas si crece).
3. El comando opera sobre una máquina efímera o la provisionada — decidir y documentar en el propio comando (--fresh flag es aceptable).

(No prescriptivo — el implementador puede divergir si encuentra mejor camino.)

## ADR asociado
No — aplica ADR-0001 (hexagonal): es exactamente el segundo driving adapter que esa decisión predice.

## Depende de
11

## Prioridad sugerida
media — no bloquea nada, pero es alto valor por línea para la evaluación.

## Notas y referencias
- El enunciado deja "cómo se dirigen las acciones" a elección del candidato: este comando ES la respuesta literal a los ejemplos.

## Origen
Desglose de backlog — sesión PM de 2026-08-18.
