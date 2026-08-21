<?php

declare(strict_types=1);

namespace App\VendingMachine\Infrastructure\Persistence\Doctrine\Type;

use App\VendingMachine\Domain\Money\AcceptedCoins;
use App\VendingMachine\Domain\Money\CoinDenomination;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use JsonException;

/**
 * AcceptedCoins ⇄ a JSON column, `[5, 10, 25, 100]` — which denominations this
 * machine takes, in cents.
 *
 * A list and not a map, unlike the coin collections next to it in the same
 * table: this says which coins, never how many, and the column should not be
 * able to express a count nobody would read.
 *
 * An empty list is a machine switched off at the acceptor, and it round-trips
 * as `[]` rather than as null. Null would be the same story a missing column
 * tells, and those are different situations: one is a machine out of service,
 * the other is a row written before this feature existed.
 */
final class AcceptedCoinsType extends Type
{
    public const NAME = 'accepted_coins';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getJsonTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): string
    {
        if (!$value instanceof AcceptedCoins) {
            throw InvalidType::new($value, self::NAME, [AcceptedCoins::class]);
        }

        $cents = array_map(
            static fn (CoinDenomination $denomination): int => $denomination->value,
            $value->all(),
        );

        try {
            return json_encode($cents, \JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw ValueNotConvertible::new($value, self::NAME, $error->getMessage(), $error);
        }
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): AcceptedCoins
    {
        if (null === $value || '' === $value) {
            return AcceptedCoins::none();
        }

        if (!\is_string($value)) {
            throw ValueNotConvertible::new($value, AcceptedCoins::class);
        }

        try {
            /** @var array<array-key, mixed> $cents */
            $cents = json_decode($value, true, 4, \JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw ValueNotConvertible::new($value, AcceptedCoins::class, $error->getMessage(), $error);
        }

        return AcceptedCoins::of(...array_map(
            static function (mixed $denomination) use ($cents): CoinDenomination {
                // A denomination this build cannot read means the row was
                // written by something else, or by a later version of this
                // application. Either way it is not ours to interpret, and a
                // loud failure beats a machine that quietly takes a coin less
                // than the row says.
                return \is_int($denomination)
                    ? CoinDenomination::fromCents($denomination)
                    : throw ValueNotConvertible::new($cents, AcceptedCoins::class);
            },
            $cents,
        ));
    }
}
