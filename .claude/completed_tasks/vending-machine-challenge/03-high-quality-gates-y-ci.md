# Configurar quality gates (PHPUnit, PHPStan, Deptrac, cs-fixer, Infection) y CI

## Contexto
Los gates deben aterrizar ANTES que el código de negocio: así todo commit posterior del log evaluado nació verde bajo PHPStan max y Deptrac. Un gate añadido a posteriori obliga a baselines y excusas; añadido antes, la arquitectura queda vigilada desde la línea 1.

## Criterios de aceptación
- [ ] `backend/phpunit.xml.dist` con 4 suites: unit, application, integration, acceptance (dirs de `backend/tests/`)
- [ ] `backend/phpstan.neon.dist` a nivel `max`, sin baseline
- [ ] `backend/deptrac.yaml` con las capas y ruleset EXACTOS de `CLAUDE.md` § dependency rule (Domain→SharedDomain, Application→Domain, Delivery→App+Domain+Symfony, Infrastructure→todo, SharedDomain→∅)
- [ ] `backend/.php-cs-fixer.dist.php` (PSR-12 + declare strict_types obligatorio)
- [ ] `backend/infection.json5` acotado a `src/VendingMachine/Domain` y `src/VendingMachine/Application` con `minMsi: 85`
- [ ] `Makefile` targets reales: `qa` encadena phpunit + phpstan + deptrac + cs-fixer --dry-run
- [ ] `.github/workflows/ci.yml` con jobs cs | static | architecture | tests | mutation, verde en push
- [ ] `make qa` corre en local en verde (sin tests aún: suites vacías permitidas)

## Capa
infra

## Archivos probablemente afectados
- `backend/phpunit.xml.dist`, `backend/phpstan.neon.dist`, `backend/deptrac.yaml`, `backend/infection.json5`, `backend/.php-cs-fixer.dist.php` (a crear)
- `.github/workflows/ci.yml` (a crear)
- `Makefile` — sustituir stubs del ticket 01

## Enfoque sugerido
1. `composer require --dev` de las 5 herramientas.
2. Deptrac primero con el ruleset completo aunque las capas estén vacías: fallará en cuanto alguien viole, sin config extra.
3. CI: cache de composer, PHP 8.2+, jobs paralelos.

(No prescriptivo — el implementador puede divergir si encuentra mejor camino.)

## ADR asociado
Sí — crear `docs/adr/0013-enforce-boundaries-with-deptrac-phpstan-max.md` y `docs/adr/0014-four-test-levels-mutation-gated-domain.md`.

## Depende de
02

## Prioridad sugerida
alta — condición para que el resto del log sea verde desde el origen.

## Notas y referencias
- Infection solo Domain+Application: mutar glue de infraestructura es ruido (defensa en el ADR-0014).

## Origen
Desglose de backlog — sesión PM de 2026-08-18.
