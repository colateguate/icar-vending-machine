# Cancelar de verdad la carga inicial, en vez de ignorar su respuesta

## Contexto
`frontend/src/hooks/useMachine.js:24-52` carga la máquina al montar y protege el desmontaje con una bandera `ignore`: la respuesta que llega tarde se descarta. Funciona, pero **la petición sigue viva** y, sobre todo, **no se puede probar**: en React 19 escribir estado en un componente desmontado no avisa ni rompe nada observable, así que un test pasaría igual con bandera y sin ella. Un test que no puede fallar es peor que ninguno.

La causa está una capa más abajo: `frontend/src/services/httpClient.js:40` no acepta un `AbortSignal`, así que no hay nada que cancelar. Detectado al cerrar el ticket 17 y declarado allí como hueco conocido.

## Criterios de aceptación
- [ ] `request()` acepta un `signal` opcional y se lo pasa a `fetch`
- [ ] **Solo `getState` lo expone.** Las cuatro operaciones de escritura no son cancelables, y eso es una decisión, no un olvido: abortar una compra deja al cliente sin saber si la lata salió o no. Cancelar una lectura es seguro; cancelar una escritura es perder el resultado de algo que ya pasó
- [ ] Un aborto **no es un `TransportFailure`**. Nadie ha fallado: hemos sido nosotros. `request()` deja pasar el error de aborto en vez de envolverlo
- [ ] `useMachine` crea un `AbortController` en el efecto y aborta en el cleanup. **La bandera `ignore` desaparece**: `signal.aborted` responde a la misma pregunta y, a diferencia de la bandera, se puede observar desde fuera
- [ ] El test que hoy no se puede escribir, escrito: desmontar el hook y comprobar que el `signal` con el que se llamó a `getState` queda `aborted`. Debe ponerse **rojo** si se quita el cleanup — verificarlo, no suponerlo
- [ ] `httpClient` prueba que reenvía el `signal` a `fetch` y que un aborto no se convierte en `TransportFailure`
- [ ] Nada cambia en la pantalla: no hay caso de uso nuevo, solo se cierra un hueco

## Capa
frontend

## Archivos probablemente afectados
- `frontend/src/services/httpClient.js:40` — `request()` acepta `signal`
- `frontend/src/services/machineApi.js:17` — solo `getState` lo propaga
- `frontend/src/hooks/useMachine.js:24` — `AbortController` en lugar de la bandera
- Los tres tests correspondientes

## Enfoque sugerido
1. De abajo arriba: `httpClient` primero, que es donde está la carencia real.
2. En `useMachine`, el `catch` y el `finally` consultan `signal.aborted` en vez de una bandera propia. Un solo mecanismo, y observable.
3. Comprobar con una mutación deliberada que el test nuevo muerde: quitar el cleanup y verlo fallar.

(No prescriptivo — el implementador puede divergir si encuentra mejor camino.)

## ADR asociado
No — aplica ADR-0016. La decisión de que las escrituras no se cancelen se argumenta en el docblock de `machineApi`, que es donde la va a leer quien la use.

## Depende de
17

## Prioridad sugerida
media — no cambia comportamiento visible, pero convierte una línea sin vigilancia en una línea probada, y la razón por la que las escrituras quedan fuera es material de entrevista.

## Notas y referencias
- Lo interesante de contar no es `AbortController`, que es API estándar: es **por qué solo la lectura lo tiene**. Un `POST /purchases` abortado en vuelo puede haber dispensado ya.

## Origen
Detectado durante implement-feature del ticket 17 — declarado en su informe como hueco conocido.
