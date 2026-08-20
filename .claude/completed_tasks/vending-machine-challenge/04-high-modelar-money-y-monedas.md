# Modelar Money, CoinDenomination, Coin y CoinCollection con TDD

## Contexto
Primer ticket de dominio puro: la base monetaria sobre la que se asienta todo. Dinero en céntimos enteros — los floats hacen la igualdad y la acumulación insanas (IEEE-754) y en este dominio un error de redondeo es dinero. Detalle del enunciado a modelar: acepta 4 monedas pero solo devuelve 3 — la de 1.00 nunca se dispensa como cambio (el ejemplo 3 lo confirma: 1.00−0.65 → 0.25+0.10).

## Criterios de aceptación
- [ ] `Money` final readonly en céntimos int: fromCents, zero, fromDecimalString('0.65'), add, subtract (lanza en negativo), comparaciones, toDecimalString
- [ ] `CoinDenomination` enum respaldado (5, 10, 25, 100) con `isDispensableAsChange(): bool` (100 → false)
- [ ] `Coin` VO sobre denominación; `UnsupportedCoin` lanzada en construcción con valor inválido
- [ ] `CoinCollection` multiset inmutable: add, merge, subtract (lanza si negativo), countOf, total, isEmpty, dispensableOnly, toArray
- [ ] Tests unitarios en `backend/tests/Unit/VendingMachine/Domain/Money/` escritos ANTES (rojo confirmado), cubriendo la asimetría dispensable
- [ ] Cero imports de Symfony/Doctrine (Deptrac verde)

## Capa
domain

## Archivos probablemente afectados
- `backend/src/VendingMachine/Domain/Money/Money.php`, `CoinDenomination.php`, `Coin.php`, `CoinCollection.php` (a crear)
- `backend/src/VendingMachine/Domain/Exception/VendingMachineError.php` (interface marcador), `UnsupportedCoin.php` (a crear)
- `backend/tests/Unit/VendingMachine/Domain/Money/*Test.php` (a crear)

## Enfoque sugerido
1. TDD por VO, en orden: Money → CoinDenomination → Coin → CoinCollection.
2. CoinCollection interno: `array<int,int>` denominación→count.
3. `make test-mutation` al cerrar: la base monetaria debe salir con MSI alto.

(No prescriptivo — el implementador puede divergir si encuentra mejor camino.)

## ADR asociado
Sí — crear `docs/adr/0004-money-as-integer-cents.md` (incluye: JSON serializa dinero como string decimal, y la regla "1.00 nunca es cambio" como requisito interpretado).

## Depende de
03

## Prioridad sugerida
alta — todo el dominio depende de esta base.

## Notas y referencias
- Regla del repo en `CLAUDE.md` § Domain model; el float monetario es Critical para `security-reviewer`.

## Origen
Desglose de backlog — sesión PM de 2026-08-18.
