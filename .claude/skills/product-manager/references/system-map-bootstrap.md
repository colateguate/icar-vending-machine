# Bootstrap del overview de proyecto (F0, rama "no existe")

Genera el documento "qué existe hoy" de un proyecto (o ecosistema de repos) que no lo tiene. Se ofrece al usuario ANTES de hacerlo: es una pasada de exploración con coste real. Con su OK:

## 1. Descubrir el terreno
- ¿Un repo o varios hermanos? (pregunta al usuario qué repos forman el sistema si no es evidente por CLAUDE.md/README/configs).
- Inventaría docs existentes: CLAUDE.md (raíz y por workspace), README, `docs/`, specs, DEVLOG, strategy docs. El overview NO duplica lo que ya está bien escrito: lo indexa.

## 2. Explorar (agentes Explore en paralelo, read-only)
Un agente por área, con rutas absolutas y preguntas concretas:
- **Módulos**: rutas/endpoints del backend, páginas/vistas del frontend, jobs/crons — nombres, no código.
- **Datos**: motor(es), quién define el esquema (migraciones ¿dónde?), tablas/colecciones por dominio, tablas compartidas entre apps y quién lee/escribe cada una.
- **Auth**: mecanismos, realms/audiencias de tokens, tipos de usuario y cómo se autoriza (roles estáticos vs permisos dinámicos).
- **Costuras**: colas/eventos/jobs compartidos, sincronizaciones entre apps, handoffs (quién pide cambios de esquema a quién), servicios externos (email, pagos, storage, mapas).
- **Runtime**: puertos por proceso en dev, colisiones conocidas, entornos (local/staging/prod) y cómo se selecciona cada uno, configs de deploy.

**Regla de verificación:** cada afirmación del overview debe citar su fuente (`fichero`, migración, config). Lo no verificado se marca "por confirmar", no se afirma.

## 3. Redactar con esta plantilla

```markdown
# <Producto> — Project Overview (qué existe hoy)

> **Capa "qué existe":** catálogo de features, datos, stack y costuras. [Si hay strategy doc: "El porqué vive en <link>".]
> **Last verified: <YYYY-MM-DD>** (<repo> `<sha corto>` [· <repo2> `<sha>`…]). Protocolo de actualización en §10.

## 0. Índice          ← una pantalla; es lo único que se lee SIEMPRE
## 1. Resumen del sistema        ← repos/apps, stack, qué posee cada uno (tabla)
## 2. Catálogo de módulos        ← por app: rutas backend, páginas frontend, jobs
## 3. Propiedad de datos         ← tabla → dueño del esquema → quién escribe/lee → notas
## 4. Auth y autorización        ← realms/tokens, tipos de usuario, cómo se gatea
## 5. Puertos y entornos         ← matriz dev + colisiones + selección de entorno
## 6. Costuras (flujos cross-app) ← una subsección por flujo: tablas, dirección, disparador, gotchas
## 7. Deploy                     ← config por app, migraciones por entorno
## 8. Huecos conocidos           ← lo que solo existe en código o en la cabeza de alguien
## 9. Índice de documentación    ← dónde vive cada doc profundo (specs, runbooks, CLAUDE.md)
## 10. Protocolo de frescura     ← ver abajo
```

## 4. Protocolo de frescura (sección 10 del doc)
- Sello `Last verified: <fecha> (<shas>)` en cabecera; quien edite cualquier sección lo actualiza.
- Comandos de detección de drift, uno por repo, sobre las rutas sensibles del proyecto (migraciones, rutas/páginas, módulos de costura):
  `git -C <repo> log --oneline --since="<fecha del sello>" -- <rutas>`
- Umbral: commits en esas rutas → avisar de drift y ofrecer refresco de las secciones afectadas; nunca bloquear el trabajo.
- Refresco = re-verificar SOLO las secciones tocadas; costura nueva → añadir a §6 y valorar §8.

## 5. Guardar y enlazar
- Ruta canónica: `.claude/product_strategy/<producto>-project-overview.md` (créala si el proyecto no la tiene; si el proyecto guarda docs de producto en otro sitio establecido, respétalo).
- En ecosistemas multi-repo: UN overview canónico en el repo hub; en los demás repos, una línea-puntero en su CLAUDE.md.
- Añade la línea-puntero también al CLAUDE.md del hub para que las sesiones futuras lo encuentren sin buscar.
