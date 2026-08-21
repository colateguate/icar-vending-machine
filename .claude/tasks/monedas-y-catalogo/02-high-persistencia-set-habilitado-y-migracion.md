# Persistir el set habilitado: columna, tipo DBAL y migración con default

## Contexto

El ticket 01 añade el set habilitado al agregado; sin columna, una máquina rehidratada lo perdería. El mapping vive en XML fuera de la clase (`backend/config/doctrine/Machine.VendingMachine.orm.xml:32-36`) con un custom type por VO que posee columna (`backend/config/packages/doctrine.yaml`, types `machine_id`/`inventory`/`coin_collection`). Las máquinas existentes deben migrar al default de fábrica: las 4 del enunciado habilitadas (decisión K4).

## Criterios de aceptación

- [ ] Campo nuevo en el XML junto a `change_reserve`/`inserted_coins` (`Machine.VendingMachine.orm.xml:35-36`), con custom type DBAL registrado en `doctrine.yaml` — el agregado nunca ve un string.
- [ ] Migración nueva (junto a `backend/migrations/Version20260819152732.php`) que añade la columna y deja las filas existentes con las 4 del brief habilitadas.
- [ ] `make schema-check` en verde: mapping y migración describen la misma tabla.
- [ ] El contract test abstracto (`backend/tests/Support/Contract/`) cubre el campo nuevo — ambos adaptadores (Doctrine e InMemory) lo persisten/copian; expectativas particulares de cada adaptador en su propio test, nunca en el contrato.
- [ ] Test de integración: guardar una máquina con 0.50 habilitada, rehidratar, y el set sobrevive el viaje.
- [ ] El optimistic locking sigue intacto (la columna `version`, `orm.xml:32`, no se toca).

## Capa

infrastructure

## Repo

icar-vending-machine

## Archivos probablemente afectados

- `backend/config/doctrine/Machine.VendingMachine.orm.xml:32-36` — campo nuevo
- `backend/config/packages/doctrine.yaml` — registro del type
- `backend/src/VendingMachine/Infrastructure/Persistence/Doctrine/Type/` — type nuevo (imitar `CoinCollectionType.php`)
- `backend/migrations/` — migración nueva (a crear)
- `backend/src/VendingMachine/Infrastructure/Persistence/InMemory/InMemoryVendingMachineRepository.php` — el clone superficial debe seguir bastando (el set es VO inmutable; verificarlo)
- `backend/tests/Support/Contract/` + `backend/tests/Integration/`

## Enfoque sugerido

1. Rojo: extender el contract test con el campo nuevo (falla en ambos adaptadores).
2. Type DBAL imitando `CoinCollectionType` (serialización estable, comparable).
3. XML + migración con `UPDATE` de default para filas existentes.
4. `make schema-check` + suite de integración.

(No prescriptivo.)

## ADR asociado

No — aplica ADR-0008 (aggregate as one row, XML mapping) tal cual.

## Depende de

01

## Prioridad sugerida

alta — sin persistencia el contrato (03) no puede montarse.

## Notas y referencias

- Spec: [../../../docs/specs/2026-08-21-configurable-coins-and-catalogue-design.md](../../../docs/specs/2026-08-21-configurable-coins-and-catalogue-design.md)
- La migración con default es la suposición "máquinas existentes → las 4 del brief" del spec; validarla con test.

## Origen

Sesión PM de 2026-08-21 — spec `docs/specs/2026-08-21-configurable-coins-and-catalogue-design.md`
