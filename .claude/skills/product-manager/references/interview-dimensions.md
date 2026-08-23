# Dimensiones de entrevista (F2)

Cruza la petición con cada dimensión. Cada cruce produce candidatos a hueco: **[CRÍTICO]** (decisión de producto abierta), **[CONFLICTO]** (choca con algo existente — cita fuente) o **[MENOR]** (default razonable, se lista como suposición). No todas las dimensiones aplican a toda petición — descarta rápido las que no, pero descártalas conscientemente.

## 1. Actores y permisos
- ¿Quién usa esto (tipo de usuario, rol, visitante anónimo)? ¿Quién NO debe poder?
- ¿Cómo se gatea en este proyecto (sistema de permisos/roles dinámico, checks estructurales)? ¿Permiso nuevo o existente?
- ¿Hay acciones de administración/moderación asociadas, y de quién son?

## 2. Alcance y no-alcance
- ¿MVP o completo? ¿Qué queda explícitamente FUERA de esta iteración?
- ¿Detrás de flag / activación gradual?
- ¿La petición son en realidad varias features independientes? (si sí → proponer partir)

## 3. Datos
- Entidades nuevas o modificadas; ¿quién es dueño del esquema y quién migra en este ecosistema?
- Unicidad y duplicados (¿1 por usuario? ¿por recurso?); ¿qué pasa con los datos existentes (backfill/legacy)?
- Borrado: ¿soft o hard? ¿cascada? ¿qué NO debe borrarse nunca (histórico, auditoría)?
- Retención y tamaño esperado (¿crece sin límite?).

## 4. Reglas de negocio
- Estados y transiciones (¿quién puede pasar de A a B? ¿hay vuelta atrás?).
- Límites y cuotas (por usuario, por recurso, por periodo).
- Concurrencia: ¿qué pasa si dos lo hacen a la vez sobre lo mismo?
- Temporalidad: zonas horarias, cosas del pasado vs futuro, ventanas de validez.

## 5. Monetización / gating de plan
- ¿A qué plan/tier pertenece la feature? ¿Es palanca de upgrade?
- ¿Límites distintos por plan? ¿Qué ve quien no la tiene (oculto, teaser, CTA)?

## 6. UI/UX
- ¿Dónde vive en la navegación? ¿Página nueva o extensión de una existente?
- Estados: cargando / vacío / error / sin permiso / filtro sin resultados.
- ¿Responsive/móvil relevante? ¿Interacciones destructivas con confirmación?

## 7. i18n y SEO
- Idiomas soportados por el proyecto: toda cadena visible nueva llega a todos.
- ¿Superficie pública indexable? URLs/slugs, metadatos, contenido duplicado.

## 8. Seguridad y privacidad
- Autorización vertical (rol correcto) y horizontal (SOLO sus recursos — cross-tenant).
- Validación de entrada; contenido generado por usuarios (XSS, moderación).
- ¿PII o datos sensibles? Cifrado, masking en logs/exports, GDPR (consentimiento, borrado).
- ¿Endpoint caro? Rate limit y topes de filas. ¿Operación sensible? Auditoría.

## 9. Integraciones y costuras (cross-repo / cross-servicio)
- Consulta la sección de costuras del overview ANTES de decidir que "solo toca este repo".
- ¿Colas/eventos/jobs compartidos implicados? ¿Quién produce y quién consume?
- Sincronizaciones: ¿qué dirección? ¿quién gana si divergen? ¿qué pasa con borrados/vacíos?
- ¿Servicios externos (pagos, email, mapas, APIs)? ¿Términos de uso/review pendientes?

## 10. Emails y notificaciones
- ¿Quién recibe qué, cuándo, en qué idioma? ¿Se puede desactivar?
- Mecanismo del proyecto (outbox/cola/directo) — no inventes uno nuevo.

## 11. Analítica y telemetría
- ¿Hay que medir uso/conversión? ¿Dashboards o informes existentes afectados?

## 12. Operación y despliegue
- ¿Migraciones con orden de despliegue delicado (prod)? ¿Rollback?
- Carga esperada: N+1, índices, payloads grandes, cachés.
- ¿Necesita seed/datos de demo? ¿Afecta a los tests E2E existentes?
