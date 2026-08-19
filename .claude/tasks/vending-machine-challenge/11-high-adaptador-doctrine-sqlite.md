# Añadir el adaptador Doctrine/SQLite con mapping XML y contract test compartido

## Contexto
El segundo adaptador del puerto de persistencia: la demostración tangible de que la arquitectura paga. Mapping en XML para que el dominio siga sin una sola anotación de infraestructura; tipos DBAL propios para que los VOs sobrevivan el round-trip sin setters ni constructores públicos vacíos. El contract test abstracto que corre contra AMBOS adaptadores responde a "¿cómo sabes que tu doble InMemory no miente?" — posiblemente el test de mayor señal del proyecto.

## Criterios de aceptación
- [ ] Mapping XML en `backend/config/doctrine/` (`type: xml`, `is_bundle: false`); `grep -r "Doctrine" backend/src/VendingMachine/Domain` → 0 hits
- [ ] Tipos DBAL: MoneyType (int), CoinCollectionType (JSON), ProductSelectorType, MachineIdType
- [ ] `DoctrineVendingMachineRepository` implementa el puerto; migración Doctrine inicial (no schema:create)
- [ ] Optimistic locking: columna `<version/>` en XML, `OptimisticLockException` → `ConcurrentMachineModification` (infra) → 409
- [ ] `DoctrineVendingMachineRepositoryTest` **extiende el contrato ya existente** `tests/Support/Contract/VendingMachineRepositoryContract.php` (creado en el ticket 06) y solo implementa `repository()`. NO duplicar los tests del contrato ni relajarlos
- [ ] Ojo con el *identity map* de Doctrine: dos `find()` en la misma unidad de trabajo devuelven **el mismo objeto**, al revés que el doble InMemory que copia al leer. Por eso los tests de aislamiento de copias viven en el test del adaptador InMemory y NO en el contrato — no los subas
- [ ] `ConcurrentPurchaseTest`: dos EntityManagers, un ítem de stock, exactamente una compra gana
- [ ] `bin/console app:machine:provision` siembra Water/Juice/Soda ×10 + reserva de cambio
- [ ] Acceptance sigue verde ahora contra SQLite real

## Capa
infrastructure

## Archivos probablemente afectados
- `backend/config/doctrine/Machine.VendingMachine.orm.xml`, `Catalog.Product.orm.xml` (a crear)
- `backend/src/VendingMachine/Infrastructure/Persistence/Doctrine/DoctrineVendingMachineRepository.php`, `Type/*.php` (a crear)
- `backend/src/VendingMachine/Infrastructure/Symfony/Console/ProvisionMachineCommand.php` (a crear)
- `backend/migrations/Version*.php` (a crear)
- `backend/config/packages/doctrine.yaml`, `backend/config/services/infrastructure.yaml` — binding puerto→adaptador
- `backend/tests/Integration/VendingMachine/Infrastructure/Persistence/**` (a crear)

## Enfoque sugerido
1. Contract test abstracto PRIMERO, corriendo ya contra InMemory (rojo estructural para Doctrine).
2. Tipos DBAL con test de round-trip propio.
3. Mapping XML + repo + migración hasta que el contrato pase contra SQLite.
4. Locking al final con su test de concurrencia.

(No prescriptivo — el implementador puede divergir si encuentra mejor camino.)

## ADR asociado
Sí — crear `docs/adr/0008-doctrine-sqlite-xml-mapping.md`, `docs/adr/0009-two-adapters-shared-contract-test.md` y `docs/adr/0011-optimistic-locking-for-concurrent-purchases.md`.

## Depende de
10

## Prioridad sugerida
alta — cierra el hexágono por el lado driven.

## Notas y referencias
- Caveat SQLite (write-lock a nivel de BD) documentado como restricción conocida del runtime elegido.
- ADR-0008 concede el coste honestamente: los atributos ORM son más ergonómicos; XML es una elección de pureza porque el ejercicio VA de arquitectura.

## Origen
Desglose de backlog — sesión PM de 2026-08-18.
